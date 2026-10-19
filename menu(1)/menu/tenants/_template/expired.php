<?php
/* ═══════════════════════════════════════════════════════════════
   صفحه اشتراک منقضی — v5.0.6
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

$slug = basename(__DIR__);

$brand = $slug;
$dbFile = __DIR__ . '/data/db.sqlite';
if (is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT v FROM doc WHERE scope = ? AND k = 'db' LIMIT 1");
        $stmt->execute([$slug]);
        $raw = $stmt->fetchColumn();
        if ($raw) {
            $db = json_decode($raw, true);
            if (is_array($db) && !empty($db['menu']['brand']['name'])) {
                $brand = (string)$db['menu']['brand']['name'];
            }
        }
    } catch (Throwable $e) { /* بی‌صدا */ }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>اشتراک منقضی — <?= htmlspecialchars($brand) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#FBF6EB;border:2px solid #B4531F;border-radius:18px;padding:32px 28px;max-width:480px;width:100%;text-align:center;box-shadow:0 6px 24px rgba(180,83,31,.1)}
.icon{font-size:52px;margin-bottom:12px}
h1{font-size:22px;font-weight:800;color:#8A2C12;margin-bottom:10px}
.cafe{font-size:14px;color:#7D6C54;margin-bottom:22px}
p{font-size:14px;line-height:2;color:#4A3D2C;margin-bottom:16px}
.actions{display:flex;flex-direction:column;gap:10px;margin-top:22px}
.btn{display:block;padding:13px 20px;border-radius:12px;text-decoration:none;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer;border:0}
.btn.primary{background:#B4531F;color:#fff}
.btn.primary:hover{background:#9A4517}
.btn.ghost{background:transparent;color:#271D12;border:1px solid #DFD2B6}
.btn.ghost:hover{background:#F2EADA}
.note{font-size:12px;color:#7D6C54;margin-top:20px;line-height:1.9}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🔒</div>
  <h1>اشتراک شما منقضی شده است</h1>
  <div class="cafe"><?= htmlspecialchars($brand) ?></div>
  <p>
    دسترسی به پنل مدیریت و امکانات کافه تا زمان تمدید اشتراک موقتاً غیرفعال شده است.
    داده‌های شما کاملاً محفوظ هستند و پس از تمدید، بلافاصله در دسترس خواهند بود.
  </p>
  <div class="actions">
    <a href="renew.php" class="btn" style="display:block; width:100%; text-align:center; background:#4F7A3D; color:#fff; padding:16px; border-radius:12px; text-decoration:none; font-weight:800; font-size:16px; margin-bottom:12px; box-shadow:0 4px 12px rgba(79,122,61,0.2); border:0;">💳 تمدید خودکار اشتراک (پرداخت آنلاین)</a>
<a class="btn primary" href="tel:+989198345661">📞 تماس با پشتیبانی</a>
    <a class="btn ghost" href="https://t.me/+989198345661" target="_blank" rel="noopener">💬 پیام در تلگرام</a>
    <a class="btn ghost" href="index.html">👁 مشاهده منوی مشتری</a>
  </div>
  <div class="note">
    برای تمدید سریع، کد اختصاصی کافه خود را هنگام تماس ذکر کنید.<br>
    <b dir="ltr"><?= htmlspecialchars($slug) ?></b>
  </div>
</div>
</body>
</html>