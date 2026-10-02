<?php
/* ════════════════════════════════════════════════════════════
   منطق پنل ادمین پلتفرم — فقط با نشست ادمین
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const PLAT_DAYS = 86400000;

function plat_tenant_dir(string $slug): string {
  $cfg = p_config();
  return $cfg['paths']['tenants'] . '/' . $slug;
}

/* v5: دسترسی به بانک دادهٔ هر کافه (SQLite/MySQL خودکار) */
function plat_store(string $slug): GStore {
  require_once __DIR__ . '/class-gstore.php';
  return GStore::for(plat_tenant_dir($slug));
}

function plat_find(string $slug): ?array {
  foreach (p_tenants_load()['tenants'] as $t)
    if (($t['slug'] ?? '') === $slug) return $t;
  return null;
}

/* فهرست کافه‌ها با وضعیت لایسنس محاسبه‌شده */
function plat_list(): array {
  $out = [];
  $now = round(microtime(true) * 1000);
  foreach (p_tenants_load()['tenants'] as $t) {
    $lic = ['status'=>'?','days_left'=>null,'license_key'=>''];
    try {
      $db = plat_store($t['slug'])->get();
      if (isset($db['license'])) {
        $lic['status']      = $db['license']['status'] ?? '?';
        $lic['license_key'] = $db['license']['key'] ?? '';
        if ($lic['status'] === 'trial' && !empty($db['license']['expires']))
          $lic['days_left'] = max(0, (int)ceil(($db['license']['expires'] - $now) / PLAT_DAYS));
        if ($lic['status'] === 'active' && !empty($db['license']['paid_until']))
          $lic['days_left'] = max(0, (int)ceil(($db['license']['paid_until'] - $now) / PLAT_DAYS));
      }
    } catch (Throwable $e) {
      $lic['status'] = 'broken';
    }
    $t['lic']       = $lic;
    $t['size_mb']   = round(plat_dir_size(plat_tenant_dir($t['slug'])) / 1048576, 1);
    $t['url']       = p_config()['base_url'] . '/tenants/' . $t['slug'] . '/';
    $out[] = $t;
  }
  return $out;
}

function plat_dir_size(string $dir): int {
  if (!is_dir($dir)) return 0;
  $s = 0;
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = "$dir/$f";
    $s += is_dir($p) ? plat_dir_size($p) : (filesize($p) ?: 0);
  }
  return $s;
}

/* فعال‌سازی دستی لایسنس — مدت به روز */
function plat_activate(string $slug, int $days): void {
  try {
    plat_store($slug)->txn(function (array &$db) use ($days) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      $db['license']['status']     = 'active';
      $db['license']['paid_until'] = round(microtime(true) * 1000) + $days * PLAT_DAYS;
      return $db;
    });
  } catch (Throwable $e) {
    throw new Exception('دیتای کافه یافت نشد');
  }
}

/* غیرفعال‌کردن (مثلاً نکول) */
function plat_suspend(string $slug): void {
  try {
    plat_store($slug)->txn(function (array &$db) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      $db['license']['status'] = 'suspended';
      return $db;
    });
  } catch (Throwable $e) {
    throw new Exception('دیتای کافه یافت نشد');
  }
}

/* ریست رمز کافه‌دار — رمز موقت برمی‌گردد تا به مشتری بدهی */
function plat_reset_pass(string $slug): string {
  $tmp = 'GA-' . random_int(100000, 999999);
  try {
    plat_store($slug)->txn(function (array &$db) use ($tmp) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      $db['auth']['ph'] = password_hash($tmp, PASSWORD_DEFAULT);
      return $db;
    });
  } catch (Throwable $e) {
    throw new Exception('دیتای کافه یافت نشد');
  }
  return $tmp;
}

