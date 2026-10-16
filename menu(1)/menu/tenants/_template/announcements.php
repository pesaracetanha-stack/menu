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
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;padding:20px 14px;min-height:100dvh}
.wrap{max-width:760px;margin:0 auto}
.head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.head h1{font-size:20px;font-weight:800}
.head a{color:#7D6C54;text-decoration:none;font-size:14px}
.card{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:14px;padding:18px 20px;margin-bottom:14px}
.anno{background:#fff;border:1px solid #DFD2B6;border-radius:14px;padding:16px;margin-bottom:12px;position:relative;overflow:hidden}
.anno.unread{border-color:#B4531F;box-shadow:0 0 0 3px rgba(180,83,31,.08)}
.anno.unread::before{content:'● جدید';position:absolute;top:10px;left:12px;background:#B4531F;color:#fff;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:800}
.anno img{max-width:100%;border-radius:10px;margin-bottom:12px;display:block}
.anno h3{font-size:15px;font-weight:800;margin-bottom:8px}
.anno p{font-size:14px;line-height:1.9;color:#4A3D2C;white-space:pre-wrap;word-wrap:break-word;margin-bottom:10px}
.anno-meta{font-size:11px;color:#7D6C54;display:flex;gap:12px;flex-wrap:wrap}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;margin-right:6px}
.empty{padding:40px;text-align:center;color:#7D6C54;font-size:14px;line-height:2}
.empty-icon{font-size:48px;display:block;margin-bottom:10px;opacity:.5}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1>📢 اعلانات</h1>
      <p style="font-size:13px;color:#7D6C54;margin-top:4px"><?= htmlspecialchars($brand) ?></p>
    </div>
    <a href="hub.php">‹ بازگشت به پنل</a>
  </div>

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
</body>
</html>