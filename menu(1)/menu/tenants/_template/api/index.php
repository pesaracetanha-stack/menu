<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/export-client.php';
require_once __DIR__ . '/modules/data.php';
require_once __DIR__ . '/modules/orders.php';
require_once __DIR__ . '/modules/reviews.php';
require_once __DIR__ . '/modules/upload.php';
require_once __DIR__ . '/modules/excel.php';   /* v5.0.4: ایمپورت اکسل منو */
require_once __DIR__ . '/modules/sms.php';     /* v5.0.5-test.5: پنل پیامکی هر کافه */

header('Content-Type: application/json; charset=utf-8');
function jout(bool $ok, ?string $err = null, $data = null): void {
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode(['ok' => $ok, 'err' => $err, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}
/* v5.0.4-test.4: پیام خطای قابل فهم — استثناهای فنی (PDO/سرور) هرگز خام به کلاینت نمی‌روند؛
   متن فنی در لاگ خطای PHP ثبت می‌شود */
function giti_err(Throwable $e): void {
  $msg = $e->getMessage();
  if (!preg_match('/[\x{0600}-\x{06FF}]/u', $msg)) {
    error_log('[gitiarts] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = 'خطای غیرمنتظرهٔ سرور — چند لحظه بعد دوباره تلاش کنید؛ اگر تکرار شد با پشتیبانی تماس بگیرید';
  }
  jout(false, $msg);
}
set_exception_handler('giti_err');

$action = $_GET['action'] ?? '';

/* آپلود multipart */
if ($action === 'upload') { jout(true, null, up_upload()); }
/* v5.0.4: ایمپورت اکسل — بارگذاری فایل، خروجی فقط پیش‌نمایش (ذخیره با excel_apply) */
if ($action === 'excel_import') { jout(true, null, excel_import()); }

$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
}

$publicGet  = ['menu', 'notifs_pub'];   /* v5.0.5-test.1: کانال پیام مشتری */
$publicPost = ['order_create', 'review_add', 'reset_pass', 'reserve_create'];   /* v5.0.1 بازیابی رمز · v5.0.5-test.1 رزرو مشتری */
/* v5.0.3: تفکیک نقش — پرسنل صندوق/آشپزخانه فقط اکشن‌های عملیاتی؛
   منو/تنظیمات/کاربران/پشتیبان فقط مدیر کافه. state برای پرسنل بدون auth/users برمی‌گردد. */
$staffGet   = ['state'];
$staffPost  = ['order_pay', 'order_ready', 'order_deliver', 'save_shift',
               'save_tables', 'save_reservations', 'save_invoices',
               'reserve_set', 'notifs_read',
               'sms_send_one'];   /* v5.0.5-test.1: کنترل رزرو + خواندن پیام‌ها · test.5: پیامک تکی فاکتور */
$adminGet   = ['backup', 'export_status', 'sms_state'];   /* v5.0.5-test.5: وضعیت پنل پیامکی */
$adminPost  = ['review_toggle', 'review_del',
               'save_menu', 'save_settings', 'save_promos', 'save_users',
               'save_palettes', 'save_setup', 'export_request', 'excel_apply', 'pw_audit', 'notify_send',
               'sms_cfg_save', 'sms_test', 'sms_sync', 'sms_bulk_send'];   /* v5.0.5: بررسی رمز ضعیف · test.1: پیام دستی مدیر · test.5: پنل پیامکی */

try {
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (in_array($action, $publicGet, true) && function_exists($action)) {
      jout(true, null, $action($in));
    }
    if (in_array($action, $staffGet, true) && function_exists($action)) {
      t_require_tenant();
      jout(true, null, $action($in));
    }
    if (in_array($action, $adminGet, true) && function_exists($action)) {
      t_require_admin();
      jout(true, null, $action($in));
    }
  } else {
    if (in_array($action, $publicPost, true) && function_exists($action)) {
      jout(true, null, $action($in));
    }
    if (in_array($action, $staffPost, true) && function_exists($action)) {
      t_require_tenant();
      jout(true, null, $action($in));
    }
    if (in_array($action, $adminPost, true) && function_exists($action)) {
      t_require_admin();
      jout(true, null, $action($in));
    }
  }
  jout(false, 'درخواست نامعتبر به سرور — صفحه را نوسازی کنید (F5) و دوباره تلاش کنید');
} catch (Throwable $e) {
  giti_err($e);
}
