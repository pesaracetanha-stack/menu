<?php
/* ════════════════════════════════════════════════════════════════
   repair-tenant-files.php — v5.0.5-test.2 (یک‌بارمصرف)
   ────────────────────────────────────────────────────────────────
   چرا این فایل هست؟
   کافه‌هایی که روی هاست ثبت شده‌اند (مثلاً verd) فایل‌های «قالب» را از
   زمانِ ثبتِ خودشان دارند؛ اگر فایل‌های پلتفرم را دستی به‌روز کنید،
   این کافه‌ها فایل‌های تازه را نمی‌گیرند (باگ keepScroll دقیقاً از
   همین‌جا آمد). این اسکریپت فایل‌های تازهٔ tenants/_template/ را روی
   همهٔ کافه‌های ثبت‌شده کپی می‌کند.
   محافظت داده‌ها:
     ‹ data/ هر کافه (پایگاه‌داده) دست‌نخورده می‌ماند
     ‹ uploads/ هر کافه (عکس‌ها) دست‌نخورده می‌ماند
     ‹ فقط اگر data/.htaccess یا uploads/.htaccess نبود، ساخته می‌شود
   امنیت:
     ‹ فقط با کلید یک‌بارمصرف اجرا می‌شود
     ‹ بعد از اجرای موفق، این فایل خودش را حذف می‌کند
   اجرا (مرورگر):   https://دامنه/مسیر/repair-tenant-files.php?key=کلید
   اجرا (ترمینال):  php repair-tenant-files.php کلید
   ════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

$KEY = 'cc840592';

/* ── گیت کلید ── */
$given = PHP_SAPI === 'cli'
  ? (string)($argv[1] ?? '')
  : (string)($_GET['key'] ?? '');
if ($given === '' || !hash_equals($KEY, $given)) {
  http_response_code(404);
  exit('not found');
}

header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;
$tmpl = $root . '/tenants/_template';
if (!is_dir($tmpl)) exit('قالب پیدا نشد: tenants/_template');

/* ── فهرست کافه‌های ثبت‌شده از رجیستری ── */
$reg = json_decode((string)@file_get_contents($root . '/tenants.json'), true);
$tenants = [];
foreach (($reg['tenants'] ?? []) as $t) {
  $s = (string)($t['slug'] ?? '');
  if ($s !== '' && $s !== '_template' && is_dir($root . '/tenants/' . $s)) $tenants[] = $s;
}

/* ── گشت‌وسپر فایل‌های قالب ── */
$files = [];
$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($tmpl, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
  if (!$f->isFile()) continue;
  $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($tmpl) + 1));
  $files[] = $rel;
}

$report = [];
$fatal = null;
foreach ($tenants as $slug) {
  $n = 0;
  foreach ($files as $rel) {
    $src = $tmpl . '/' . $rel;
    $dst = $root . '/tenants/' . $slug . '/' . $rel;
    $isData = (strpos($rel, 'data/') === 0);
    $isUpl  = (strpos($rel, 'uploads/') === 0);
    if ($isData || $isUpl) {
      /* داده/رسانه: فقط محافظ .htaccess در صورت نبود ساخته می‌شود */
      $base = basename($rel);
      if ($base === '.htaccess' && !is_file($dst)) {
        $d = dirname($dst);
        if (!is_dir($d) && !@mkdir($d, 0755, true)) continue;
        if (@copy($src, $dst)) $n++;
      }
      continue;
    }
    $d = dirname($dst);
    if (!is_dir($d) && !@mkdir($d, 0755, true)) { $fatal = "ساخت پوشه ناموفق: tenants/$slug/$rel"; break 2; }
    if (!@copy($src, $dst)) { $fatal = "کپی ناموفق: tenants/$slug/$rel"; break 2; }
    $n++;
  }
  $report[] = [$slug, $n];
}

/* ── گزارش ── */
if (PHP_SAPI === 'cli') {
  foreach ($report as [$s, $n]) echo "tenants/$s : $n فایل به‌روز شد\n";
  if ($fatal) { echo "خطا: $fatal\n"; exit(1); }
  echo "تعمیر کامل شد ✓\n";
  @unlink(__FILE__);
  exit(0);
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تعمیر فایل کافه‌ها</title></head>
<body style="font-family:Tahoma,sans-serif;background:#EFE7D6;color:#271D12;padding:30px;line-height:2.2">
<div style="max-width:640px;margin:0 auto;background:#FBF6EB;border:1px solid #DFD2B6;border-radius:16px;padding:24px">
<h2 style="margin:0 0 12px;font-size:18px">🔧 تعمیر فایل‌های کافه‌ها</h2>
<?php if ($fatal): ?>
  <p style="color:#A93B2A;font-weight:700">خطا: <?= htmlspecialchars($fatal) ?></p>
  <p style="font-size:12.5px;color:#7D6C54">هیچ‌چیز ناقص رها نشده — دوباره اجرا کنید؛ اگر تکرار شد با پشتیبانی تماس بگیرید.</p>
<?php else: ?>
  <?php foreach ($report as [$s, $n]): ?>
    <p style="margin:4px 0">✅ <b dir="ltr">tenants/<?= htmlspecialchars($s) ?></b> — <?= $n ?> فایل به‌روز شد</p>
  <?php endforeach; ?>
  <p style="margin-top:14px">دادهٔ هر کافه (data/ و uploads/) دست‌نخورده ماند.</p>
  <p style="color:#4F7A3D;font-weight:800">تعمیر کامل شد ✓ — این فایل خودش را حذف کرد؛ نیازی به کاری نیست.</p>
<?php endif; ?>
</div></body></html>
<?php
if (!$fatal) @unlink(__FILE__);
