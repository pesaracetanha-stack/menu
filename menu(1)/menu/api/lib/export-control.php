<?php
/* ═══════════════════════════════════════════════════════════════
   سامانهٔ خروجی (زیپ) کنترل‌شده — نسخهٔ ۱
   قانون طلایی: فایل نصبی فقط با «تیکت + تأیید مدیر پلتفرم» +
   «لینک یک‌بارمصرف با مهلت» قابل دانلود است. همه‌چیز لاگ می‌شود.
   جریان:
     ۱) کافه‌دار از پنل خودش درخواست می‌دهد  (xc_request)
     ۲) مدیر در پنل پلتفرم تأیید می‌کند      (xc_approve → توکن ۷۲ساعته)
     ۳) مدیر لینک را برای مشتری می‌فرستد
     ۴) export-download.php توکن را یک‌بار مصرف می‌کند
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

const XC_FILE       = __DIR__ . '/../../data/export-requests.json';
const XC_AUDIT_FILE = __DIR__ . '/../../data/audit.json';
const XC_LINK_HOURS = 72;          // مهلت استفاده از لینک
const XC_MAX_DONE   = 40;          // حداکثر درخواستِ تمام‌شده نگه‌داشته‌شده

/* ── خواندن/نوشتن اتمیک فایل درخواست‌ها ─────────────────────── */
function xc_load(): array {
  $j = json_decode((string)@file_get_contents(XC_FILE), true);
  return (is_array($j) && isset($j['reqs']) && is_array($j['reqs']))
    ? $j : ['nextId' => 1, 'reqs' => []];
}
function xc_txn(callable $fn) {
  $dir = dirname(XC_FILE);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fp = fopen(XC_FILE, 'c+');
  if (!$fp) throw new RuntimeException('فایل درخواست‌ها قابل باز شدن نیست');
  flock($fp, LOCK_EX);
  try {
    $j = json_decode((string)stream_get_contents($fp), true);
    $d = (is_array($j) && isset($j['reqs']) && is_array($j['reqs']))
       ? $j : ['nextId' => 1, 'reqs' => []];
    $r = $fn($d);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $r;
  } finally {
    flock($fp, LOCK_UN); fclose($fp);
  }
}

/* ── لاگ حسابرسی (همیشه اضافه‌شونده) ────────────────────────── */
function xc_audit(string $event, array $d = []): void {
  $dir = dirname(XC_AUDIT_FILE);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fp = fopen(XC_AUDIT_FILE, 'c+');
  if (!$fp) return;
  flock($fp, LOCK_EX);
  $j = json_decode((string)stream_get_contents($fp), true);
  $log = (is_array($j) && isset($j['log']) && is_array($j['log'])) ? $j['log'] : [];
  $log[] = ['ts' => time(), 'ip' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '-'),
            'ev' => $event, 'd' => $d];
  if (count($log) > 500) $log = array_slice($log, -500);
  ftruncate($fp, 0); rewind($fp);
  fwrite($fp, json_encode(['log' => $log], JSON_UNESCAPED_UNICODE));
  flock($fp, LOCK_UN); fclose($fp);
}
function xc_audit_tail(int $n = 30): array {
  $j = json_decode((string)@file_get_contents(XC_AUDIT_FILE), true);
  $log = (is_array($j) && isset($j['log']) && is_array($j['log'])) ? $j['log'] : [];
  return array_slice(array_reverse($log), 0, $n);
}

/* ── ۱) درخواست از طرف کافه ─────────────────────────────────── */
function xc_request(string $slug, string $note, string $byName): int {
  $id = xc_txn(function (array &$d) use ($slug, $note, $byName) {
    // اگر درخواست در انتظارِ همین کافه هست، دوباره نساز
    foreach ($d['reqs'] as $r)
      if (($r['slug'] ?? '') === $slug && ($r['status'] ?? '') === 'pending')
        return (int)$r['id'];
    $id = (int)$d['nextId']++;
    $d['reqs'][] = ['id' => $id, 'slug' => $slug, 'name' => $byName,
                    'note' => mb_substr($note, 0, 300), 'ts' => time(),
                    'status' => 'pending'];
    // فقط ۴۰ رکوردِ بسته‌شدهٔ آخر نگه داشته می‌شود
    $done = array_values(array_filter($d['reqs'], fn($r) => ($r['status'] ?? '') !== 'pending'));
    if (count($done) > XC_MAX_DONE) {
      $cut  = array_slice($done, 0, count($done) - XC_MAX_DONE);
      $kill = array_column($cut, 'id');
      $d['reqs'] = array_values(array_filter($d['reqs'],
        fn($r) => !in_array((int)$r['id'], $kill, true)));
    }
    return $id;
  });
  xc_audit('request', ['slug' => $slug, 'id' => $id]);
  return $id;
}

