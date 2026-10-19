<?php
declare(strict_types=1);
/* ════════════════════════════════════════════════════════════
   ارسال کد تأیید پیامکی — پشتیبانی از mydnspanel و Kavenegar
   ════════════════════════════════════════════════════════════ */

function sms_get_config(): array {
    $cfg = require dirname(__DIR__, 2) . '/config.php';
    $s = $cfg['sms'] ?? [];
    $file = dirname(__DIR__, 2) . '/data/platform-sms.json';
    if (is_file($file)) {
        $j = json_decode((string)file_get_contents($file), true);
        if (is_array($j)) {
            $s = array_merge($s, $j);
        }
    }
    return $s;
}

function sms_send_code(string $phone, string $code): bool {
    $s = sms_get_config();
    if (empty($s['key'])) return false;
    $phone = preg_replace('/\D/', '', $phone);

    if ($s['provider'] === 'mydnspanel') {
        $apiUrl = "mydnspanel.com/webservice/server";
        $apiParam = array(
            'action' => 'sendOTP',
            'type' => 2,
            'otpCode' => $code,
            'appID' => 0,
            'receivers' => $phone
        );
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $s['key']]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParam);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $j = json_decode($res, true);
            return is_array($j) && ($j['result'] ?? 0) === 1;
        }
        return false;
    }

    // Kavenegar fallback
    if ($s['provider'] === 'kavenegar') {
        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
             . '/verify/lookup.json?receptor=' . rawurlencode($phone)
             . '&token=' . rawurlencode($code)
             . '&template=' . rawurlencode($s['template'] ?? '');
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return false;
        $j = json_decode($res, true);
        return is_array($j) && ($j['return']['status'] ?? 500) === 200;
    }
    return false;
}

function sms_send_welcome(string $phone, string $cafeName): bool {
    $s = sms_get_config();
    if (empty($s['key'])) return false;
    $phone = preg_replace('/\D/', '', $phone);

    if ($s['provider'] === 'mydnspanel') {
        $apiUrl = "mydnspanel.com/webservice/server";
        $apiParam = array(
            'action' => 'send',
            'text' => "کافه «{$cafeName}» با موفقیت ثبت شد.",
            'receivers' => $phone
        );
        if (!empty($s['sender'])) {
            $apiParam['from'] = $s['sender'];
        }
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $s['key']]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParam);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $j = json_decode($res, true);
            return is_array($j) && ($j['result'] ?? 0) === 1;
        }
        return false;
    }

    // Kavenegar fallback
    if ($s['provider'] === 'kavenegar') {
        if (empty($s['template_welcome'])) return false;
        $token = mb_substr($cafeName, 0, 20, 'UTF-8');
        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
             . '/verify/lookup.json?receptor=' . rawurlencode($phone)
             . '&token=' . rawurlencode($token)
             . '&template=' . rawurlencode($s['template_welcome']);
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return false;
        $j = json_decode($res, true);
        return is_array($j) && ($j['return']['status'] ?? 500) === 200;
    }
    return false;
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
function sec_file_ok(): bool { return is_writable(dirname(PLAT_FILE)) || !file_exists(PLAT_FILE); }
function sec_load(): array { $j = json_decode((string)@file_get_contents(PLAT_FILE), true); return is_array($j)? $j : []; }
function sec_store(array $d): void { file_put_contents(PLAT_FILE, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX); }