<?php
/* ════════════════════════════════════════════════════════════
   آپدیتر پنل — نصب بستهٔ آپدیت با یک کلیک (بدون FTP)
   BUILD: upd-v1
   ────────────────────────────────────────────────────────────
   › upd_apply(zip)  : پشتیبان خودکار ← استخراج با حفاظت دادهٔ زنده
     ‹ فایل‌های زیپ → ریشهٔ پروژه؛ ورودی‌های «حفاظت‌شده» رد می‌شوند
     ‹ updates/remove.json در زیپ = فهرست فایل‌های زائد برای حذف
     ‹ بستهٔ قدیمی‌تر از نسخهٔ نصب‌شده رد می‌شود (ضد downgrade)
     ‹ v5.0.5-test.2: فایل‌های قالب (tenants/_template/) که در بسته آمدند، بلافاصله
       به همهٔ کافه‌های ثبت‌شدهٔ tenants.json هم کپی می‌شوند (بدون data/ و uploads/) —
       کافه‌های ثبت‌شدهٔ روی هاست دیگر از آپدیت جا نمی‌مانند
   › upd_scan()      : اسکن سلامت فایل‌ها با updates/checksums.json
   › upd_backup()    : زیپ کامل وضعیت فعلی → data/backups/ (نگهداری ۵)
   ‹ فایل‌های محافظت‌شده هرگز از زیپ اعمال نمی‌شوند:
     config.php · data/ · tenants.json · tenants/[slug]/data/
     tenants/[slug]/uploads/ · uploads/ · updates/packages/
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/* ── نسخه و مانیفست ── */
function upd_manifest(): array {
  $j = json_decode((string)@file_get_contents(p_root() . '/updates/manifest.json'), true);
  return is_array($j) ? $j : [];
}
function upd_manifest_version(): string {
  return (string)(upd_manifest()['version'] ?? '');
}
function upd_config_version(): string {
  $c = p_config();
  return (string)($c['version'] ?? '');
}
/* نسخهٔ «فعلی» = مانیفست آپدیت (با هر نصب تازه‌سازی می‌شود)، وگرنه config */
function upd_current_version(): string {
  $m = upd_manifest_version();
  return $m !== '' ? $m : upd_config_version();
}

/* ── فایل‌های زندهٔ هاست — هرگز از زیپ آپدیت اعمال نمی‌شوند ── */
function upd_protected(string $rel): bool {
  $rel = ltrim($rel, '/');
  if ($rel === 'config.php')        return true;   // کلیدهای زندهٔ پیامک/نصب
  if ($rel === 'tenants.json')      return true;   // رجیستری کافه‌ها
  if (str_starts_with($rel, 'data/'))        return true;   // وضعیت زمان‌اجرا + پشتیبان‌ها
  if (preg_match('#^tenants/[^/]+/(data|uploads)/#', $rel)) return true; // داده و رسانهٔ هر کافه
  if (str_starts_with($rel, 'uploads/'))     return true;
  if (str_starts_with($rel, 'updates/packages/')) return true; // بسته‌های خروجی
  if (preg_match('#\.(sqlite|sqlite-wal|sqlite-shm)$#', $rel)) return true;
  return false;
}

/* ── نرمال‌سازی نام ورودی زیپ + گشت‌وسپر مسیر ── */
function upd_norm_name(string $n): string {
  $n = str_replace('\\', '/', $n);
  $n = preg_replace('#^(\./|/)+#', '', $n) ?? $n;
  return $n;
}
function upd_unsafe_name(string $n): bool {
  return $n === '' || str_contains($n, '..') || str_starts_with($n, '/');
}

/* ── پشتیبان کامل قبل از هر تغییر — data/backups/pre-update-*.zip ── */
function upd_backup(string $tag, array &$msgs): ?string {
  if (!class_exists('ZipArchive')) { $msgs[] = 'افزونهٔ zip برای پشتیبان‌گیری نیست — نصب لغو شد'; return null; }
  $root = p_root();
  $dir  = $root . '/data/backups';
  if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $msgs[] = 'پوشهٔ data/backups ساخته نشد'; return null; }

  /* بهترین تلاش برای چک‌پوینت WAL — کپی تمیزتر از پایگاه داده */
  foreach (array_merge(glob($root . '/data/*.sqlite') ?: [],
                       glob($root . '/tenants/*/data/*.sqlite') ?: []) as $db) {
    try { (new PDO('sqlite:' . $db))->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) { /* بی‌اهمیت */ }
  }

  $out = $dir . '/' . $tag . '-' . date('ymd_His') . '.zip';
  $zip = new ZipArchive();
  if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { $msgs[] = 'ساخت فایل پشتیبان ممکن نشد'; return null; }
  $n = 0;
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY);
  foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $rel = upd_norm_name(substr((string)$f, strlen($root) + 1));
    if (str_starts_with($rel, 'data/backups/')) continue;          // بازگشت پشتیبانِ پشتیبان ممنوع
    $zip->addFile((string)$f, $rel); $n++;
  }
  $zip->close();
  if ($n === 0) { @unlink($out); $msgs[] = 'پشتیبان خالی ماند'; return null; }
  return $out;
}