/* آخرین درخواستِ یک کافه (برای نمایش در پنل کافه‌دار) */
function xc_latest_for(string $slug): ?array {
  $d = xc_load(); $out = null;
  foreach ($d['reqs'] as $r)
    if (($r['slug'] ?? '') === $slug) $out = $r;
  return $out;
}

/* ── ۲) تأیید مدیر → لینک یک‌بارمصرف ────────────────────────── */
function xc_approve(int $id, string $slug): string {
  $token = bin2hex(random_bytes(32));                       // ۶۴ کاراکتر
  $hash  = hash('sha256', $token);
  xc_txn(function (array &$d) use ($id, $slug, $hash) {
    foreach ($d['reqs'] as &$r) {
      if ((int)$r['id'] === $id && ($r['slug'] ?? '') === $slug) {
        if (($r['status'] ?? '') !== 'pending')
          throw new RuntimeException('این درخواست قبلاً بسته شده است');
        $r['status']   = 'approved';
        $r['token']    = $hash;
        $r['expires']  = time() + XC_LINK_HOURS * 3600;
        $r['used']     = 0;
        $r['approved_at'] = time();
        return;
      }
    }
    throw new RuntimeException('درخواست یافت نشد');
  });
  xc_audit('approve', ['slug' => $slug, 'id' => $id]);
  return $token;   // فقط همین یک‌بار به‌صورت خام برمی‌گردد
}

function xc_deny(int $id, string $slug): void {
  xc_txn(function (array &$d) use ($id, $slug) {
    foreach ($d['reqs'] as &$r) {
      if ((int)$r['id'] === $id && ($r['slug'] ?? '') === $slug) {
        if (($r['status'] ?? '') !== 'pending')
          throw new RuntimeException('این درخواست قبلاً بسته شده است');
        $r['status'] = 'denied';
        return;
      }
    }
    throw new RuntimeException('درخواست یافت نشد');
  });
  xc_audit('deny', ['slug' => $slug, 'id' => $id]);
}

/* توکن → رکورد درخواست (یا خطا) */
function xc_validate_token(string $token): array {
  $hash = hash('sha256', $token);
  foreach (xc_load()['reqs'] as $r) {
    if (($r['token'] ?? '') === $hash) {
      if (($r['used'] ?? 0))            throw new RuntimeException('این لینک قبلاً استفاده شده است (یک‌بارمصرف)');
      if (time() > ($r['expires'] ?? 0)) throw new RuntimeException('مهلت این لینک تمام شده است');
      return $r;
    }
  }
  throw new RuntimeException('لینک نامعتبر است');
}
function xc_mark_used(string $token): void {
  $hash = hash('sha256', $token);
  xc_txn(function (array &$d) use ($hash) {
    foreach ($d['reqs'] as &$r)
      if (($r['token'] ?? '') === $hash) { $r['used'] = time(); return; }
  });
}

/* ── ۳) مهر لایسنس روی بستهٔ خروجی ──────────────────────────── */
function xc_secret(): string {
  $f = dirname(XC_FILE) . '/platform.json';
  $j = json_decode((string)@file_get_contents($f), true);
  if (!empty($j['secret'])) return (string)$j['secret'];
  $secret = bin2hex(random_bytes(32));
  $d = is_array($j) ? $j : [];
  $d['secret'] = $secret;
  file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
  return $secret;
}
function xc_license_seal(string $slug): array {
  $db   = plat_store($slug)->get();
  $lic  = $db['license'] ?? [];
  $body = ['slug' => $slug, 'key' => (string)($lic['key'] ?? ''),
           'status' => (string)($lic['status'] ?? 'trial'),
           'paid_until' => (int)($lic['paid_until'] ?? 0),
           'issued_at' => time(), 'version' => '5.0.0'];
  $body['sig'] = hash_hmac('sha256',
      $body['slug'] . '|' . $body['key'] . '|' . $body['status'] . '|' . $body['paid_until'] . '|' . $body['issued_at'],
      xc_secret());
  return $body;
}

