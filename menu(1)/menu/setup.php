<?php
/* ═══════════════════════════════════════════════════════════════
   GitiArts Menu Platform — ویزارد نصب (نسخهٔ ۱)
   سه قدم:
     ۱) بررسی محیط (PHP + افزونه‌ها + دسترسی نوشتن)
     ۲) ساخت رمز مدیر پلتفرم (با کلید نصب از config.php)
     ۳) بانک اطلاعاتی: SQLite خودکار (پیشنهادی) یا MySQL اختیاری
   پایان: data/installed.lock ساخته می‌شود و ویزارد قفل می‌شود.
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_name('GA_SETUP');
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$ROOT = __DIR__;
$CFG  = require $ROOT . '/config.php';
$DATA = $ROOT . '/data';
$LOCK = $DATA . '/installed.lock';

function s_esc(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ── ۱) بررسی محیط ──────────────────────────────────────────── */
function s_checks(array $CFG, string $ROOT, string $DATA): array {
  $c = [];
  $c[] = ['PHP ≥ 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION];
  foreach (['gd'=>'پردازش تصویر (GD)','mbstring'=>'رشته‌های چندبایتی (mbstring)',
            'fileinfo'=>'تشخیص نوع فایل','json'=>'JSON','zip'=>'ساخت زیپ'] as $ext=>$lbl)
    $c[] = [$lbl, extension_loaded($ext), ''];
  $c[] = ['درایور بانک (pdo_sqlite یا pdo_mysql)',
          extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'), ''];
  if (!is_dir($DATA)) @mkdir($DATA, 0755, true);
  $c[] = ['نوشتن در پوشهٔ data', is_writable($DATA), ''];
  $c[] = ['نوشتن در پوشهٔ tenants', is_writable($ROOT . '/tenants'), ''];
  if (!is_file($ROOT . '/tenants.json')) @file_put_contents($ROOT . '/tenants.json',
      "{\n    \"nextId\": 1,\n    \"tenants\": [\n    ]\n}", LOCK_EX);
  $c[] = ['فایل tenants.json', is_file($ROOT . '/tenants.json') && is_writable($ROOT . '/tenants.json'), ''];
  $c[] = ['نوشتن در config.php (برای خالی‌کردن کلید نصب)', is_writable($ROOT . '/config.php'), ''];
  return $c;
}
function s_all_ok(array $c): bool { foreach ($c as $x) if (!$x[1]) return false; return true; }

/* ── ۲/۳) پردازش فرم ────────────────────────────────────────── */
$stepErr = []; $doneAdmin = false; $doneDb = false; $dbInfo = '';
$dbDriver = 'sqlite';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
  $act = (string)($_POST['act'] ?? '');

  if ($act === 'admin') {
    require_once $ROOT . '/api/lib/platform-auth.php';
    $err = pauth_setup((string)($_POST['key'] ?? ''), (string)($_POST['p1'] ?? ''), (string)($_POST['p2'] ?? ''));
    if ($err !== '') $stepErr['admin'] = $err;
    else {
      $doneAdmin = true;
      /* رمز مخفی پلتفرم برای مهر لایسنس */
      require_once $ROOT . '/api/lib/export-control.php';
      xc_secret();
    }
  }

  if ($act === 'db' && $doneAdmin === false && !empty($_POST['force_db'])) {
    /* اجازهٔ تنظیم DB بدون گرفتن رمز (اگر رمز قبلاً ست شده) */
  }

  if ($act === 'db') {
    $dbDriver = ($_POST['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    if ($dbDriver === 'mysql') {
      $host = trim((string)($_POST['host'] ?? 'localhost'));
      $name = trim((string)($_POST['name'] ?? ''));
      $user = trim((string)($_POST['user'] ?? ''));
      $pass = (string)($_POST['pass'] ?? '');
      $port = (int)($_POST['port'] ?? 3306);
      if ($name === '' || $user === '') {
        $stepErr['db'] = 'نام بانک و نام کاربری MySQL را وارد کنید';
      } elseif (!extension_loaded('pdo_mysql')) {
        $stepErr['db'] = 'افزونهٔ pdo_mysql روی هاست فعال نیست';
      } else {
        $cfgFile = $DATA . '/db-config.json';
        @file_put_contents($cfgFile, json_encode([
          'driver'=>'mysql','host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'pass'=>$pass,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        try {
          require_once $ROOT . '/api/lib/class-gstore.php';
          GStore::for($ROOT)->ensureSchema();     // اتصال + ساخت جدول
          $doneDb = true; $dbInfo = 'MySQL متصل شد (' . s_esc($host) . ' / ' . s_esc($name) . ')';
        } catch (Throwable $e) {
          @unlink($cfgFile);
          $stepErr['db'] = 'اتصال MySQL برقرار نشد: ' . $e->getMessage();
        }
      }
    } else {
      @unlink($DATA . '/db-config.json');         // حذف پیکربندی MySQL قبلی → SQLite
      try {
        require_once $ROOT . '/api/lib/class-gstore.php';
        GStore::for($ROOT)->ensureSchema();
        $doneDb = true; $dbInfo = 'SQLite خودکار آماده شد';
      } catch (Throwable $e) { $stepErr['db'] = $e->getMessage(); }
    }

    if ($doneDb && !empty($_POST['blank_key'])) {
      $src = (string)file_get_contents($ROOT . '/config.php');
      $new = preg_replace("/(['\"])setup_key\\1\\s*=>\\s*(['\"])[^'\"]*\\2/",
                         "\$1setup_key\$1 => ''", $src, 1);
      if ($new !== null && $new !== $src) @file_put_contents($ROOT . '/config.php', $new, LOCK_EX);
    }
    if ($doneDb) @file_put_contents($LOCK, (string)time(), LOCK_EX);
  }
}

$installed = is_file($LOCK);
$checks    = s_checks($CFG, $ROOT, $DATA);
$envOk     = s_all_ok($checks);
require_once $ROOT . '/api/lib/platform-auth.php';
$adminDone = pauth_configured();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب GitiArts Menu Platform</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;line-height:1.9;min-height:100dvh;padding:24px 14px}
.wrap{max-width:640px;margin:0 auto}
.logo{font-size:19px;font-weight:900;text-align:center;margin-bottom:16px}
.card{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:18px;padding:20px;margin-bottom:14px}
h2{font-size:14.5px;color:#B4531F;margin-bottom:10px}
table{width:100%;border-collapse:collapse;font-size:12.5px}
td{padding:5px 8px;border-bottom:1px dashed #DFD2B6}
.ok{color:#4F7A3D;font-weight:800}.bad{color:#A93B2A;font-weight:800}
label{display:block;font-size:12.5px;font-weight:700;margin:10px 0 4px}
input[type=text],input[type=password],input[type=number]{width:100%;padding:9px;border:1px solid #CBBB99;border-radius:10px;font:inherit;background:#fff}
button{width:100%;padding:11px;border:0;border-radius:12px;background:#B4531F;color:#fff;font:inherit;font-weight:800;cursor:pointer;margin-top:12px}
button:hover{opacity:.92}
.err{background:rgba(169,59,42,.09);border:1px solid #A93B2A;color:#A93B2A;border-radius:10px;padding:9px 13px;font-size:12.5px;margin:8px 0}
.okb{background:rgba(79,122,61,.1);border:1px solid #4F7A3D;color:#4F7A3D;border-radius:10px;padding:9px 13px;font-size:12.5px;margin:8px 0}
.muted{color:#7D6C54;font-size:12px}
.mini{font-size:11px;color:#AC9C81}
.ck{display:flex;gap:6px;align-items:center;font-size:12px;font-weight:600;margin-top:10px}
.ck input{width:auto}
code{background:#F2EADA;border-radius:6px;padding:1px 7px;direction:ltr;display:inline-block;font-size:11.5px}
a{color:#B4531F;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">🌿 نصب GitiArts Menu Platform</div>

<?php if ($installed): ?>
  <div class="card">
    <h2>✅ نصب قبلاً انجام شده</h2>
    <p class="muted">این ویزارد برای امنیت قفل شده است. اگر می‌خواهید دوباره اجرا شود، فایل <code>data/installed.lock</code> را از هاست حذف کنید.</p>
    <p style="margin-top:10px"><a href="panel/index.php">ورود به پنل مدیریت پلتفرم →</a></p>
    <p><a href="index.html">مشاهدهٔ صفحهٔ اصلی →</a></p>
  </div>

<?php else: ?>

  <div class="card">
    <h2>۱) بررسی محیط هاست</h2>
    <table>
      <?php foreach ($checks as $x): ?>
        <tr><td><?= s_esc($x[0]) ?></td>
            <td class="<?= $x[1] ? 'ok' : 'bad' ?>"><?= $x[1] ? '✓ آماده' : '✗ مشکل' ?>
              <?php if ($x[2]): ?><span class="mini" dir="ltr"><?= s_esc($x[2]) ?></span><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$envOk): ?><p class="err">تا رفع موارد قرمز نمی‌توان نصب را ادامه داد. موارد قرمز معمولاً از بخش «Select PHP Version» سی‌پنل قابل فعال‌سازی است.</p><?php endif; ?>
  </div>

  <div class="card">
    <h2>۲) رمز مدیر پلتفرم</h2>
    <?php if ($adminDone): ?>
      <p class="okb">✓ رمز مدیر قبلاً تنظیم شده — این قدم را می‌توانید رد کنید.</p>
    <?php else: ?>
      <?php if (!empty($stepErr['admin'])): ?><p class="err"><?= s_esc($stepErr['admin']) ?></p><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= s_esc($_SESSION['csrf']) ?>">
        <input type="hidden" name="act" value="admin">
        <label>کلید نصب (مقدار <code>setup_key</code> در config.php)</label>
        <input type="text" name="key" required dir="ltr">
        <label>رمز مدیر پلتفرم</label>
        <input type="password" name="p1" required minlength="8">
        <label>تکرار رمز</label>
        <input type="password" name="p2" required minlength="8">
        <button <?= $envOk ? '' : 'disabled' ?>>ساخت رمز مدیر</button>
      </form>
      <p class="mini" style="margin-top:8px">💡 اول در config.php مقدار <code>setup_key</code> را یک عبارت دلخواه بگذارید و همین‌جا واردش کنید. بعد از پایان نصب، خودکار خالی می‌شود.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>۳) بانک اطلاعاتی</h2>
    <?php if (!empty($stepErr['db'])): ?><p class="err"><?= s_esc($stepErr['db']) ?></p><?php endif; ?>
    <?php if ($doneDb): ?>
      <p class="okb">✓ <?= $dbInfo ?></p>
      <p class="okb">🎉 نصب کامل شد! ویزارد قفل شد.</p>
      <p><a href="panel/index.php">ورود به پنل مدیریت پلتفرم →</a></p>
      <p><a href="register.php">ساخت اولین کافه (ثبت‌نام) →</a></p>
      <p class="mini">⚠️ یادتان نرود در config.php مقدار <code>setup_key</code> را خالی کنید (اگر خودکار خالی نشد).</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= s_esc($_SESSION['csrf']) ?>">
        <input type="hidden" name="act" value="db">
        <label class="ck"><input type="radio" name="driver" value="sqlite" checked onchange="dbMode()">SQLite — خودکار و بدون تنظیم (پیشنهادی برای شروع و برای هاست‌های معمولی)</label>
        <label class="ck"><input type="radio" name="driver" value="mysql" onchange="dbMode()">MySQL — اگر بانک ساخته‌اید (سرعت بالاتر در ترافیک سنگین)</label>

        <div id="mysqlBox" style="display:none;border:1px dashed #CBBB99;border-radius:12px;padding:12px;margin-top:8px">
          <label>میزبان (Host)</label><input type="text" name="host" value="localhost" dir="ltr">
          <label>پورت</label><input type="number" name="port" value="3306" dir="ltr">
          <label>نام بانک</label><input type="text" name="name" dir="ltr">
          <label>نام کاربری</label><input type="text" name="user" dir="ltr">
          <label>رمز</label><input type="password" name="pass" dir="ltr">
          <p class="mini">در سی‌پنل: MySQL® Databases → ساخت بانک + کاربر + FULL PRIVILEGES. اطلاعات را دقیقاً همین‌جا وارد کنید.</p>
        </div>

        <label class="ck"><input type="checkbox" name="blank_key" value="1" checked>بعد از نصب، <code>setup_key</code> در config.php خودکار خالی شود</label>
        <button <?= ($envOk && $adminDone) ? '' : 'disabled' ?>>اتمام نصب</button>
      </form>
    <?php endif; ?>
  </div>

<?php endif; ?>
  <p class="mini" style="text-align:center">GitiArts Menu Platform · نسخهٔ <?= s_esc((string)($CFG['version'] ?? '5.0.0')) ?> · پشتیبانی: <span dir="ltr"><?= s_esc((string)$CFG['support_telegram']) ?></span></p>
</div>
<script>
function dbMode(){
  const v = document.querySelector('input[name=driver]:checked').value;
  document.getElementById('mysqlBox').style.display = v === 'mysql' ? 'block' : 'none';
}
</script>
</body>
</html>