/* حذف نهایی (پس از گرِیس) یا منتقل‌به-سطل */
function plat_delete(string $slug, bool $final): void {
  $dir = plat_tenant_dir($slug);
  if (!is_dir($dir)) throw new Exception('کافه یافت نشد');
  if ($final) {
    plat_rrmdir($dir);
  } else {
    $trash = p_config()['paths']['data'] . '/trash/' . $slug . '_' . date('ymd_His');
    if (!is_dir(dirname($trash))) mkdir(dirname($trash), 0755, true);
    if (!@rename($dir, $trash)) throw new Exception('انتقال به سطل زباله ناموفق بود — دسترسی نوشتن را بررسی کنید');
  }
  p_lock(function (array &$t) use ($slug) {
    $t['tenants'] = array_values(array_filter($t['tenants'],
      fn($x) => ($x['slug'] ?? '') !== $slug));
  });
  plat_log('delete', ['slug' => $slug, 'final' => $final ? 1 : 0]);
}
function plat_rrmdir(string $dir): void {
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = "$dir/$f";
    is_dir($p) ? plat_rrmdir($p) : @unlink($p);
  }
  @rmdir($dir);
}

/* سطل زباله و پاک‌سازی خودکار پس از grace_days */
function plat_trash_sweep(): int {
  $cfg = p_config();
  $dir = $cfg['paths']['data'] . '/trash';
  if (!is_dir($dir)) return 0;
  $n = 0; $limit = time() - $cfg['limits']['grace_days'] * 86400;
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = "$dir/$f";
    if (filemtime($p) < $limit) { plat_rrmdir($p); $n++; }
  }
  return $n;
}

/* جاروبرقی: رسانه‌های یتیم بالای ۷ روز در uploads کافه‌ها */
function plat_sweep_orphans(): array {
  $cfg = p_config();
  $res = ['scanned'=>0, 'deleted'=>0, 'freed_mb'=>0.0];
  foreach (p_tenants_load()['tenants'] as $t) {
    $up = plat_tenant_dir($t['slug']) . '/uploads';
    if (!is_dir($up)) continue;
    // همهٔ آدرس‌های رسانهٔ شناخته‌شده از بانک کافه
    $used = [];
    try {
      $raw = plat_store($t['slug'])->rawJson();
    } catch (Throwable $e) { $raw = ''; }
    preg_match_all('/uploads\/[A-Za-z0-9_\-\.]+/', $raw, $m);
    foreach ($m[0] as $u) $used[basename($u)] = true;
    $limit = time() - 7 * 86400;
    foreach (scandir($up) ?: [] as $f) {
      if ($f === '.' || $f === '..') continue;
      $res['scanned']++;
      $p = "$up/$f";
      if (isset($used[$f])) continue;             // در استفاده است
      if (filemtime($p) >= $limit) continue;      // تازه آپلود شده (شاید هنوز ذخیره نشده)
      $sz = filesize($p) ?: 0;
      if (@unlink($p)) { $res['deleted']++; $res['freed_mb'] += $sz / 1048576; }
    }
  }
  $res['freed_mb'] = round($res['freed_mb'], 2);
  return $res;
}

/* ═══ v5.0.5-test.1: ریست رمز پرسنل (صندوق‌دار / آشپزخانه) توسط مدیر پلتفرم ═══ */

/* فهرست پرسنل کافه — بدون هش؛ برای نمایش در پنل پلتفرم */
function plat_users(string $slug): array {
  $out = [];
  try {
    $db = plat_store($slug)->get();
    foreach (($db['users'] ?? []) as $u) {
      if (!is_array($u) || empty($u['id'])) continue;
      $out[] = [
        'id'    => (string)$u['id'],
        'name'  => (string)($u['name'] ?? ''),
        'phone' => (string)($u['phone'] ?? ''),
        'haspass' => !empty($u['ph']),
        'haspin'  => !empty($u['pin']),
      ];
    }
  } catch (Throwable $e) { /* کافه خراب — فهرست خالی */ }
  return $out;
}

/* ریست رمز یک کاربر پرسنلی — رمز موقت برمی‌گردد تا به صندوق‌دار/آشپز بدهی */
function plat_reset_user(string $slug, string $uid): string {
  $tmp = 'GA-' . random_int(100000, 999999);
  try {
    $hit = plat_store($slug)->txn(function (array &$db) use ($tmp, $uid) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      foreach (($db['users'] ?? []) as $i => $u) {
        if (!is_array($u) || (string)($u['id'] ?? '') !== $uid) continue;
        $db['users'][$i]['ph'] = password_hash($tmp, PASSWORD_DEFAULT);
        return true;
      }
      throw new Exception('کاربر پرسنلی یافت نشد');
    });
  } catch (Throwable $e) {
    throw new Exception($e->getMessage() === 'کاربر پرسنلی یافت نشد' ? $e->getMessage() : 'دیتای کافه یافت نشد');
  }
  if (!$hit) throw new Exception('کاربر پرسنلی یافت نشد');
  return $tmp;
}

