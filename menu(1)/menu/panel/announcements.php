<?php
declare(strict_types=1);
/* اعلانات — پنل ادمین */

require_once __DIR__ . '/../api/lib/platform-auth.php';
require_once __DIR__ . '/../api/lib/platform-admin.php';
require_once __DIR__ . '/../api/lib/paths.php';
require_once __DIR__ . '/../bootstrap-i18n.php';

sec_session();
pauth_require();

$root = dirname(__DIR__);
$annoFile = $root . '/data/announcements.json';
$imgDir = $root . '/data/announcement-images';

function an_load(string $f): array {
  if (!is_file($f)) return ['nextId'=>1, 'items'=>[]];
  $j = json_decode((string)@file_get_contents($f), true);
  if (!is_array($j)) return ['nextId'=>1, 'items'=>[]];
  if (!isset($j['items'])) $j['items'] = [];
  if (!isset($j['nextId'])) $j['nextId'] = 1;
  return $j;
}
function an_save(string $f, array $d): void {
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

require_once __DIR__ . '/../api/lib/atomic-json.php';

/* ATOMIC_RMW: خواندن/تغییر/نوشتن اتمیک با flock */
function an_update(string $f, callable $fn): array {
  return ajl_update($f, function(array $d) use ($fn) {
    if (!isset($d['items'])) $d['items'] = [];
    if (!isset($d['nextId'])) $d['nextId'] = 1;
    $result = $fn($d);
    return is_array($result) ? $result : $d;
  });
}
function an_upload(array $file, string $dir): ?string {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] <= 0 || $file['size'] > 3 * 1024 * 1024) return null;
  $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
  $mime = mime_content_type($file['tmp_name']) ?: '';
  if (!isset($allowed[$mime])) return null;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;
  return $name;
}

$msg = ''; $err = '';
$act = (string)($_POST['act'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $err = 'نشست منقضی شده';
  } elseif ($act === 'create') {
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $type = (string)($_POST['type'] ?? 'info');
    $expiresAt = (string)($_POST['expires_at'] ?? '');
    $target = (string)($_POST['target'] ?? 'all');

    if (mb_strlen($title) < 3) $err = 'عنوان حداقل ۳ حرف';
    elseif (mb_strlen($title) > 120) $err = 'عنوان حداکثر ۱۲۰ حرف';
    elseif (mb_strlen($body) < 5) $err = 'متن حداقل ۵ حرف';
    elseif (mb_strlen($body) > 3000) $err = 'متن حداکثر ۳۰۰۰ حرف';
    else {
      if (!in_array($type, ['info','success','warning','danger'], true)) $type = 'info';
      $imgName = null;
      if (!empty($_FILES['image']['name'])) {
        $imgName = an_upload($_FILES['image'], $imgDir);
        if ($imgName === null) $err = 'تصویر نامعتبر یا بزرگ‌تر از ۳ مگابایت';
      }
      if ($err === '') {
        $exp = 0;
        if ($expiresAt !== '') {
          $ts = strtotime($expiresAt);
          if ($ts !== false && $ts > time()) $exp = $ts;
        }
        $newAnnoId = 0;
        an_update($annoFile, function(array $d) use (&$newAnnoId, $title, $body, $type, $imgName, $exp, $target) {
          $newAnnoId = (int)($d['nextId'] ?? 1);
          $d['nextId'] = $newAnnoId + 1;
          $d['items'][] = [
            'id' => $newAnnoId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'image' => $imgName,
            'created' => time(),
            'expires' => $exp,
            'active' => true,
            'target' => $target === 'all' ? 'all' : [],
            'read_by' => [],
          ];
          return $d;
        });
        $msg = "✅ اعلان #$newAnnoId ثبت شد";
        plat_log('announcement_create', ['id' => $newAnnoId]);
      }
    }
  } elseif ($act === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $newToggleState = null;
      an_update($annoFile, function(array $d) use ($id, &$newToggleState) {
        foreach ($d['items'] as $i => $it) {
          if ((int)$it['id'] === $id) {
            $d['items'][$i]['active'] = empty($it['active']);
            $newToggleState = !empty($d['items'][$i]['active']);
            break;
          }
        }
        return $d;
      });
      if ($newToggleState !== null) {
        $msg = $newToggleState ? '✅ فعال شد' : '⏸ غیرفعال شد';
      }
    }
  } elseif ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $removedAnno = null;
      an_update($annoFile, function(array $d) use ($id, &$removedAnno) {
        $newItems = [];
        foreach ($d['items'] as $it) {
          if ((int)$it['id'] === $id) { $removedAnno = $it; continue; }
          $newItems[] = $it;
        }
        $d['items'] = $newItems;
        return $d;
      });
      if ($removedAnno) {
        if (!empty($removedAnno['image'])) @unlink($imgDir . '/' . $removedAnno['image']);
        $msg = '🗑 حذف شد';
        plat_log('announcement_delete', ['id' => $id]);
      }
    }
  }
}