/* ── ۴) ساخت بستهٔ زیپ مستقل ──────────────────────────────────
   ساختار بسته: لایهٔ بسته‌بندی + tenants/<slug> کامل
   (مسیرهای نسبی tenant دست‌نخورده می‌ماند؛ login.php مستقل
    در ریشه گذاشته می‌شود تا ../../login.php هم کار کند)        */
function xc_exclude_default(string $rel): bool {
  if (preg_match('#(^|/)data/(db\.sqlite(-wal|-shm)?|db-config\.json|platform\.json|export-requests\.json|audit\.json|installed\.lock|trash)#', $rel)) return true;
  if (preg_match('#(^|/)data/db\.json\.imported-#', $rel))       return true;
  if (preg_match('#(^|/)data/backups/#', $rel))                  return true;
  return false;
}

function xc_build_zip(string $slug, string $outPath): array {
  require_once __DIR__ . '/platform-admin.php';
  if (!class_exists('ZipArchive')) throw new RuntimeException('افزونهٔ zip روی هاست فعال نیست');
  $dir = plat_tenant_dir($slug);
  if (!is_dir($dir)) throw new RuntimeException('پوشهٔ کافه یافت نشد');

  // سند زندهٔ داده → db.json تازه (نصب مستقل خودش به SQLite منتقل می‌کند)
  $seedJson = plat_store($slug)->rawJson();
  $seal     = xc_license_seal($slug);
  $tmpJson  = tempnam(sys_get_temp_dir(), 'ga_seed_');
  file_put_contents($tmpJson, $seedJson);
  $tmpSeal  = tempnam(sys_get_temp_dir(), 'ga_seal_');
  file_put_contents($tmpSeal, json_encode($seal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  $zip = new ZipArchive();
  if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
    throw new RuntimeException('ساخت فایل زیپ ممکن نشد');

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY);
  $files = 0;
  foreach ($it as $f) {
    $rel = ltrim(str_replace([$dir, '\\'], ['', '/'], (string)$f), '/');
    if ($f->isDir() || xc_exclude_default($rel)) continue;
    $zip->addFile((string)$f, 'tenants/' . $slug . '/' . $rel); $files++;
  }
  $zip->addFile($tmpJson, 'tenants/' . $slug . '/data/db.json');
  $zip->addFile($tmpSeal, 'tenants/' . $slug . '/data/license.json');

  // لایهٔ مستقل: ورود + ریدایرکت + راهنما
  $zip->addFromString('login.php',        xc_tpl_login($slug));
  $zip->addFromString('index.php',        "<?php header('Location: tenants/{$slug}/index.html');");
  $zip->addFromString('tenants/.htaccess', "Options -Indexes\n");
  $zip->addFromString('راهنمای-نصب.html', xc_tpl_guide($slug, $seal));
  $zip->close();
  @unlink($tmpJson); @unlink($tmpSeal);
  return ['files' => $files, 'path' => $outPath];
}

function xc_out_path(string $slug): string {
  return dirname(XC_FILE) . '/exports/' . $slug . '-' . date('ymd_His') . '.zip';
}

/* ── قالب login.php مستقل (داخل بستهٔ خروجی) ────────────────── */
function xc_tpl_login(string $slug): string {
  return '<?php
declare(strict_types=1);
/* ورود مستقل — ساخته‌شده توسط پلتفرم GitiArts برای بستهٔ ' . $slug . ' */
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
  session_name("TEN_SESS");
  session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$secure,"httponly"=>true,"samesite"=>"Lax"]);
  session_start();
}
if (isset($_SESSION["tenant"]) && $_SESSION["tenant"] === ' . var_export($slug, true) . ') {
  header("Location: tenants/' . $slug . '/hub.php"); exit;
}
$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $pass = (string)($_POST["pass"] ?? "");
  try {
    require __DIR__ . "/tenants/' . $slug . '/api/lib/class-gstore.php";
    $db = GStore::for(__DIR__ . "/tenants/' . $slug . '")->get();
    if (!empty($db["auth"]["ph"]) && password_verify($pass, $db["auth"]["ph"])) {
      session_regenerate_id(true);
      $_SESSION["tenant"] = ' . var_export($slug, true) . ';
      $_SESSION["tenant_user"] = (string)($db["auth"]["user"] ?? "");
      header("Location: tenants/' . $slug . '/hub.php"); exit;
    }
    $err = "رمز اشتباه است";
  } catch (Throwable $e) { $err = "خطا: " . htmlspecialchars($e->getMessage()); }
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به پنل</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;display:grid;place-items:center;min-height:100dvh;margin:0}
.c{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:18px;padding:28px;width:min(360px,90vw)}
h1{font-size:16px;color:#271D12;margin:0 0 14px}
input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #CBBB99;border-radius:10px;font:inherit;margin-bottom:10px}
button{width:100%;padding:10px;border:0;border-radius:10px;background:#B4531F;color:#fff;font:inherit;font-weight:800;cursor:pointer}
.e{color:#A93B2A;font-size:12px;margin-bottom:8px}</style></head><body>
<div class="c"><h1>🌿 ورود به پنل مدیریت</h1>
<?php if ($err): ?><div class="e"><?= $err ?></div><?php endif; ?>
<form method="post"><input type="password" name="pass" placeholder="رمز پنل" required autofocus>
<button>ورود</button></form></div></body></html>';
}

/* ── قالب راهنمای نصب داخل بستهٔ خروجی ─────────────────────── */
function xc_tpl_guide(string $slug, array $seal): string {
  $lic = htmlspecialchars((string)($seal['key'] ?? '—'));
  $st  = htmlspecialchars((string)($seal['status'] ?? '—'));
  return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>راهنمای نصب</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;line-height:2;margin:0;padding:24px 14px}
.c{max-width:640px;margin:0 auto;background:#FBF6EB;border:1px solid #DFD2B6;border-radius:18px;padding:22px}
h1{font-size:17px}h2{font-size:14px;color:#B4531F;margin:18px 0 6px}
ol{padding-right:20px}code{background:#F2EADA;border-radius:6px;padding:2px 7px;direction:ltr;display:inline-block}
.lic{background:#F2EADA;border-radius:12px;padding:10px 14px;font-size:13px;margin-top:14px}</style></head><body><div class="c">
<h1>🌿 راهنمای نصب بستهٔ مستقل منو</h1>
<h2>مراحل نصب روی هاست سی‌پنل</h2>
<ol>
<li>در سی‌پنل وارد <b>File Manager</b> شوید.</li>
<li>محتویات همین فایل زیپ را در پوشهٔ موردنظر (مثلاً <code>public_html</code> یا زیرپوشهٔ آن) آپلود و Extract کنید.</li>
<li>کافی است آدرس منو را در مرورگر باز کنید — بانک اطلاعاتی <b>خودکار</b> ساخته می‌شود؛ هیچ تنظیمی لازم نیست.</li>
<li>پنل مدیریت: <code>tenants/' . htmlspecialchars($slug) . '/hub.php</code> — دسترسی‌های مدیریت، صندوق و آشپزخانه. یا از صفحهٔ اصلی <code>login.php</code> وارد شوید.</li>
</ol>
<h2>نکات</h2>
<ul>
<li>اگر می‌خواهید منو روی آدرس اصلی دامنه باشد، محتویات زیپ را داخل <code>public_html</code> بریزید.</li>
<li>پوشهٔ <code>data</code> حاوی بانک اطلاعاتی است — هرگز حذفش نکنید و دسترسی آن ۷۵۵ بماند.</li>
<li>پشتیبان‌گیری: هر چند وقت یک‌بار از پوشهٔ <code>data</code> یک کپی در جای امن نگه دارید.</li>
</ul>
<div class="lic">🔐 مجوز نصب — کد لایسنس: <b dir="ltr">' . $lic . '</b> · وضعیت: ' . $st .
' · صادرشده در: ' . htmlspecialchars(date('Y-m-d')) . '</div>
</div></body></html>';
}