/* نام کاربر پرسنلی — برای پیام تأیید ریست رمز */
function plat_user_name(string $slug, string $uid): string {
  foreach (plat_users($slug) as $u)
    if (($u['id'] ?? '') === $uid) return (string)$u['name'];
  return 'پرسنل';
}

/* ═══════════════════════════════════════════════════════════════
   v5.0.5-test.4: مدیریت کامل پلتفرم — آمار، جزئیات، تمدید، تغییر نام،
   بازیابی از سطل زباله، پشتیبان‌گیری و لاگ کامل عملیات مدیر
   ═══════════════════════════════════════════════════════════════ */

/* لاگ عملیات مدیر پلتفرم — در همان audit.json (رویدادهای plat.*) */
function plat_log(string $ev, array $d = []): void {
  require_once __DIR__ . '/export-control.php';
  xc_audit('plat.' . $ev, $d);
}

/* آمار کل پلتفرم — برای داشبورد بالای پنل */
function plat_stats(): array {
  $list = plat_list();
  $now  = round(microtime(true) * 1000);
  $s = ['total'=>0,'active'=>0,'trial'=>0,'suspended'=>0,'broken'=>0,
        'expiring'=>0,'total_mb'=>0.0,'orders'=>0,'orders24h'=>0,
        'customers'=>0,'items'=>0,'reservations'=>0,'expiring_list'=>[]];
  $s['total'] = count($list);
  foreach ($list as $x) {
    $s['total_mb'] += $x['size_mb'];
    switch ($x['lic']['status']) {
      case 'active':
        $s['active']++;
        if ($x['lic']['days_left'] !== null && $x['lic']['days_left'] <= 7) {
          $s['expiring']++;
          $s['expiring_list'][] = ['slug'=>$x['slug'],'name'=>$x['name'],'days_left'=>(int)$x['lic']['days_left']];
        }
        break;
      case 'trial':    $s['trial']++;     break;
      case 'suspended': $s['suspended']++; break;
      case 'broken':   $s['broken']++;    break;
    }
    try {
      $db = plat_store($x['slug'])->get();
      $s['orders']      += count($db['orders'] ?? []);
      $s['customers']   += count($db['customers'] ?? []);
      $s['reservations'] += count($db['reservations'] ?? []);
      foreach (($db['menu']['cats'] ?? []) as $c)
        $s['items'] += is_array($c) ? count($c['items'] ?? []) : 0;
      foreach (($db['orders'] ?? []) as $o)
        if (is_array($o) && (int)($o['ts'] ?? 0) > $now - 86400000) $s['orders24h']++;
    } catch (Throwable $e) { /* کافه خراب — از آمار رد می‌شود */ }
  }
  $s['total_mb'] = round($s['total_mb'], 1);
  usort($s['expiring_list'], fn($a, $b) => $a['days_left'] <=> $b['days_left']);
  return $s;
}

/* جزئیات یک کافه — برای کارت بسط‌پذیر هر کافه */
function plat_details(string $slug): array {
  require_once __DIR__ . '/export-control.php';
  $d = ['items'=>0,'cats'=>0,'orders'=>0,'orders24h'=>0,'last_order'=>null,
        'customers'=>0,'reservations'=>0,'resv_pending'=>0,'staff'=>0,
        'created'=>null,'setup_done'=>false,'brand'=>'','pending_export'=>false];
  $now = round(microtime(true) * 1000);
  try {
    $db = plat_store($slug)->get();
    $d['brand'] = (string)($db['menu']['brand']['name'] ?? '');
    $d['cats']  = count($db['menu']['cats'] ?? []);
    foreach (($db['menu']['cats'] ?? []) as $c)
      $d['items'] += is_array($c) ? count($c['items'] ?? []) : 0;
    $d['orders']    = count($db['orders'] ?? []);
    $d['customers'] = count($db['customers'] ?? []);
    $d['staff']     = count(plat_users($slug));
    foreach (($db['reservations'] ?? []) as $r)
      if (is_array($r) && ($r['status'] ?? '') === 'pending') $d['resv_pending']++;
    foreach (($db['orders'] ?? []) as $o) {
      $ts = (int)($o['ts'] ?? 0);
      if ($ts > $now - 86400000) $d['orders24h']++;
      if ($ts > 0 && ($d['last_order'] === null || $ts > $d['last_order'])) $d['last_order'] = $ts;
    }
    $d['created']    = $db['created'] ?? null;
    $d['setup_done'] = !empty($db['setup']['done']);
  } catch (Throwable $e) { /* کافه خراب — صفرها نمایش داده می‌شود */ }
  foreach (xc_load()['reqs'] as $r)
    if (($r['slug'] ?? '') === $slug && ($r['status'] ?? '') === 'pending') { $d['pending_export'] = true; break; }
  return $d;
}

