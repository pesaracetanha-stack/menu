<?php
/* ════════════════════════════════════════════════════════════
   ارسال کد تأیید پیامکی — فعلاً کاوه‌نگار
   ⚠️ اگر پنل پیامکی‌تان کاوه‌نگار نیست، فقط این فایل را بگویید
      تا provider مربوطه اضافه شود؛ بقیهٔ سیستم تغییری نمی‌کند.
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

function sms_send_code(string $phone, string $code): bool {
  $cfg = require dirname(__DIR__, 2) . '/config.php';
  $s   = $cfg['sms'];
  if (!$s['key']) return false;
  $phone = preg_replace('/\D/', '', $phone);

  switch ($s['provider']) {
    case 'kavenegar':
      $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
           . '/verify/lookup.json?receptor=' . rawurlencode($phone)
           . '&token=' . rawurlencode($code)
           . '&template=' . rawurlencode($s['template']);
      break;
    default:
      return false;
  }
  $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
  $res = @file_get_contents($url, false, $ctx);
  if ($res === false) return false;
  $j = json_decode($res, true);
  return is_array($j) && ($j['return']['status'] ?? 500) === 200;
}

/* تولید و نگه‌داری کد تأیید (۵ دقیقه اعتبار) */
function sms_issue_code(string $phone): bool {
  if (!sec_file_ok()) return false;
  $code = (string)random_int(10000, 99999);
  $d = sec_load();
  $d['otps'][$phone] = ['code'=>$code, 'exp'=>time()+300, 'tries'=>0];
  sec_store($d);
  return sms_send_code($phone, $code);
}
function sms_verify_code(string $phone, string $code): bool {
  $d = sec_load(); $o = $d['otps'][$phone] ?? null;
  if (!$o || $o['exp'] < time()) return false;
  if ($o['tries'] >= 5) return false;
  $d['otps'][$phone]['tries']++;
  if (!hash_equals($o['code'], $code)) { sec_store($d); return false; }
  unset($d['otps'][$phone]); sec_store($d);
  return true;
}
/* helpers کوچک فایل تأیید */
function sec_file_ok(): bool { return is_writable(dirname(PLAT_FILE)) || !file_exists(PLAT_FILE); }
function sec_load(): array { $j = json_decode((string)@file_get_contents(PLAT_FILE), true); return is_array($j)? $j : []; }
function sec_store(array $d): void { file_put_contents(PLAT_FILE, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX); }
