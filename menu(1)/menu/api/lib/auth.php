<?php
/* ════════════════════════════════════════════════════════════
   نشست کافه‌دار (TEN_SESS — همان کوکی login.php) + لایسنس
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

function t_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_name('TEN_SESS');
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
  ]);
  session_start();
}
/* v5.0.3: تفکیک نقش — «نشست معتبر کافه» (مدیر یا پرسنل) از «مدیر» جدا شد.
   پرسنل صندوق/آشپزخانه فقط به اکشن‌های عملیاتی دسترسی دارد (فهرست staffPost). */
function t_is_tenant(): bool {
  t_session();
  return (($_SESSION['tenant'] ?? '') === T_SLUG);
}
function t_is_admin(): bool {
  /* نشست‌های قدیمیِ بدون tenant_role = مدیر (سازگاری با قبل از v5.0.2) */
  return t_is_tenant() && ($_SESSION['tenant_role'] ?? 'admin') === 'admin';
}
function t_is_staff(): bool {
  return t_is_tenant() && ($_SESSION['tenant_role'] ?? 'admin') !== 'admin';
}
function t_require_tenant(): void {
  if (!t_is_tenant()) throw new Exception('برای این بخش وارد حساب کافه شوید');
}
function t_require_admin(): void {
  if (!t_is_admin()) throw new Exception('دسترسی فقط برای مدیر کافه');
}
/* وضعیت لایسنس — منو همیشه عمومی است؛ سفارش‌گیری مشروط به اعتبار */
function t_license(array $db): array {
  $lic  = $db['license'] ?? [];
  $now  = round(microtime(true) * 1000);
  $st   = (string)($lic['status'] ?? 'trial');
  $days = null; $ok = true;
  if ($st === 'trial') {
    $days = (int)ceil((($lic['expires'] ?? 0) - $now) / 86400000);
    if ($days <= 0) { $ok = false; $days = 0; }
  } elseif ($st === 'active') {
    $days = (int)ceil((($lic['paid_until'] ?? 0) - $now) / 86400000);
    if ($days <= 0) { $ok = false; $days = 0; }
  } elseif ($st === 'suspended') { $ok = false; }
  return ['status'=>$st, 'ok'=>$ok, 'days_left'=>$days, 'key'=>(string)($lic['key'] ?? '')];
}
