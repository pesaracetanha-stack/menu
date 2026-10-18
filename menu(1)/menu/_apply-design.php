<?php
declare(strict_types=1);
/* _apply-design.php — separates index.html (customer) from design.php (admin editor)
   ⚠️ TEMPORARY — DELETE AFTER USE */

$root = __DIR__;
$ts = date('Ymd_His');
$backups = [];

function fail(string $msg, array $backups): void {
    echo "\n❌ ERROR: $msg\n🔄 Rolling back...\n";
    foreach ($backups as $b) if (is_file($b['bak'])) { copy($b['bak'], $b['orig']); echo "  ↺ ".basename($b['orig'])."\n"; }
    exit(1);
}
function backup(string $file, string $ts, array &$backups): void {
    $bak = $file . '.bak.design.' . $ts;
    if (!is_file($file)) fail("not found: $file", $backups);
    if (!copy($file, $bak)) fail("cannot backup: $file", $backups);
    $backups[] = ['orig' => $file, 'bak' => $bak];
    echo "📦 backup: " . basename($bak) . "\n";
}

echo "═══════════════════════════════════════════════════\n  Apply Design Separation Fix\n═══════════════════════════════════════════════════\n\n";

/* ─── 1. design.php (new) ─── */
echo "▶ [1/5] tenants/_template/design.php\n";
$designFile = $root . '/tenants/_template/design.php';
if (is_file($designFile)) {
    echo "  ⏭  already exists — skip\n";
} else {
    $content = <<<'DESIGNPHP'
<?php
declare(strict_types=1);
/* صفحه طراحی منو — فقط مدیر کافه. ورود و نقش سروری چک می‌شود. */
$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}
if (($_SESSION['tenant_role'] ?? 'admin') !== 'admin') {
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>دسترسی غیرمجاز</title><style>body{font-family:Vazirmatn,Tahoma,sans-serif;background:#EFE7D6;color:#271D12;text-align:center;padding:80px 20px}h1{font-size:22px;margin-bottom:12px}a{display:inline-block;margin-top:18px;padding:10px 22px;background:#B4531F;color:#fff;border-radius:10px;text-decoration:none}</style></head><body><h1>🚫 دسترسی فقط برای مدیر کافه</h1><p style="color:#7D6C54">این بخش مخصوص مدیر کافه است. اگر پرسنل هستید از پنل صندوق/آشپزخانه استفاده کنید.</p><a href="hub.php">بازگشت</a></body></html>';
  exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#B4531F">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
<title>طراحی منو — پنل کافه</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
</head>
<body>
<div id="errbox"></div>
<div id="app"></div>
<div id="toasts"></div>
<div id="modal-root"></div>
<div id="print-root"></div>
<input type="file" id="imgpick" accept="image/*" style="display:none">
<input type="file" id="excelpick" accept=".xlsx" style="display:none">
<script>window.__DESIGN_MODE__ = true; try { sessionStorage.setItem('tenAdmin','1'); } catch(e){}</script>
<script src="js/api.js"></script>
<script src="js/core.js"></script>
<script src="js/menu-render.js"></script>
<script src="js/ui-modals.js"></script>
<script src="js/ui-screens.js"></script>
<script src="js/customer.js"></script>
<script src="js/staff.js"></script>
<script src="js/receipt.js"></script>
<script src="js/actions.js"></script>
<script src="js/app.js"></script>
</body>
</html>
DESIGNPHP;
    if (file_put_contents($designFile, $content) === false) fail("cannot write design.php", $backups);
    echo "  ✅ design.php created\n";
}

/* ─── 2. js/app.js — 3 lines → __DESIGN_MODE__ ─── */
echo "\n▶ [2/5] tenants/_template/js/app.js\n";
$appJs = $root . '/tenants/_template/js/app.js';
backup($appJs, $ts, $backups);
$c = file_get_contents($appJs);
$old = "sessionStorage.getItem('tenAdmin') === '1'";
$new = "window.__DESIGN_MODE__ === true";
$count = substr_count($c, $old);
if ($count === 0) {
    if (strpos($c, '__DESIGN_MODE__') !== false) echo "  ⏭  already patched\n";
    else fail("app.js: pattern '$old' not found", $backups);
} else {
    $c = str_replace($old, $new, $c);
    file_put_contents($appJs, $c);
    echo "  ✅ replaced $count occurrence(s)\n";
}

/* ─── 3. hub.php — 3 refs ─── */
echo "\n▶ [3/5] tenants/_template/hub.php\n";
$hubFile = $root . '/tenants/_template/hub.php';
backup($hubFile, $ts, $backups);
$c = file_get_contents($hubFile);

// 3a. design card url
$f1 = "'url'=>'index.html','full'=>\$base.'/index.html'";
$r1 = "'url'=>'design.php','full'=>\$base.'/design.php'";
if (strpos($c, $r1) === false) {
    if (strpos($c, $f1) === false) fail("hub.php: design card url not found", $backups);
    $c = str_replace($f1, $r1, $c);
    echo "  ✅ design card url → design.php\n";
}

// 3b. staff filter — add design.php
$f2 = "['panel-admin.php?role=admin','index.html']";
$r2 = "['panel-admin.php?role=admin','design.php','index.html']";
if (strpos($c, $r2) === false) {
    if (strpos($c, $f2) === false) fail("hub.php: staff filter not found", $backups);
    $c = str_replace($f2, $r2, $c);
    echo "  ✅ staff filter includes design.php\n";
}

// 3c. empty CTA
$f3 = "'افزودن اولین دسته‌بندی منو', 'u' => 'index.html'";
$r3 = "'افزودن اولین دسته‌بندی منو', 'u' => 'design.php'";
if (strpos($c, $r3) === false) {
    if (strpos($c, $f3) === false) {
        echo "  ⚠  CTA pattern not found (may have whitespace variants) — manual check needed\n";
    } else {
        $c = str_replace($f3, $r3, $c);
        echo "  ✅ empty CTA → design.php\n";
    }
}
file_put_contents($hubFile, $c);

/* ─── 4. panel-admin.php — design button ─── */
echo "\n▶ [4/5] tenants/_template/panel-admin.php\n";
$paFile = $root . '/tenants/_template/panel-admin.php';
backup($paFile, $ts, $backups);
$c = file_get_contents($paFile);
$f = '<a href="index.html" class="btn btn-primary btn-big"><span class="icon">🎨</span> طراحی منو</a>';
$r = '<a href="design.php" class="btn btn-primary btn-big"><span class="icon">🎨</span> طراحی منو</a>';
if (strpos($c, $r) !== false) {
    echo "  ⏭  already patched\n";
} elseif (strpos($c, $f) === false) {
    echo "  ⚠  design button pattern not found (emoji/encoding variant?) — manual check needed\n";
} else {
    $c = str_replace($f, $r, $c);
    file_put_contents($paFile, $c);
    echo "  ✅ design button → design.php\n";
}

/* ─── 5. sidebar.php — design nav item ─── */
echo "\n▶ [5/5] tenants/_template/sidebar.php\n";
$sbFile = $root . '/tenants/_template/sidebar.php';
backup($sbFile, $ts, $backups);
$c = file_get_contents($sbFile);
$oldNav = '<li class="nav-item" onclick="window.location.href=\'index.html\'">';
$newNav = '<li class="nav-item" onclick="window.location.href=\'design.php\'">';
if (strpos($c, $newNav) !== false) {
    echo "  ⏭  already patched\n";
} elseif (strpos($c, $oldNav) === false) {
    echo "  ⚠  sidebar nav pattern not found — manual check needed\n";
} else {
    $c = str_replace($oldNav, $newNav, $c);
    file_put_contents($sbFile, $c);
    echo "  ✅ sidebar design nav → design.php\n";
}

/* ─── syntax check ─── */
echo "\n═══════════════════════════════════════════════════\n  Syntax check\n═══════════════════════════════════════════════════\n";
$toCheck = [$designFile, $hubFile, $paFile, $sbFile];
$failCount = 0;
foreach ($toCheck as $f) {
    $out = shell_exec('php -l ' . escapeshellarg($f) . ' 2>&1');
    if (strpos((string)$out, 'No syntax errors') === false) {
        echo "  ❌ " . basename($f) . ":\n     $out\n";
        $failCount++;
    } else {
        echo "  ✅ " . basename($f) . "\n";
    }
}

if ($failCount > 0) fail("$failCount file(s) failed syntax check — see above", $backups);

echo "\n═══════════════════════════════════════════════════\n  ✅ DONE\n═══════════════════════════════════════════════════\n";
echo "Backups: *.bak.design.$ts\n";
echo "Rollback: git restore .\n";
echo "\n⚠️  Apply to tenants/bil/ manually if that folder is still active.\n";
echo "⚠️  Delete this script: Remove-Item _apply-design.php\n";