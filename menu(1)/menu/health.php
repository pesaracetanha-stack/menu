<?php
/* ═══════════════════════════════════════════════════════════════
   v5.0.6: صفحه سلامت سیستم (Health Check)
   - چک: فضای دیسک، DB کافه‌ها، آخرین بکاپ
   - خروجی: HTML (پیش‌فرض) یا JSON (?format=json)
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/api/lib/paths.php';

$isJson = (($_GET['format'] ?? '') === 'json');
$started = microtime(true);

/* ─── ۱. فضای دیسک ─── */
$diskFree  = @disk_free_space(__DIR__);
$diskTotal = @disk_total_space(__DIR__);
$diskPct   = ($diskTotal > 0) ? round(($diskFree / $diskTotal) * 100, 1) : 0;
$diskFreeGB = round($diskFree / 1073741824, 2);

/* ─── ۲. کافه‌ها ─── */
$tenantsFile = p_root() . '/tenants.json';
$tenants = [];
$dbCheck = [];

if (is_file($tenantsFile)) {
    $j = json_decode((string)@file_get_contents($tenantsFile), true);
    if (is_array($j) && isset($j['tenants'])) {
        $tenants = $j['tenants'];
    }
}

$dbFail = 0;
$dbOK = 0;
$tenantsDir = p_root() . '/tenants';

foreach ($tenants as $t) {
    $slug = (string)($t['slug'] ?? '');
    if ($slug === '') continue;
    $dbf = $tenantsDir . '/' . $slug . '/data/db.sqlite';
    if (!is_file($dbf)) {
        $dbFail++;
        $dbCheck[] = ['slug' => $slug, 'ok' => false, 'err' => 'فایل DB نیست'];
        continue;
    }
    try {
        $pdo = new PDO('sqlite:' . $dbf);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $r = $pdo->query("PRAGMA integrity_check")->fetchColumn();
        if ($r === 'ok') {
            $dbOK++;
            $dbCheck[] = ['slug' => $slug, 'ok' => true, 'err' => null];
        } else {
            $dbFail++;
            $dbCheck[] = ['slug' => $slug, 'ok' => false, 'err' => (string)$r];
        }
    } catch (Throwable $e) {
        $dbFail++;
        $dbCheck[] = ['slug' => $slug, 'ok' => false, 'err' => 'خطا: ' . substr($e->getMessage(), 0, 60)];
    }
}

/* ─── ۳. آخرین بکاپ ─── */
$bakStateFile = p_root() . '/data/backup-state.json';
$bakLast = 0;
$bakAgo = 'هرگز';
if (is_file($bakStateFile)) {
    $bs = json_decode((string)@file_get_contents($bakStateFile), true);
    if (is_array($bs) && isset($bs['last_run'])) {
        $bakLast = (int)$bs['last_run'];
        $diff = time() - $bakLast;
        if ($diff < 60) $bakAgo = 'همین الان';
        elseif ($diff < 3600) $bakAgo = (int)($diff / 60) . ' دقیقه پیش';
        elseif ($diff < 86400) $bakAgo = (int)($diff / 3600) . ' ساعت پیش';
        else $bakAgo = (int)($diff / 86400) . ' روز پیش';
    }
}

/* ─── ۴. بررسی سرعت ─── */
$elapsed = round((microtime(true) - $started) * 1000, 1);

/* ─── ۵. وضعیت کلی ─── */
$status = 'healthy';
$issues = [];

if ($diskPct < 10) {
    $status = 'critical';
    $issues[] = "فضای دیسک بحرانی: تنها {$diskPct}% آزاد";
} elseif ($diskPct < 20) {
    $status = 'warning';
    $issues[] = "فضای دیسک کم: {$diskPct}% آزاد";
}

if ($dbFail > 0) {
    $status = ($status === 'critical' || $status === 'warning') ? $status : 'warning';
    $issues[] = "{$dbFail} کافه با مشکل DB";
}

$bakAgeHours = $bakLast > 0 ? (time() - $bakLast) / 3600 : 999;
if ($bakAgeHours > 48) {
    $status = ($status === 'critical') ? 'critical' : 'warning';
    $issues[] = "آخرین بکاپ {$bakAgo}";
}