/* نگهداری فقط ۵ پشتیبان آخر */
function upd_prune_backups(int $keep = 5): int {
  $files = glob(p_root() . '/data/backups/*.zip') ?: [];
  usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
  $rm = 0;
  foreach (array_slice($files, $keep) as $old) { @unlink($old); $rm++; }
  return $rm;
}

/* ── لاگ نصب آپدیت‌ها (۱۰ رویداد آخر) ── */
function upd_log_file(): string { return p_root() . '/data/update-log.json'; }
function upd_log_load(): array {
  $j = json_decode((string)@file_get_contents(upd_log_file()), true);
  return is_array($j) ? $j : [];
}
function upd_log_add(array $e): void {
  $all = upd_log_load();
  array_unshift($all, $e + ['ts' => time()]);
  file_put_contents(upd_log_file(),
    json_encode(array_slice($all, 0, 10), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* ════════════════════════════════════════════════════════════
   نصب بستهٔ آپدیت — قلب آپدیتر
   خروجی: گزارش کامل (applied / deleted / skipped / backup / version)
   خطاها با RuntimeException با پیام فارسی
   ════════════════════════════════════════════════════════════ */
function upd_apply(string $zipPath, bool $force = false): array {
  if (!is_file($zipPath))            throw new RuntimeException('فایل آپدیت دریافت نشد');
  if (!class_exists('ZipArchive'))   throw new RuntimeException('افزونهٔ zip روی هاست فعال نیست');
  $root = p_root();

  $zip = new ZipArchive();
  $st  = $zip->open($zipPath);
  if ($st !== true) throw new RuntimeException('فایل زیپ باز نشد — بسته ناقص یا خراب است (کد ' . (int)$st . ')');
  try {
    /* ۱) نام‌گذاری و گشت‌وسپر مسیر */
    $orig = []; $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $raw = (string)$zip->getNameIndex($i);
      $n   = upd_norm_name($raw);
      if (upd_unsafe_name($n)) throw new RuntimeException('بسته مسیرهای ناامن دارد — رد شد');
      if (str_ends_with($n, '/')) continue;                 // پوشه
      $orig[] = $raw; $names[$raw] = $n;
    }
    if (!$names) throw new RuntimeException('بسته خالی است');

    /* ۲) سنجاق هویت — باید بستهٔ GitiArts باشد */
    $markers = ['api/lib/paths.php', 'api/lib/db.php', 'panel/panel.php', 'login.php',
                'index.html', 'tenants/_template/api/lib/db.php', 'updates/manifest.json'];
    if (!array_reduce(array_values($names), fn($c, $n) => $c || in_array($n, $markers, true), false))
      throw new RuntimeException('این فایل شبیه بستهٔ GitiArts نیست — رد شد');

    /* ۳) ضد Downgrade — نسخهٔ بسته نباید قدیمی‌تر از نصب‌شده باشد */
    $newVer = upd_current_version();
    foreach ($names as $rawN => $n) {
      if ($n === 'updates/manifest.json') {
        $j = json_decode((string)stream_get_contents($zip->getStream($rawN)), true);
        if (is_array($j) && !empty($j['version'])) $newVer = (string)$j['version'];
        break;
      }
    }
    $cur = upd_current_version();
    if (!$force && $cur !== '' && version_compare($newVer, $cur, '<'))
      throw new RuntimeException("نسخهٔ بسته ({$newVer}) قدیمی‌تر از نسخهٔ نصب‌شده ({$cur}) است — نصب لغو شد");

    /* ۴) پشتیبان خودکار — اگر نشد، هیچ چیزی اعمال نمی‌شود */
    $msgs = [];
    $bak  = upd_backup('pre-update', $msgs);
    if ($bak === null) throw new RuntimeException('پشتیبان‌گیری ناموفق بود — نصب لغو شد: ' . implode(' | ', $msgs));

    /* ۵) تفکیک ورودی‌ها: قابل‌اعمال / حفاظت‌شده */
    $allowed = []; $skipped = [];
    foreach ($names as $rawN => $n) {
      if (upd_protected($n)) { $skipped[$n] = 'حفاظت‌شده'; continue; }
      $allowed[$rawN] = $n;
    }

    /* ۶) استخراج اتمی‌نما: temp در همان پوشه + rename */
    $applied = [];
    foreach ($allowed as $rawN => $n) {
      $dst = $root . '/' . $n;
      $d   = dirname($dst);
      if (!is_dir($d) && !mkdir($d, 0755, true))
        throw new RuntimeException("ساخت پوشه برای {$n} ممکن نشد");
      $src  = $zip->getStream($rawN);
      if ($src === false) throw new RuntimeException("خواندن {$n} از بسته ممکن نشد");
      $data = stream_get_contents($src);
      fclose($src);
      if ($data === false) throw new RuntimeException("خواندن محتوای {$n} ناموفق بود");
      $tmp = $dst . '.updtmp';
      if (file_put_contents($tmp, $data) === false || !rename($tmp, $dst))
        throw new RuntimeException("نوشتن {$n} ممکن نشد — دسترسی فایل‌ها را بررسی کنید");
      $applied[] = $n;
    }

    /* ۷) فایل‌های زائد — updates/remove.json داخل بسته */
    $deleted = [];
    foreach ($names as $rawN => $n) {
      if ($n !== 'updates/remove.json') continue;
      $j = json_decode((string)stream_get_contents($zip->getStream($rawN)), true);
      foreach ((is_array($j['remove'] ?? null) ? $j['remove'] : []) as $gone) {
        $g = upd_norm_name((string)$gone);
        if (upd_unsafe_name($g) || upd_protected($g)) continue;   // حفاظت همیشه اول
        $p = $root . '/' . $g;
        if (is_file($p)) { @unlink($p); $deleted[] = $g; }
      }
      break;
    }

    /* ۷٫۵) همگام‌سازی قالب با کافه‌های زنده — v5.0.5-test.2
       اگر بسته فایلی از tenants/_template/ آورده باشد، همان فایل به همهٔ کافه‌های
       ثبت‌شده هم کپی می‌شود (دادهٔ data/ و رسانهٔ uploads/ هر کافه حفاظت‌شده می‌ماند
       و قالب هرگز آن‌ها را ندارد). نتیجه در گزارش و لاگ می‌آید. */
    $syncTmpl = array_values(array_filter($applied,
      fn($n) => str_starts_with($n, 'tenants/_template/')));
    $sync = ['files' => 0, 'tenants' => []];
    if ($syncTmpl) {
      $reg = json_decode((string)@file_get_contents($root . '/tenants.json'), true);
      foreach (($reg['tenants'] ?? []) as $t) {
        $slug = (string)($t['slug'] ?? '');
        if ($slug === '' || $slug === '_template') continue;
        if (!is_dir($root . '/tenants/' . $slug)) continue;
        foreach ($syncTmpl as $n) {
          $rel = substr($n, strlen('tenants/_template/'));
          $src = $root . '/tenants/_template/' . $rel;
          $dst = $root . '/tenants/' . $slug . '/' . $rel;
          if (!is_file($src)) continue;
          $d = dirname($dst);
          if (!is_dir($d) && !mkdir($d, 0755, true)) continue;
          if (@copy($src, $dst)) $sync['files']++;
        }
        $sync['tenants'][] = $slug;
      }
    }

    $rep = ['ok' => true, 'version' => $newVer, 'previous' => $cur,
            'applied' => count($applied), 'applied_list' => array_slice($applied, 0, 30),
            'skipped' => $skipped, 'deleted' => $deleted, 'tenant_sync' => $sync,
            'backup' => $bak, 'pruned' => upd_prune_backups(5), 'notes' => $msgs];
    upd_log_add(['ev' => 'apply', 'from' => $cur, 'to' => $newVer,
                 'applied' => count($applied), 'skipped' => count($skipped),
                 'deleted' => count($deleted), 'tenant_sync' => $sync['files'],
                 'backup' => basename($bak)]);
    return $rep;
  } finally {
    $zip->close();
  }
}

/* ════════════════════════════════════════════════════════════
   اسکن سلامت — مقایسهٔ sha256 فایل‌ها با updates/checksums.json
   (هر بستهٔ آپدیت چک‌سام تازه‌ای می‌آورد و مبنا تازه می‌شود)
   ════════════════════════════════════════════════════════════ */
function upd_scan(): array {
  $f = p_root() . '/updates/checksums.json';
  if (!is_file($f)) throw new RuntimeException('فهرست چک‌سام یافت نشد — یک بار بستهٔ کامل نصب کنید');
  $j = json_decode((string)file_get_contents($f), true);
  if (!is_array($j) || empty($j['files']) || !is_array($j['files']))
    throw new RuntimeException('فایل چک‌سام نامعتبر است');
  $root = p_root();
  $mod = []; $miss = []; $okN = 0;
  foreach ($j['files'] as $rel => $h) {
    $p = $root . '/' . ltrim((string)$rel, '/');
    if (!is_file($p)) { $miss[] = (string)$rel; continue; }
    if (hash_file('sha256', $p) === $h) { $okN++; continue; }
    $mod[] = (string)$rel;
  }
  return ['version' => (string)($j['version'] ?? ''), 'generated' => (int)($j['generated'] ?? 0),
          'total' => count($j['files']), 'ok' => $okN,
          'modified' => $mod, 'missing' => $miss,
          'healthy' => !$mod && !$miss];
}