/* تغییر نام نمایشی کافه در رجیستری */
function plat_rename(string $slug, string $name): void {
  $name = trim($name);
  if (mb_strlen($name) < 2 || mb_strlen($name) > 60)
    throw new Exception('نام کافه باید بین ۲ تا ۶۰ کاراکتر باشد');
  $hit = false;
  p_lock(function (array &$t) use ($slug, $name, &$hit) {
    foreach ($t['tenants'] as $i => $x)
      if (($x['slug'] ?? '') === $slug) { $t['tenants'][$i]['name'] = $name; $hit = true; return; }
  });
  if (!$hit) throw new Exception('کافه یافت نشد');
  plat_log('rename', ['slug' => $slug, 'name' => $name]);
}

/* تمدید دورهٔ آزمایشی — به انتهای اعتبار فعلی اضافه می‌کند */
function plat_extend_trial(string $slug, int $days): void {
  if ($days < 1 || $days > 3650) throw new Exception('مدت باید بین ۱ تا ۳۶۵۰ روز باشد');
  try {
    plat_store($slug)->txn(function (array &$db) use ($days) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      $now = round(microtime(true) * 1000);
      $cur = (int)($db['license']['expires'] ?? 0);
      $db['license']['status']  = 'trial';
      $db['license']['expires'] = max($now, $cur) + $days * PLAT_DAYS;
      return $db;
    });
  } catch (Throwable $e) {
    throw new Exception('دیتای کافه یافت نشد');
  }
  plat_log('extend_trial', ['slug' => $slug, 'days' => $days]);
}

/* تمدید/تمدید مجدد لایسنس پولی — به انتهای اعتبار فعلی اضافه می‌کند */
function plat_renew(string $slug, int $days): void {
  if ($days < 1 || $days > 3650) throw new Exception('مدت باید بین ۱ تا ۳۶۵۰ روز باشد');
  try {
    plat_store($slug)->txn(function (array &$db) use ($days) {
      if (empty($db)) throw new Exception('دیتای کافه یافت نشد');
      $now = round(microtime(true) * 1000);
      $cur = (int)($db['license']['paid_until'] ?? 0);
      $db['license']['status']     = 'active';
      $db['license']['paid_until'] = max($now, $cur) + $days * PLAT_DAYS;
      return $db;
    });
  } catch (Throwable $e) {
    throw new Exception('دیتای کافه یافت نشد');
  }
  plat_log('renew', ['slug' => $slug, 'days' => $days]);
}

/* فهرست سطل زباله — کافه‌های حذف‌شدهٔ قابل بازگشت */
function plat_trash_list(): array {
  $dir = p_config()['paths']['data'] . '/trash';
  $out = [];
  if (!is_dir($dir)) return $out;
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..' || !is_dir("$dir/$f")) continue;
    $slug = $f; $ts = (int)filemtime("$dir/$f");
    if (preg_match('/^(.+)_(\d{6})_(\d{6})$/', $f, $m)) { $slug = $m[1]; }
    $name = $slug; $lic = '?'; $mb = round(plat_dir_size("$dir/$f") / 1048576, 1);
    /* نام برند از بذر db.json — بدون لمس بانک زندهٔ سندباکس */
    foreach ([$f . '/data/db.json'] as $jf) {
      if (!is_file($dir . '/' . $jf)) continue;
      $j = json_decode((string)file_get_contents($dir . '/' . $jf), true);
      if (is_array($j)) {
        $name = (string)($j['menu']['brand']['name'] ?? $name);
        $lic  = (string)($j['license']['status'] ?? $lic);
        break;
      }
    }
    /* اگر db.json نبود (قبلاً مهاجرت شده) از بذر imported استفاده کن */
    if ($name === $slug && $lic === '?') {
      foreach (glob($dir . '/' . $f . '/data/db.json.imported-*') ?: [] as $gf) {
        $j = json_decode((string)file_get_contents($gf), true);
        if (is_array($j)) {
          $name = (string)($j['menu']['brand']['name'] ?? $name);
          $lic  = (string)($j['license']['status'] ?? $lic);
          break;
        }
      }
    }
    $out[] = ['tname'=>$f, 'slug'=>$slug, 'name'=>$name, 'lic'=>$lic,
              'ts'=>$ts, 'size_mb'=>$mb,
              'taken'=>is_dir(plat_tenant_dir($slug))];
  }
  usort($out, fn($a, $b) => $b['ts'] <=> $a['ts']);
  return $out;
}