/* ─── خروجی JSON ─── */
if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($status === 'critical' ? 503 : 200);
    echo json_encode([
        'status'   => $status,
        'time'     => date('c'),
        'elapsed_ms' => $elapsed,
        'disk'     => [
            'free_gb'  => $diskFreeGB,
            'total_gb' => round($diskTotal / 1073741824, 2),
            'free_pct' => $diskPct,
        ],
        'tenants'  => [
            'total'  => count($tenants),
            'db_ok'  => $dbOK,
            'db_fail'=> $dbFail,
        ],
        'backup'   => [
            'last_run'  => $bakLast,
            'last_ago'  => $bakAgo,
        ],
        'issues'   => $issues,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ─── خروجی HTML ─── */
$colorMap = ['healthy' => '#4F7A3D', 'warning' => '#B4801F', 'critical' => '#B4531F'];
$labelMap = ['healthy' => '✅ سالم', 'warning' => '⚠️ هشدار', 'critical' => '🚨 بحرانی'];
$color = $colorMap[$status] ?? '#7D6C54';
$label = $labelMap[$status] ?? $status;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>وضعیت سیستم — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;min-height:100dvh;padding:26px 14px}
.wrap{max-width:720px;margin:0 auto}
h1{font-size:22px;font-weight:800;margin-bottom:6px}
.sub{color:#7D6C54;font-size:13px;margin-bottom:22px}
.status{display:inline-flex;align-items:center;gap:10px;background:<?= $color ?>;color:#fff;padding:10px 20px;border-radius:12px;font-size:18px;font-weight:800;margin-bottom:20px}
.card{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:14px;padding:16px 18px;margin-bottom:12px}
.card h2{font-size:15px;font-weight:800;color:#271D12;margin-bottom:12px;padding-bottom:8px;border-bottom:1px dashed #DFD2B6}
.row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;font-size:14px}
.row + .row{border-top:1px solid #EFE7D6}
.big{font-size:20px;font-weight:800;color:#B4531F}
.big.ok{color:#4F7A3D}
.big.bad{color:#B4531F}
.mini{font-size:12px;color:#7D6C54}
.issue{background:#FBEAE4;border:1px solid #EFC5B6;color:#8A2C12;border-radius:10px;padding:10px 14px;margin-bottom:8px;font-size:13px;line-height:1.7}
.btn{display:inline-block;padding:9px 18px;background:#B4531F;color:#fff;border:0;border-radius:10px;font-family:inherit;font-size:14px;cursor:pointer;text-decoration:none;margin-top:8px}
.btn:hover{opacity:.9}
.btn.ghost{background:transparent;color:#271D12;border:1px solid #DFD2B6}
@media(max-width:520px){body{padding:18px 10px}}
</style>
</head>
<body>
<div class="wrap">
  <h1>🌿 وضعیت سیستم GitiArts</h1>
  <p class="sub">زمان چک: <?= date('Y-m-d H:i:s') ?> · سرعت پاسخ: <?= $elapsed ?> میلی‌ثانیه</p>

  <div class="status"><?= $label ?></div>

  <?php if (!empty($issues)): ?>
    <?php foreach ($issues as $iss): ?>
      <div class="issue">⚠️ <?= htmlspecialchars($iss) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card">
    <h2>💾 فضای دیسک</h2>
    <div class="row">
      <span>آزاد</span>
      <span class="big <?= $diskPct < 20 ? 'bad' : 'ok' ?>"><?= $diskFreeGB ?> GB</span>
    </div>
    <div class="row">
      <span>درصد آزاد</span>
      <span class="big <?= $diskPct < 20 ? 'bad' : 'ok' ?>"><?= $diskPct ?>%</span>
    </div>
  </div>

  <div class="card">
    <h2>🗄️ کافه‌ها (DB)</h2>
    <div class="row">
      <span>تعداد کل</span>
      <span class="big"><?= count($tenants) ?></span>
    </div>
    <div class="row">
      <span>DB سالم</span>
      <span class="big ok"><?= $dbOK ?></span>
    </div>
    <?php if ($dbFail > 0): ?>
      <div class="row">
        <span>DB مشکل‌دار</span>
        <span class="big bad"><?= $dbFail ?></span>
      </div>
      <?php foreach ($dbCheck as $d): ?>
        <?php if (!$d['ok']): ?>
          <div class="row">
            <span class="mini" dir="ltr"><?= htmlspecialchars($d['slug']) ?></span>
            <span class="mini" style="color:#B4531F"><?= htmlspecialchars($d['err'] ?? '') ?></span>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>💾 آخرین بکاپ</h2>
    <div class="row">
      <span>زمان</span>
      <span class="big <?= $bakAgeHours > 48 ? 'bad' : 'ok' ?>"><?= htmlspecialchars($bakAgo) ?></span>
    </div>
  </div>

  <div style="text-align:center;margin-top:20px">
    <a href="?format=json" class="btn ghost">📋 نسخه JSON</a>
    <a href="?" class="btn">🔄 رفرش</a>
  </div>

  <p class="mini" style="text-align:center;margin-top:20px">GitiArts Menu Platform — v5.0.6</p>
</div>
</body>
</html>