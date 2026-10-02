<?php
/* ════════════════════════════════════════════════════════════
   سامانهٔ رمز پنل ادمین پلتفرم
   هش password_hash · ضدتلاش (قفل) · CSRF · نشست امن
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const PLAT_FILE = __DIR__ . '/../../data/platform.json';

/* ── نشست امن ── */
function sec_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $cfg    = require dirname(__DIR__, 2) . '/config.php';
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_name($cfg['security']['session_name']);
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
  ]);
  session_start();
}

/* ── خواندن/نوشتن فایل رمز ── */
function pauth_load(): array {
  if (!file_exists(PLAT_FILE)) return [];
  $j = json_decode((string)file_get_contents(PLAT_FILE), true);
  return is_array($j) ? $j : [];
}
function pauth_save(array $d): void {
  $dir = dirname(PLAT_FILE);
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  file_put_contents(PLAT_FILE, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function pauth_configured(): bool { return isset(pauth_load()['ph']); }

/* ── سیاست رمز ── */
function pauth_policy(string $p): ?string {
  if (mb_strlen($p) < 8)             return 'رمز حداقل ۸ کاراکتر باشد';
  if (!preg_match('/[A-Za-z]/', $p)) return 'رمز باید حداقل یک حرف داشته باشد';
  if (!preg_match('/\d/', $p))       return 'رمز باید حداقل یک عدد داشته باشد';
  return null;
}

/* ── راه‌اندازی اولیه (فقط یک‌بار، با کلید نصب) ── */
function pauth_setup(string $key, string $p1, string $p2): string {
  if (pauth_configured()) return 'رمز از قبل تنظیم شده است';
  $cfg  = require dirname(__DIR__, 2) . '/config.php';
  $want = (string)($cfg['setup_key'] ?? '');
  if ($want === '' || !hash_equals($want, $key))
    return 'کلید نصب نامعتبر است — مقدار setup_key را در config.php بررسی کنید';
  if ($p1 !== $p2) return 'تکرار رمز مطابقت ندارد';
  if ($e = pauth_policy($p1)) return $e;
  pauth_save(['ph' => password_hash($p1, PASSWORD_DEFAULT),
              'created' => time(), 'attempts' => [], 'lock' => 0]);
  return '';
}

/* ── ورود با محدودیت تلاش ── */
function pauth_login(string $p): string {
  $d = pauth_load(); $now = time();
  if (($d['lock'] ?? 0) > $now)
    return 'به‌دلیل تلاش‌های ناموفق موقتاً قفل است — چند دقیقه بعد تلاش کنید';
  if (!pauth_configured()) return 'رمز تنظیم نشده — ابتدا راه‌اندازی را انجام دهید';
  if (!password_verify($p, $d['ph'])) {
    $d['attempts'] = array_values(array_filter($d['attempts'] ?? [],
      fn($t) => $t > $now - 600));
    $d['attempts'][] = $now;
    $cfg = require dirname(__DIR__, 2) . '/config.php';
    if (count($d['attempts']) >= $cfg['security']['login_max_attempts']) {
      $d['lock'] = $now + $cfg['security']['lockout_minutes'] * 60;
      $d['attempts'] = [];
    }
    pauth_save($d);
    return 'رمز اشتباه است';
  }
  $d['attempts'] = []; $d['lock'] = 0; pauth_save($d);
  session_regenerate_id(true);
  $_SESSION['plat'] = true;
  return '';
}

function pauth_logged(): bool  { return !empty($_SESSION['plat']); }
function pauth_require(): void { if (!pauth_logged()) { header('Location: index.php'); exit; } }
function pauth_logout(): void  { unset($_SESSION['plat']); }

/* ── تغییر رمز (از خود پنل) ── */
function pauth_change(string $old, string $p1, string $p2): string {
  $d = pauth_load();
  if (!password_verify($old, $d['ph'] ?? '')) return 'رمز فعلی اشتباه است';
  if ($p1 !== $p2) return 'تکرار رمز مطابقت ندارد';
  if ($e = pauth_policy($p1)) return $e;
  $d['ph'] = password_hash($p1, PASSWORD_DEFAULT);
  pauth_save($d);
  return '';
}

/* ── CSRF ── */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_ok(?string $t): bool {
  return !empty($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/* ═══════════════════════════════════════════════════════════════
   v5.0.5-test.3: کد یکبارمصرف ورود به پنل مدیر پلتفرم (ورود دومرحله‌ای)
   · گام ۱: رمز مدیر → درست بود، کد ۶ رقمی به موبایل مدیر پیامک می‌شود
   · گام ۲: کد در همان نشست وارد می‌شود (۵ دقیقه اعتبار · ۵ تلاش · ۶۰ ثانیه فاصلهٔ ارسال مجدد)
   · فعال‌سازی: «security.admin_mobile» و کلید پیامک در config.php —
     اگر هر دو تنظیم نشده باشند، ورود مثل قبل تک‌مرحله‌ای می‌ماند (هیچ چیزی نمی‌شکند).
   ═══════════════════════════════════════════════════════════════ */
function pauth_otp_enabled(): bool {
  if (!preg_match('/^09\d{9}$/', pauth_otp_mobile())) return false;
  $cfg = require dirname(__DIR__, 2) . '/config.php';
  return (string)($cfg['sms']['key'] ?? '') !== '';
}
function pauth_otp_mobile(): string {
  $cfg = require dirname(__DIR__, 2) . '/config.php';
  return preg_replace('/\D/', '', (string)($cfg['security']['admin_mobile'] ?? ''));
}
/* شمارهٔ نق‌شده برای نمایش: 0912***4567 */
function pauth_otp_mobile_masked(): string {
  $m = pauth_otp_mobile();
  return strlen($m) === 11 ? substr($m, 0, 4) . '***' . substr($m, 7) : $m;
}
/* ارسال کد — '' یعنی موفق، رشتهٔ دیگر = پیام خطا */
function pauth_otp_send(): string {
  if (!function_exists('sms_send_code')) require_once __DIR__ . '/sms.php';
  $d = pauth_load(); $now = time();
  $wait = (int)($d['otp']['next'] ?? 0) - $now;
  if ($wait > 0) return 'ارسال مجدد کد تا ' . $wait . ' ثانیه دیگر ممکن است';
  $code = (string)random_int(100000, 999999);
  if (!sms_send_code(pauth_otp_mobile(), $code))
    return 'ارسال پیامک ناموفق بود — کلید پیامک / قالب تأیید را در config.php بررسی کنید';
  $d['otp'] = ['code' => $code, 'exp' => $now + 300, 'tries' => 0, 'next' => $now + 60];
  pauth_save($d);
  return '';
}
/* بررسی کد — '' یعنی موفق (نشست plat باز می‌شود) */
function pauth_otp_verify(string $code): string {
  $d = pauth_load(); $now = time();
  $o = $d['otp'] ?? null;
  if (!is_array($o) || (int)($o['exp'] ?? 0) < $now) {
    unset($d['otp']); pauth_save($d);
    return 'کد منقضی شده است — دوباره وارد شوید';
  }
  if ((int)($o['tries'] ?? 0) >= 5) {
    unset($d['otp']); pauth_save($d);
    unset($_SESSION['plat_2fa']);
    return 'تلاش‌های زیاد ناموفق — ورود از ابتدا (رمز + کد تازه) لازم است';
  }
  $d['otp']['tries'] = (int)($o['tries'] ?? 0) + 1;
  if (!hash_equals((string)($o['code'] ?? ''), trim($code))) {
    pauth_save($d);
    $left = 5 - (int)$d['otp']['tries'];
    return 'کد اشتباه است' . ($left > 0 ? ' — ' . $left . ' تلاش باقی مانده' : '');
  }
  unset($d['otp']); pauth_save($d);
  unset($_SESSION['plat_2fa']);
  session_regenerate_id(true);
  $_SESSION['plat'] = true;
  return '';
}
