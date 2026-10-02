<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/lib/platform-auth.php';
require_once __DIR__ . '/../api/lib/platform-admin.php'; /* v5.0.5-test.4: لاگ رویدادهای ورود */
sec_session();

 $msg = ''; $ok = '';
/* v5.0.5-test.3: ورود دومرحله‌ای — گام ۱ رمز، گام ۲ کد یکبارمصرف پیامکی */
 $showOtp = !empty($_SESSION['plat_2fa']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $msg = 'نشست منقضی شده — دوباره تلاش کنید';
  } elseif ($act === 'setup') {
    $msg = pauth_setup($_POST['key'] ?? '', $_POST['p1'] ?? '', $_POST['p2'] ?? '');
    if ($msg === '') { plat_log('setup'); $_SESSION['plat'] = true; header('Location: panel.php'); exit; }
  } elseif ($act === 'login') {
    $msg = pauth_login($_POST['pass'] ?? '');
    if ($msg === '') {
      plat_log('login');
      if (pauth_otp_enabled()) {
        $_SESSION['plat_2fa'] = time();
        $m2 = pauth_otp_send();
        if ($m2 !== '') { $msg = $m2; unset($_SESSION['plat_2fa']); }
        else $showOtp = true;
      } else { header('Location: panel.php'); exit; }
    } else plat_log('login_fail');
  } elseif ($act === 'otp' && $showOtp) {
    if (empty($_SESSION['plat_2fa']) || time() - (int)$_SESSION['plat_2fa'] > 600) {
      $msg = 'زمان ورود منقضی شد — از ابتدا وارد شوید';
      unset($_SESSION['plat_2fa']); $showOtp = false;
    } else {
      $msg = pauth_otp_verify($_POST['code'] ?? '');
      if ($msg === '') { plat_log('otp_ok'); header('Location: panel.php'); exit; }
      $showOtp = !empty($_SESSION['plat_2fa']);
    }
  } elseif ($act === 'otp-resend' && $showOtp) {
    $msg = pauth_otp_send();
    if ($msg === '') $ok = 'کد تازه ارسال شد';
  }
}
 $cfg = require __DIR__ . '/../config.php';
 $setup = !pauth_configured();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل GitiArts — ورود</title>
<link rel="stylesheet" href="platform.css">
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="logo">🌿 GitiArts</div>
    <?php if ($setup): ?>
      <h1>راه‌اندازی پنل مدیریت</h1>
      <p class="sub">تنها بار اول است — رمز مدیر پلتفرم را بسازید. این رمز هش‌شده ذخیره می‌شود.</p>
      <?php if ($msg): ?><p class="err"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="setup">
        <label>کلید نصب <small>(از config.php — مقدار setup_key)</small>
          <input type="text" name="key" required autocomplete="off"></label>
        <label>رمز عبور جدید
          <input type="password" name="p1" required autocomplete="new-password"></label>
        <label>تکرار رمز عبور
          <input type="password" name="p2" required autocomplete="new-password"></label>
        <button>راه‌اندازی و ورود</button>
      </form>
      <p class="hint">حداقل ۸ کاراکتر · حداقل یک حرف و یک عدد</p>
    <?php elseif ($showOtp): ?>
      <h1>تأیید دومرحله‌ای</h1>
      <p class="sub">کد ۶ رقمی به موبایل مدیر <b dir="ltr"><?= htmlspecialchars(pauth_otp_mobile_masked()) ?></b> پیامک شد.</p>
      <?php if ($msg): ?><p class="err"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($ok): ?><p class="ok"><?= htmlspecialchars($ok) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="otp">
        <label>کد یکبارمصرف
          <input type="text" name="code" required autocomplete="one-time-code" inputmode="numeric"
                 pattern="\d{6}" maxlength="6" placeholder="------"
                 style="letter-spacing:8px;text-align:center;font-size:20px" autofocus></label>
        <button>ورود به پنل</button>
      </form>
      <form method="post" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="otp-resend">
        <button class="btn2">ارسال مجدد کد</button>
      </form>
      <p class="hint">کد ۵ دقیقه اعتبار دارد · حداکثر ۵ تلاش</p>
    <?php else: ?>
      <h1>ورود به پنل مدیریت</h1>
      <?php if ($msg): ?><p class="err"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="login">
        <label>رمز عبور مدیر
          <input type="password" name="pass" required autocomplete="current-password" autofocus></label>
        <button>ورود</button>
      </form>
      <?php if (pauth_otp_enabled()): ?>
        <p class="hint">🔒 پس از رمز، کد تأیید پیامکی هم پرسیده می‌شود.</p>
      <?php endif; ?>
    <?php endif; ?>
    <p class="foot">سِرو · قدرت‌گرفته از GitiArts</p>
  </div>
</div>
</body>
</html>