$d = an_load($annoFile);
$items = $d['items'];
usort($items, fn($a, $b) => (int)$b['created'] <=> (int)$a['created']);

$totalCafes = 0;
try { $totalCafes = count(plat_list()); } catch (Throwable $e) {}

$typeFa = ['info'=>'🔵 اطلاعیه', 'success'=>'🟢 موفقیت', 'warning'=>'🟡 هشدار', 'danger'=>'🔴 مهم'];
$typeColor = ['info'=>'#4A6FA5', 'success'=>'#4F7A3D', 'warning'=>'#B4801F', 'danger'=>'#B4531F'];

function ftime(int $ts): string {
  if ($ts <= 0) return '—';
  $diff = time() - $ts;
  if ($diff < 60) return 'همین الان';
  if ($diff < 3600) return (int)($diff/60) . 'د پیش';
  if ($diff < 86400) return (int)($diff/3600) . 'س پیش';
  return (int)($diff/86400) . 'روز پیش';
}
function ftimeFull(int $ts): string { return $ts > 0 ? jdate(date('Y-m-d H:i:s', $ts), 'Y/m/d H:i') : '—'; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>اعلانات — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;padding:20px 14px;min-height:100dvh}
.wrap{max-width:960px;margin:0 auto}
.head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.head h1{font-size:20px;font-weight:800}
.head a{color:#7D6C54;text-decoration:none;font-size:14px}
.card{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:14px;padding:18px 20px;margin-bottom:14px}
.card h2{font-size:15px;font-weight:800;margin-bottom:14px;padding-bottom:8px;border-bottom:1px dashed #DFD2B6}
label{display:block;margin-bottom:12px;font-size:14px}
label span{display:block;margin-bottom:5px;font-weight:700}
input[type=text],input[type=datetime-local],textarea,select{width:100%;padding:10px 12px;border:1px solid #DFD2B6;border-radius:10px;font-family:inherit;font-size:14px;background:#fff;color:#271D12}
input:focus,textarea:focus,select:focus{outline:none;border-color:#B4531F;box-shadow:0 0 0 3px rgba(180,83,31,.1)}
textarea{resize:vertical;min-height:100px}
.file-input{padding:8px;border:1.5px dashed #DFD2B6;border-radius:10px;background:#FBF6EB;cursor:pointer;font-size:13px;width:100%}
.file-input::file-selector-button{padding:6px 14px;background:#B4531F;color:#fff;border:0;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:700;margin-left:8px}
.btn{display:inline-block;padding:11px 22px;background:#B4531F;color:#fff;border:0;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{opacity:.9}
.btn.ghost{background:transparent;color:#271D12;border:1px solid #DFD2B6}
.btn.danger{background:#8A2C12}
.msg{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px}
.msg.ok{background:#E8F4E3;border:1px solid #BFDCA8;color:#3D6F2B}
.msg.err{background:#FBEAE4;border:1px solid #EFC5B6;color:#8A2C12}
.anno{background:#fff;border:1px solid #DFD2B6;border-radius:12px;padding:14px 16px;margin-bottom:10px;display:flex;gap:14px;align-items:flex-start}
.anno-img{width:80px;height:80px;border-radius:10px;object-fit:cover;flex:none}
.anno-body{flex:1;min-width:0}
.anno-body h3{font-size:15px;font-weight:800;margin-bottom:6px}
.anno-body p{font-size:13px;color:#4A3D2C;line-height:1.8;white-space:pre-wrap;word-wrap:break-word;margin-bottom:8px}
.anno-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:11px;color:#7D6C54}
.anno-actions{display:flex;gap:6px;flex-direction:column;flex:none}
.anno-actions button{padding:6px 12px;border-radius:8px;font-family:inherit;font-size:12px;cursor:pointer;border:1px solid #DFD2B6;background:#FBF6EB;white-space:nowrap}
.anno-actions button:hover{background:#F2EADA}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff}
.off{opacity:.5}
.progress{margin-top:6px;font-size:11px;color:#7D6C54}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1>📢 اعلانات</h1>
      <p style="font-size:13px;color:#7D6C54;margin-top:4px">ارسال اطلاعیه به همه کافه‌ها</p>
    </div>
    <a href="panel.php">‹ بازگشت</a>
  </div>

  <?php if ($msg): ?><div class="msg ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card">
    <h2>✏️ اعلان جدید</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <label><span>عنوان</span>
        <input type="text" name="title" required maxlength="120" placeholder="مثلاً: قابلیت جدید – QR کد برای هر میز">
      </label>
      <label><span>متن اعلان</span>
        <textarea name="body" required maxlength="3000" placeholder="متن کامل اعلان..."></textarea>
      </label>
      <label><span>تصویر (اختیاری — حداکثر ۳ مگابایت)</span>
        <input class="file-input" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <label><span>نوع</span>
          <select name="type">
            <option value="info">🔵 اطلاعیه</option>
            <option value="success">🟢 موفقیت / قابلیت جدید</option>
            <option value="warning">🟡 هشدار</option>
            <option value="danger">🔴 مهم</option>
          </select>
        </label>
        <label><span>انقضا (اختیاری)</span>
          <input type="datetime-local" name="expires_at">
        </label>
      </div>
      <button class="btn" type="submit">📤 ارسال به همه کافه‌ها</button>
    </form>
  </div>

  <div class="card">
    <h2>📋 همه اعلانات (<?= pnum(count($items)) ?>)</h2>
    <?php if (empty($items)): ?>
      <p style="text-align:center;padding:20px;color:#7D6C54">هنوز اعلانی ثبت نشده</p>
    <?php else: ?>
      <?php foreach ($items as $it): ?>
        <?php
          $readCount = count($it['read_by'] ?? []);
          $percent = $totalCafes > 0 ? round(($readCount / $totalCafes) * 100) : 0;
          $isExpired = !empty($it['expires']) && (int)$it['expires'] < time();
        ?>
        <div class="anno <?= empty($it['active']) || $isExpired ? 'off' : '' ?>">
          <?php if (!empty($it['image'])): ?>
            <img class="anno-img" src="../data/announcement-images/<?= htmlspecialchars($it['image']) ?>" alt="">
          <?php endif; ?>
          <div class="anno-body">
            <h3>
              <span class="pill" style="background:<?= $typeColor[$it['type']] ?? '#7D6C54' ?>"><?= $typeFa[$it['type']] ?? $it['type'] ?></span>
              <?= htmlspecialchars($it['title']) ?>
            </h3>
            <p><?= htmlspecialchars(mb_substr($it['body'], 0, 200)) ?><?= mb_strlen($it['body']) > 200 ? '...' : '' ?></p>
            <div class="anno-meta">
              <span>🕐 <?= ftimeFull((int)$it['created']) ?></span>
              <?php if (!empty($it['expires'])): ?>
                <span>⏰ انقضا: <?= ftimeFull((int)$it['expires']) ?></span>
              <?php endif; ?>
              <?php if (empty($it['active'])): ?><span style="color:#B4531F">⏸ غیرفعال</span><?php endif; ?>
              <?php if ($isExpired): ?><span style="color:#B4531F">⏰ منقضی</span><?php endif; ?>
            </div>
            <div class="progress">
              📊 خوانده‌شده توسط <?= pnum($readCount) ?> از <?= pnum($totalCafes) ?> کافه (<?= pnum($percent) ?>%)
            </div>
          </div>
          <div class="anno-actions">
            <form method="post" style="margin:0">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button type="submit"><?= empty($it['active']) ? '▶ فعال' : '⏸ غیرفعال' ?></button>
            </form>
            <form method="post" style="margin:0" onsubmit="return confirm('حذف شود؟')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button type="submit" class="danger" style="color:#fff;background:#8A2C12;border-color:#8A2C12">🗑</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>