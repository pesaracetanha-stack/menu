<?php
declare(strict_types=1);
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

$ref = $_GET['ref'] ?? '';
$root = dirname(__DIR__, 2);
require_once $root . '/api/lib/class-gstore.php';

// دریافت اطلاعات لایسنس برای نمایش تاریخ جدید
$store = GStore::for(__DIR__);
$db = $store->get();
$paidUntil = (float)($db['license']['paid_until'] ?? 0);
$plan = $db['license']['plan'] ?? 'basic';

// دریافت اطلاعات تراکنش از دیتای پلتفرم
$txFile = $root . '/data/transactions.json';
$amount = 0;
if (is_file($txFile)) {
    $txData = json_decode(file_get_contents($txFile), true);
    foreach ($txData['items'] ?? [] as $tx) {
        if (($tx['ref_id'] ?? '') === $ref) {
            $amount = $tx['amount_toman'] ?? 0;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پرداخت موفق — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:20px;padding:40px 30px;max-width:480px;width:100%;text-align:center;box-shadow:0 12px 40px rgba(139,115,85,.15)}
.tick{width:80px;height:80px;margin:0 auto 20px;background:#E8F5E9;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#4CAF50}
h1{font-size:22px;font-weight:900;color:#271D12;margin-bottom:8px}
p.sub{color:#7D6C54;font-size:14px;margin-bottom:24px}
.details{background:#fff;border:1px solid #EFE7D6;border-radius:12px;padding:16px;margin-bottom:24px;text-align:right}
.row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;border-bottom:1px dashed #EFE7D6}
.row:last-child{border-bottom:0}
.row span{color:#7D6C54}
.row b{color:#271D12}
.btn{display:inline-block;width:100%;background:#4F7A3D;color:#fff;border:0;padding:14px;border-radius:12px;font-family:inherit;font-size:16px;font-weight:800;text-decoration:none;cursor:pointer;transition:.2s}
.btn:hover{opacity:.9}
</style>
</head>
<body>
<div class="card">
  <div class="tick">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
  </div>
  <h1>پرداخت با موفقیت انجام شد</h1>
  <p class="sub">اشتراک شما با موفقیت تمدید و فعال شد.</p>
  
  <div class="details">
    <?php if ($ref): ?>
    <div class="row"><span>شماره پیگیری</span><b><?= htmlspecialchars($ref) ?></b></div>
    <?php endif; ?>
    <?php if ($amount > 0): ?>
    <div class="row"><span>مبلغ پرداخت شده</span><b><?= number_format($amount) ?> تومان</b></div>
    <?php endif; ?>
    <div class="row"><span>پلن فعال</span><b><?= $plan === 'pro' ? 'حرفه‌ای 👑' : ($plan === 'basic' ? 'پایه ⚡' : 'رایگان 🌱') ?></b></div>
    <div class="row"><span>تاریخ انقضای جدید</span><b id="expiry-date" data-ts="<?= $paidUntil ?>">در حال محاسبه...</b></div>
  </div>

  <a href="hub.php" class="btn">بازگشت به داشبورد</a>
</div>

<script>
// فرمت کردن تاریخ جلالی برای نمایش بهتر
const ts = parseInt(document.getElementById('expiry-date').dataset.ts);
if (ts > 0) {
    const d = new Date(ts);
    document.getElementById('expiry-date').textContent = new Intl.DateTimeFormat('fa-IR', {year:'numeric', month:'long', day:'numeric'}).format(d);
}
</script>
</body>
</html>