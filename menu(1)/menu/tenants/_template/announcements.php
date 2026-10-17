<?php
declare(strict_types=1);
/* تاریخچه اعلانات — کافه */

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

$user = (string)($_SESSION['tenant_user'] ?? 'مدیر');

$brand = $slug;
try {
  $dbf = __DIR__ . '/data/db.sqlite';
  if (is_file($dbf)) {
    $pdo = new PDO('sqlite:' . $dbf);
    $stmt = $pdo->prepare("SELECT v FROM doc WHERE scope=? AND k='db' LIMIT 1");
    $stmt->execute([$slug]);
    $raw = $stmt->fetchColumn();
    if ($raw) { $j = json_decode($raw, true); if (!empty($j['menu']['brand']['name'])) $brand = (string)$j['menu']['brand']['name']; }
  }
} catch (Throwable $e) {}

$f = dirname(__DIR__, 2) . '/data/announcements.json';
$items = [];
$readByMe = [];
if (is_file($f)) {
  $d = json_decode((string)@file_get_contents($f), true);
  if (is_array($d) && isset($d['items'])) {
    $now = time();
    foreach ($d['items'] as $it) {
      if (empty($it['active'])) continue;
      if (!empty($it['expires']) && (int)$it['expires'] < $now) continue;
      $t = $it['target'] ?? 'all';
      if ($t !== 'all' && is_array($t) && !in_array($slug, $t, true)) continue;
      $items[] = $it;
      if (in_array($slug, $it['read_by'] ?? [], true)) $readByMe[(int)$it['id']] = true;
    }
  }
}
usort($items, fn($a, $b) => (int)$b['created'] <=> (int)$a['created']);

$typeFa = ['info'=>'🔵 اطلاعیه', 'success'=>'🟢 قابلیت جدید', 'warning'=>'🟡 هشدار', 'danger'=>'🔴 مهم'];
$typeColor = ['info'=>'#4A6FA5', 'success'=>'#4F7A3D', 'warning'=>'#B4801F', 'danger'=>'#B4531F'];

function ftimeFull(int $ts): string {
  if ($ts <= 0) return '—';
  $gy = (int)date('Y', $ts); $gm = (int)date('n', $ts); $gd = (int)date('j', $ts);
  $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
  $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
  $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
  $jy = -1595 + (33 * ((int)($days / 12053)));
  $days %= 12053;
  $jy += 4 * ((int)($days / 1461));
  $days %= 1461;
  if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
  if ($days < 186) { $jm = 1 + (int)($days / 31); $jd = 1 + ($days % 31); }
  else { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
  return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, date('H:i', $ts));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>اعلانات — <?= htmlspecialchars($brand) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ── پالت رنگی اختصاصی شما ── */
:root {
  --bg-canvas: #EFE7D6; --bg-surface: #FBF6EB; --bg-surface-hover: #F2EADA;
  --bg-sidebar: #271D12; --border-subtle: #DFD2B6; --text-primary: #271D12;
  --text-secondary: #7D6C54; --text-inverse: #FBF6EB;
  --accent-500: #B4531F; --accent-600: #9A4517;
  --success-500: #4F7A3D; --warning-500: #B07C1F; --danger-500: #A93B2A;
  --radius-md: 10px; --radius-lg: 16px; --shadow-sm: 0 1px 3px rgba(39, 29, 18, 0.05);
  --font-family: 'Vazirmatn', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); background: var(--bg-canvas); color: var(--text-primary); display: flex; min-height: 100vh; line-height: 1.8; }

/* ── Layout ── */
.app-layout { display: flex; width: 100%; }
.main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; }
.topbar { height: 64px; background: var(--bg-surface); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; }
.content-area { padding: 24px; flex: 1; overflow-y: auto; }

/* ── Sidebar ── */
.sidebar { width: 260px; background: var(--bg-sidebar); color: var(--text-inverse); display: flex; flex-direction: column; flex-shrink: 0; }
.brand { padding: 24px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--bg-surface); }
.nav-menu { list-style: none; padding: 16px 0; flex: 1; }
.nav-item { padding: 12px 24px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background 0.2s; color: #CBBB99; font-weight: 500; text-decoration: none; position: relative; }
.nav-item:hover { background: rgba(255,255,255,0.05); color: var(--bg-surface); }
.nav-item.active { background: var(--accent-500); color: white; border-right: 4px solid #E59A6B; }
.nav-item .icon { font-size: 18px; }
.nav-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 16px 24px; }
.sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); }
.btn-outline-sidebar { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: var(--radius-md); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: white; width: 100%; transition: all 0.2s; text-decoration:none; }
.btn-outline-sidebar:hover { background: rgba(255,255,255,0.1); }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.page-title { font-size: 18px; font-weight: 700; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

/* ── Announcements ── */
.anno { background: #fff; border: 1px solid var(--border-subtle); border-radius: 14px; padding: 20px; margin-bottom: 16px; position: relative; overflow: hidden; transition: box-shadow 0.2s; }
.anno:hover { box-shadow: var(--shadow-sm); }
.anno.unread { border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(180,83,31,.08); }
.anno.unread::before { content: '● جدید'; position: absolute; top: 16px; left: 16px; background: var(--accent-500); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 10px; font-weight: 800; }
.anno img { max-width: 100%; border-radius: 10px; margin-bottom: 16px; display: block; }
.anno h3 { font-size: 16px; font-weight: 800; margin-bottom: 10px; color: var(--text-primary); }
.anno p { font-size: 14px; line-height: 1.9; color: #4A3D2C; white-space: pre-wrap; word-wrap: break-word; margin-bottom: 12px; }
.anno-meta { font-size: 12px; color: var(--text-secondary); display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
.pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff; margin-left: 8px; }
.empty { padding: 60px 20px; text-align: center; color: var(--text-secondary); font-size: 14px; line-height: 2; }
.empty-icon { font-size: 56px; display: block; margin-bottom: 12px; opacity: .6; }
</style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">📢 اعلانات</div>
      <div class="user-badge"><?= htmlspecialchars($user) ?></div>
    </header>

    <div class="content-area">
      <?php if (empty($items)): ?>
        <div class="card">
          <div class="empty">
            <span class="empty-icon">📭</span>
            هنوز اعلانی از طرف تیم GitiArts دریافت نکرده‌اید
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($items as $it): ?>
          <?php $unread = empty($readByMe[(int)$it['id']]); ?>
          <div class="anno <?= $unread ? 'unread' : '' ?>">
            <?php if (!empty($it['image'])): ?>
              <img src="../../data/announcement-images/<?= htmlspecialchars($it['image']) ?>" alt="">
            <?php endif; ?>
            <h3>
              <span class="pill" style="background:<?= $typeColor[$it['type']] ?? '#7D6C54' ?>"><?= $typeFa[$it['type']] ?? $it['type'] ?></span>
              <?= htmlspecialchars($it['title']) ?>
            </h3>
            <p><?= htmlspecialchars($it['body']) ?></p>
            <div class="anno-meta">
              <span>🕐 <?= ftimeFull((int)$it['created']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>