/* بازیابی کافه از سطل زباله — فقط اگر اسلاگ آزاد باشد */
function plat_restore(string $tname): void {
  if (!preg_match('/^[a-z0-9][a-z0-9\-]*_\d{6}_\d{6}$/', $tname))
    throw new Exception('نام پوشهٔ سطل زباله نامعتبر است');
  $slug = preg_replace('/_\d{6}_\d{6}$/', '', $tname);
  $trash = p_config()['paths']['data'] . '/trash/' . $tname;
  $dest  = plat_tenant_dir($slug);
  if (!is_dir($trash))      throw new Exception('پوشهٔ سطل زباله یافت نشد');
  if (is_dir($dest))        throw new Exception('کافه‌ای با همین آدرس از قبل وجود دارد — بازیابی ممکن نیست');
  foreach (p_tenants_load()['tenants'] as $t)
    if (($t['slug'] ?? '') === $slug)
      throw new Exception('این اسلاگ در رجیستری ثبت است — بازیابی ممکن نیست');
  /* نام برند برای رکورد تازهٔ رجیستری */
  $name = $slug;
  $j = json_decode((string)@file_get_contents("$trash/data/db.json"), true);
  if (!is_array($j)) {
    foreach (glob("$trash/data/db.json.imported-*") ?: [] as $gf)
      { $j = json_decode((string)@file_get_contents($gf), true); if (is_array($j)) break; }
  }
  if (is_array($j)) $name = (string)($j['menu']['brand']['name'] ?? $name);
  if (!@rename($trash, $dest)) throw new Exception('انتقال پوشه ناموفق بود — دسترسی نوشتن را بررسی کنید');
  p_lock(function (array &$t) use ($slug, $name) {
    foreach ($t['tenants'] as $x)
      if (($x['slug'] ?? '') === $slug) return;   // زیر قفل دوباره چک
    $t['tenants'][] = ['id' => (int)$t['nextId']++, 'slug' => $slug, 'name' => $name,
                       'phone' => '', 'active' => true,
                       'created' => round(microtime(true) * 1000),
                       'domain' => '', 'license' => '', 'restored' => true];
  });
  plat_log('restore', ['slug' => $slug]);
}

/* حذف قطعی یک پوشه از سطل زباله (بدون بازگشت) */
function plat_trash_delete_one(string $tname): void {
  $dir = p_config()['paths']['data'] . '/trash/' . basename($tname);
  if (!is_dir($dir) || basename($dir) !== $tname)
    throw new Exception('پوشهٔ سطل زباله یافت نشد');
  plat_rrmdir($dir);
  plat_log('trash_delete', ['tname' => basename($tname)]);
}

/* پشتیبان کامل کافه (زیپ قابل‌نصب مستقل) — مسیر فایل برمی‌گردد */
function plat_backup(string $slug): array {
  require_once __DIR__ . '/export-control.php';
  if (!class_exists('ZipArchive')) throw new RuntimeException('افزونهٔ zip روی هاست فعال نیست');
  if (!plat_find($slug)) throw new RuntimeException('کافه یافت نشد');
  $out = xc_out_path($slug);
  if (!is_dir(dirname($out))) mkdir(dirname($out), 0755, true);
  xc_build_zip($slug, $out);
  plat_log('backup', ['slug' => $slug, 'file' => basename($out)]);
  return ['path' => $out, 'name' => basename($out)];
}
