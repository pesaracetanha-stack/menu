<?php
/* ═══════════════════════════════════════════════════════════════
   v5.0.5-test.5: پنل پیامکی هر کافه — اتصال مستقیم API
   همگام‌سازی باشگاه مشتریان با پنل پیامکی مدیر کافه، به‌صورت:
   · خودکار: هر عضو تازه در صف قرار می‌گیرد و صف خودکار تخلیه می‌شود
   · دستی: دکمهٔ «همگام‌سازی» در باشگاه مشتریان
   درایورها:
   · smsir     — SMS.ir (api.sms.ir) : اعتبارسنجی کلید + موجودی + ارسال گروهی + پیامک خوش‌آمد
   · kavenegar — کاوه‌نگار              : اعتبارسنجی کلید + موجودی + ارسال گروهی + پیامک خوش‌آمد
   · hook      — وب‌هوک سفارشی          : ذخیرهٔ هر عضو در هر پنل دیگر (POST با قالب دلخواه)
   امنیت: کلیدها فقط در db کافه می‌مانند؛ به مرورگر فقط نسخهٔ ماسک‌شده می‌رود
   (sms_pub در data.php ← t_state_out). هرگز خطای فنی خام به کلاینت نمی‌رود.
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const SMS_Q_MAX    = 200;   /* سقف صف همگام‌سازی */
const SMS_SYNC_MAX = 40;    /* حداکثر پردازش صف در هر فراخوانی دستی */
const SMS_SYNC_AUTO = 8;    /* حداکثر پردازش در هر فراخوانی خودکار */
const SMS_BULK_MAX = 300;   /* حداکثر گیرنده در یک ارسال گروهی */
const SMS_TXT_MAX  = 600;   /* سقف نویسهٔ متن پیامک */

const SMS_PROVS = ['smsir', 'kavenegar', 'hook'];
const SMS_PROV_FA = ['smsir' => 'SMS.ir', 'kavenegar' => 'کاوه‌نگار', 'hook' => 'وب‌هوک سفارشی'];

/* ── مشتری HTTP بدون وابستگی به curl (مثل api/lib/sms.php پلتفرم) ── */
function sms_http(string $url, string $method = 'GET', array $headers = [],
                  string $body = '', int $timeout = 8): array {
  $hdrs = array_merge(['Connection: close'], $headers);
  $ctx = stream_context_create(['http' => [
    'method' => $method, 'timeout' => $timeout, 'ignore_errors' => true,
    'header' => implode("\r\n", $hdrs), 'content' => $body,
  ]]);
  $res = @file_get_contents($url, false, $ctx);
  $status = 0;
  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $hh)
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$hh, $mm)) { $status = (int)$mm[1]; break; }
  }
  if ($res === false) return ['ok' => false, 'status' => $status, 'body' => '', 'json' => null,
                             'err' => 'ارتباط با پنل پیامکی برقرار نشد (شبکه/فایروال هاست)'];
  $j = json_decode((string)$res, true);
  return ['ok' => $status >= 200 && $status < 300, 'status' => $status,
          'body' => (string)$res, 'json' => is_array($j) ? $j : null, 'err' => null];
}

/* ── پیکربندی ── */
function sms_cfg(array $db): array {
  $c = is_array($db['sms'] ?? null) ? $db['sms'] : [];
  $d = ['en' => false, 'prov' => 'smsir', 'key' => '', 'user' => '', 'pass' => '', 'line' => '',
        'auto' => true, 'wel' => false,
        'welTxt' => '{name} عزیز؛ به باشگاه مشتریان ما خوش آمدید! شمارهٔ اشتراک شما {subno} است.',
        'hookUrl' => '', 'hookMethod' => 'POST', 'hookHd' => '', 'hookBody' => '{"name":"{name}","mobile":"{phone}","subno":"{subno}","points":"{points}"}',
        'q' => [], 'st' => []];
  foreach ($d as $k => $v) if (!array_key_exists($k, $c)) $c[$k] = $v;
  if (!in_array($c['prov'], SMS_PROVS, true)) $c['prov'] = 'smsir';
  foreach (['en', 'auto', 'wel'] as $k) $c[$k] = !empty($c[$k]);
  foreach (['key', 'user', 'pass', 'line', 'welTxt', 'hookUrl', 'hookMethod', 'hookHd', 'hookBody'] as $k)
    $c[$k] = (string)$c[$k];
  if (!is_array($c['q'])) $c['q'] = [];
  $c['q'] = array_values(array_slice($c['q'], -SMS_Q_MAX));
  if (!is_array($c['st'])) $c['st'] = [];
  $st = $c['st'];
  foreach (['lastSync' => 0, 'ok' => 0, 'fail' => 0, 'sent' => 0, 'lastTest' => null, 'errs' => []] as $k => $v)
    if (!array_key_exists($k, $st)) $st[$k] = $v;
  if (!is_array($st['errs'])) $st['errs'] = [];
  $st['errs'] = array_slice(array_values($st['errs']), -5);
  $c['st'] = $st;
  return $c;
}

function sms_mask(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $n = mb_strlen($s);
  if ($n <= 6) return '•••';
  return mb_substr($s, 0, 4) . '••••' . mb_substr($s, -3);
}

/* خروجی امن برای مرورگر — کلیدها هرگز خام نمی‌روند */
function sms_pub(array $c): array {
  return ['en' => $c['en'], 'prov' => $c['prov'], 'provFa' => SMS_PROV_FA[$c['prov']],
          'line' => $c['line'], 'auto' => $c['auto'], 'wel' => $c['wel'], 'welTxt' => $c['welTxt'],
          'keym' => sms_mask($c['key']), 'userm' => sms_mask($c['user']),
          'hookUrl' => $c['hookUrl'], 'hookMethod' => $c['hookMethod'],
          'hookBody' => $c['hookBody'], 'q' => count($c['q']), 'st' => $c['st']];
}

/* ── وضعیت (مدیر کافه) ── */
function sms_state($in): array {
  $db = t_db_get();
  $c  = sms_cfg($db);
  $withPhone = 0;
  foreach ($db['customers'] as $m)
    if (preg_match('/^09\d{9}$/', (string)($m['phone'] ?? ''))) $withPhone++;
  return ['cfg' => sms_pub($c), 'members' => count($db['customers']), 'withPhone' => $withPhone];
}

/* ── ذخیرهٔ تنظیمات (مدیر کافه) ── */
function sms_cfg_save(array $in): array {
  $g = fn(string $k, int $max, string $def = '') =>
    mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in[$k] ?? ''))), 0, $max);
  $newKey  = trim((string)($in['key']  ?? ''));
  $newUser = trim((string)($in['user'] ?? ''));
  $newPass = trim((string)($in['pass'] ?? ''));
  $prov    = (string)($in['prov'] ?? 'smsir');
  if (!in_array($prov, SMS_PROVS, true)) $prov = 'smsir';
  $hookUrl = $g('hookUrl', 300);
  if ($hookUrl !== '' && !preg_match('#^https?://#i', $hookUrl))
    throw new Exception('آدرس وب‌هوک باید با http:// یا https:// شروع شود');
  return t_db_txn(function (array &$db) use ($in, $prov, $newKey, $newUser, $newPass, $hookUrl, $g) {
    $c = sms_cfg($db);
    $c['prov']  = $prov;
    $c['en']    = !empty($in['en']);
    $c['auto']  = !isset($in['auto']) ? true : !empty($in['auto']);
    $c['wel']   = !empty($in['wel']);
    if ($newKey  !== '') $c['key']  = $newKey;      /* خالی = تغییر نکند؛ «-» = پاک شود */
    elseif ($newKey === '-' ) $c['key'] = '';
    if ($newUser !== '') $c['user'] = $newUser;
    elseif ($newUser === '-') $c['user'] = '';
    if ($newPass !== '') $c['pass'] = $newPass;
    elseif ($newPass === '-') $c['pass'] = '';
    /* فیلدهای دیگر فقط اگر در درخواست آمده باشند بازنویسی می‌شوند —
       غایب = مقدار فعلی بماند (فراخوانی‌های جزئی امن است) */
    if (array_key_exists('line', $in))
      $c['line'] = preg_replace('/[^0-9+]/', '', (string)$in['line']) ?: '';
    if (array_key_exists('welTxt', $in) && trim((string)$in['welTxt']) !== '')
      $c['welTxt'] = mb_substr(trim((string)$in['welTxt']), 0, SMS_TXT_MAX);
    if (array_key_exists('hookUrl', $in))   $c['hookUrl'] = $hookUrl;
    if (array_key_exists('hookMethod', $in))
      $c['hookMethod'] = strtoupper((string)$in['hookMethod']) === 'GET' ? 'GET' : 'POST';
    if (array_key_exists('hookHd', $in))    $c['hookHd'] = $g('hookHd', 500);
    if (array_key_exists('hookBody', $in))
      $c['hookBody'] = $g('hookBody', 1000) ?: '{"name":"{name}","mobile":"{phone}"}';
    if ($c['en'] && in_array($c['prov'], ['smsir', 'kavenegar'], true) && $c['key'] === '')
      throw new Exception('برای فعال‌سازی، کلید وب‌سرویس پنل پیامکی را وارد کنید');
    if ($c['en'] && $c['prov'] === 'hook' && $c['hookUrl'] === '')
      throw new Exception('برای فعال‌سازی وب‌هوک، آدرس آن را وارد کنید');
    if ($c['en'] && $c['wel'] && in_array($c['prov'], ['smsir', 'kavenegar'], true) && $c['line'] === '')
      throw new Exception('برای پیامک خوش‌آمد، شمارهٔ خط ارسال را وارد کنید (مثلاً 3000505)');
    $db['sms'] = $c;
    /* خروجی از خودِ سند تراکنش — t_db_get داخل تراکنش دادهٔ کهنه می‌دهد */
    $withPhone = 0;
    foreach ($db['customers'] as $m)
      if (preg_match('/^09\d{9}$/', (string)($m['phone'] ?? ''))) $withPhone++;
    return ['cfg' => sms_pub($c), 'members' => count($db['customers']), 'withPhone' => $withPhone];
  });
}

/* ثبت خطا زیر تراکنش */
function sms_st_err(array &$db, string $msg): void {
  $c = sms_cfg($db);
  $c['st']['errs'][] = ['ts' => round(microtime(true) * 1000), 'm' => mb_substr($msg, 0, 160)];
  $c['st']['errs'] = array_slice($c['st']['errs'], -5);
  $db['sms'] = $c;
}

/* ── تست اتصال (مدیر کافه) — کلید و موجودی ── */
function sms_test($in): array {
  $db = t_db_get();
  $c  = sms_cfg($db);
  if (!$c['en']) throw new Exception('ابتدا پنل پیامکی را فعال و ذخیره کنید');
  $r = ['ok' => false, 'msg' => ''];
  if ($c['prov'] === 'smsir') {
    $h = sms_http('https://api.sms.ir/v1/credit', 'GET', ['X-API-KEY: ' . $c['key']]);
    $j = $h['json'];
    if ($h['ok'] && $j && (int)($j['status'] ?? 0) === 1) {
      $r = ['ok' => true, 'msg' => 'اتصال برقرار است — اعتبار: ' . number_format((float)($j['data'] ?? 0)) . ' ریال'];
    } else {
      $r['msg'] = ($j && isset($j['message'])) ? 'پنل پیامک پاسخ داد: ' . (string)$j['message']
                : 'اتصال ناموفق (کد HTTP ' . $h['status'] . ') — کلید را بررسی کنید';
    }
  } elseif ($c['prov'] === 'kavenegar') {
    $h = sms_http('https://api.kavenegar.com/v1/' . rawurlencode($c['key']) . '/account/info');
    $j = $h['json'];
    if ($h['ok'] && $j && (int)($j['return']['status'] ?? 0) === 200) {
      $r = ['ok' => true, 'msg' => 'اتصال برقرار است — اعتبار: ' . (($j['entries']['remain_credit'] ?? '0')) . ' ریال'];
    } else {
      $r['msg'] = ($j && isset($j['return']['message'])) ? 'کاوه‌نگار پاسخ داد: ' . (string)$j['return']['message']
                : 'اتصال ناموفق (کد HTTP ' . $h['status'] . ') — کلید را بررسی کنید';
    }
  } else { /* hook — یک مخاطب نمونه ارسال می‌شود */
    $h = sms_hook_push($c, ['name' => 'تست اتصال GitiArts', 'phone' => '09000000000', 'subno' => 0, 'points' => 0]);
    $r = $h['ok'] ? ['ok' => true, 'msg' => 'وب‌هوک پاسخ داد (کد HTTP ' . $h['status'] . ') — مخاطب نمونه ثبت شد']
                  : ['ok' => false, 'msg' => $h['err'] ?: 'وب‌هوک پاسخ نداد (کد HTTP ' . $h['status'] . ')'];
  }
  t_db_txn(function (array &$db) use ($r) {
    $c = sms_cfg($db);
    $c['st']['lastTest'] = ['ok' => $r['ok'], 'msg' => $r['msg'], 'ts' => round(microtime(true) * 1000)];
    if (!$r['ok']) { $c['st']['errs'][] = ['ts' => round(microtime(true) * 1000), 'm' => $r['msg']]; $c['st']['errs'] = array_slice($c['st']['errs'], -5); }
    $db['sms'] = $c;
    return null;
  });
  return $r;
}

/* ── درایور وب‌هوک: ثبت یک مخاطب با قالب دلخواه مدیر ── */
function sms_hook_push(array $c, array $m): array {
  $fill = fn(string $s) => strtr((string)$s, [
    '{name}'   => (string)($m['name'] ?? ''), '{phone}' => (string)($m['phone'] ?? ''),
    '{subno}'  => (string)($m['subno'] ?? '0'), '{points}' => (string)($m['points'] ?? '0'),
    '{cafe}'   => (string)($m['cafe'] ?? ''),
  ]);
  $url  = $fill($c['hookUrl']);
  $hdrs = [];
  foreach (preg_split('/\r\n|\r|\n/', (string)$c['hookHd']) as $ln)
    if (trim((string)$ln) !== '' && preg_match('/^[A-Za-z0-9\-_]+:\s*.+$/', trim((string)$ln))) $hdrs[] = trim((string)$ln);
  if ($c['hookMethod'] === 'GET') {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url .= $sep . 'name=' . rawurlencode((string)$m['name']) . '&mobile=' . rawurlencode((string)$m['phone'])
          . '&subno=' . rawurlencode((string)$m['subno']) . '&points=' . rawurlencode((string)$m['points']);
    return sms_http($url, 'GET', $hdrs);
  }
  $body = $fill($c['hookBody'] !== '' ? $c['hookBody'] : '{"name":"{name}","mobile":"{phone}"}');
  $isJson = (ltrim($body)[0] ?? '{') === '{';
  $hdrs[] = 'Content-Type: ' . ($isJson ? 'application/json' : 'application/x-www-form-urlencoded');
  return sms_http($url, 'POST', $hdrs, $body);
}

/* ── درایور ارسال گروهی/تکی ──
   برمی‌گرداند: ['ok'=>bool, 'msg'=>..., 'sent'=>n] */
function sms_send_batch(array $c, array $phones, string $txt, string $cafe): array {
  $phones = array_values(array_unique(array_filter($phones, fn($p) => preg_match('/^09\d{9}$/', (string)$p))));
  if (!count($phones)) return ['ok' => false, 'msg' => 'شمارهٔ معتبری برای ارسال نیست', 'sent' => 0];
  if ($c['prov'] === 'hook')
    return ['ok' => false, 'msg' => 'درایور وب‌هوک فقط برای ذخیرهٔ مخاطب است — ارسال پیامک با سرویس‌های پیامکی', 'sent' => 0];
  if ($c['prov'] === 'smsir') {
    $line = (int)preg_replace('/[^0-9]/', '', $c['line']);
    if ($line <= 0) return ['ok' => false, 'msg' => 'در تنظیمات، شمارهٔ خط ارسال SMS.ir را وارد کنید', 'sent' => 0];
    $h = sms_http('https://api.sms.ir/v1/send/bulk', 'POST',
      ['X-API-KEY: ' . $c['key'], 'Content-Type: application/json'],
      json_encode(['lineNumber' => $line, 'messageText' => $txt, 'mobiles' => $phones], JSON_UNESCAPED_UNICODE));
    $j = $h['json'];
    if ($h['ok'] && $j && (int)($j['status'] ?? 0) === 1) return ['ok' => true, 'msg' => 'پیامک ارسال شد', 'sent' => count($phones)];
    return ['ok' => false, 'msg' => ($j && isset($j['message'])) ? 'SMS.ir: ' . (string)$j['message']
            : 'ارسال ناموفق (کد HTTP ' . $h['status'] . ')', 'sent' => 0];
  }
  /* کاوه‌نگار */
  $post = http_build_query(['receptor' => implode(',', $phones), 'message' => $txt] + ($c['line'] !== '' ? ['sender' => $c['line']] : []));
  $h = sms_http('https://api.kavenegar.com/v1/' . rawurlencode($c['key']) . '/sms/send.json', 'POST',
    ['Content-Type: application/x-www-form-urlencoded'], $post);
  $j = $h['json'];
  if ($h['ok'] && $j && (int)($j['return']['status'] ?? 0) === 200)
    return ['ok' => true, 'msg' => 'پیامک ارسال شد', 'sent' => count($phones)];
  return ['ok' => false, 'msg' => ($j && isset($j['return']['message'])) ? 'کاوه‌نگار: ' . (string)$j['return']['message']
          : 'ارسال ناموفق (کد HTTP ' . $h['status'] . ')', 'sent' => 0];
}

/* متن خوش‌آمد با جای‌نگهدارها */
function sms_welcome_txt(array $c, array $db, array $m): string {
  $cafe = (string)($db['menu']['brand']['name'] ?? 'کافه');
  return strtr($c['welTxt'], ['{name}' => (string)($m['name'] ?? ''), '{phone}' => (string)($m['phone'] ?? ''),
    '{subno}' => (string)($m['subno'] ?? '0'), '{points}' => (string)($m['points'] ?? '0'), '{cafe}' => $cafe]);
}

/* ── صف خودکار — فقط داخل تراکنش سفارش صدا زده می‌شود (بدون HTTP) ── */
function sms_queue_member(array &$db, string $phone): void {
  if ($phone === '' || !preg_match('/^09\d{9}$/', $phone)) return;
  $c = sms_cfg($db);
  if (!$c['en'] || !$c['auto']) return;
  if (in_array($phone, $c['q'], true)) return;
  if (count($c['q']) >= SMS_Q_MAX) array_shift($c['q']);
  $c['q'][] = $phone;
  $db['sms'] = $c;
}

/* ── همگام‌سازی (مدیر کافه) — تخلیهٔ صف؛ hook: ثبت مخاطب، سرویس‌ها: پیامک خوش‌آمد ──
   سیاست صف: هر عضو پردازش‌شده (موفق یا ناموفق) از صف حذف می‌شود — خطاها در لاگ
   می‌مانند و «همگام‌سازی کامل» (all=1) همهٔ اعضا را دوباره ثبت می‌کند. */
function sms_sync(array $in): array {
  $auto = !empty($in['auto']);
  $all  = !empty($in['all']);
  $db = t_db_get();
  $c  = sms_cfg($db);
  if (!$c['en']) throw new Exception('ابتدا پنل پیامکی را در تنظیمات فعال کنید');
  $max = $auto ? SMS_SYNC_AUTO : SMS_SYNC_MAX;
  $done = 0; $okN = 0; $failN = 0; $errs = [];
  $cafe = (string)($db['menu']['brand']['name'] ?? 'کافه');
  $members = [];
  foreach ($db['customers'] as $m) $members[(string)($m['phone'] ?? '')] = $m;

  if ($c['prov'] === 'hook') {
    /* وب‌هوک: ثبت مخاطب در پنل بیرونی — عادی = صف، با all=1 = همهٔ اعضای دارای موبایل معتبر */
    $targets = $all
      ? array_filter(array_keys($members), fn($p) => preg_match('/^09\d{9}$/', (string)$p))
      : $c['q'];
    $targets = array_slice(array_values(array_unique($targets)), 0, $max);
    foreach ($targets as $ph) {
      $m = $members[$ph] ?? null;
      if (!$m) { $done++; continue; }
      $r = sms_hook_push($c, ['name' => (string)($m['name'] ?? ''), 'phone' => $ph,
                              'subno' => (int)($m['sub_no'] ?? 0), 'points' => (int)($m['points'] ?? 0), 'cafe' => $cafe]);
      $done++;
      if ($r['ok']) $okN++;
      else { $failN++; $errs[] = $ph . ': ' . ($r['err'] ?: 'HTTP ' . $r['status']); }
    }
  } else {
    /* سرویس‌های پیامکی: وب‌سرویسشان «افزودن مخاطب» ندارد → پیامک خوش‌آمد خودکار */
    if (!$c['wel']) {
      $done = count($c['q']);
      t_db_txn(function (array &$db2) {
        $c2 = sms_cfg($db2);
        $c2['q'] = [];
        $c2['st']['lastSync'] = round(microtime(true) * 1000);
        $db2['sms'] = $c2;
        return null;
      });
      return ['done' => $done, 'ok' => 0, 'fail' => 0, 'errs' => [], 'left' => 0,
              'msg' => 'وب‌سرویس این پنل افزودن مخاطب ندارد؛ صف پاک شد. برای ذخیرهٔ مخاطب، درایور «وب‌هوک سفارشی» یا خروجی اکسل را استفاده کنید.'];
    }
    $targets = array_slice($c['q'], 0, $max);
    foreach ($targets as $ph) {
      if (!isset($members[$ph])) { $done++; continue; }
      $m = $members[$ph];
      $txt = sms_welcome_txt($c, $db, ['name' => (string)($m['name'] ?? ''),
        'subno' => (int)($m['sub_no'] ?? 0), 'points' => (int)($m['points'] ?? 0)]);
      $r = sms_send_batch($c, [$ph], $txt, $cafe);
      $done++;
      if ($r['ok']) $okN++;
      else { $failN++; $errs[] = $ph . ': ' . $r['msg']; }
    }
  }
  $left = 0; $lastSync = round(microtime(true) * 1000);
  t_db_txn(function (array &$db) use ($done, $okN, $failN, $errs, &$left, $lastSync, $all) {
    $c2 = sms_cfg($db);
    /* پردازش‌شده‌ها از ابتدای صف حذف می‌شوند (ترتیب پردازش = ترتیب صف) */
    $c2['q'] = $all ? [] : array_slice($c2['q'], min($done, count($c2['q'])));
    $c2['st']['ok']   = (int)$c2['st']['ok'] + $okN;
    $c2['st']['fail'] = (int)$c2['st']['fail'] + $failN;
    $c2['st']['lastSync'] = $lastSync;
    foreach ($errs as $e) { $c2['st']['errs'][] = ['ts' => $lastSync, 'm' => mb_substr($e, 0, 160)]; }
    $c2['st']['errs'] = array_slice($c2['st']['errs'], -5);
    if ($c2['prov'] !== 'hook') $c2['st']['sent'] = (int)$c2['st']['sent'] + $okN;
    $db['sms'] = $c2;
    $left = count($c2['q']);
    return null;
  });
  $msg = 'همگام‌سازی انجام شد — ' . $okN . ' موفق' . ($failN ? '، ' . $failN . ' ناموفق' : '') . ($left ? '، ' . $left . ' در صف' : '');
  return ['done' => $done, 'ok' => $okN, 'fail' => $failN, 'errs' => array_slice($errs, 0, 5),
          'left' => $left, 'msg' => $msg];
}

/* ── ارسال گروهی (مدیر کافه) ── */
function sms_bulk_send(array $in): array {
  $db = t_db_get();
  $c  = sms_cfg($db);
  if (!$c['en']) throw new Exception('ابتدا پنل پیامکی را در تنظیمات فعال کنید');
  $txt = trim((string)($in['txt'] ?? ''));
  if ($txt === '') throw new Exception('متن پیامک را بنویسید');
  if (mb_strlen($txt) > SMS_TXT_MAX) throw new Exception('متن پیامک حداکثر ' . SMS_TXT_MAX . ' نویسه است');
  $phones = [];
  if (!empty($in['all'])) {
    foreach ($db['customers'] as $m) {
      $p = (string)($m['phone'] ?? '');
      if (preg_match('/^09\d{9}$/', $p)) $phones[] = $p;
    }
  } else {
    foreach ((array)($in['phones'] ?? []) as $p) $phones[] = preg_replace('/\D/', '', (string)$p);
  }
  $phones = array_values(array_unique($phones));
  if (count($phones) > SMS_BULK_MAX) $phones = array_slice($phones, 0, SMS_BULK_MAX);
  $cafe = (string)($db['menu']['brand']['name'] ?? 'کافه');
  $r = sms_send_batch($c, $phones, $txt, $cafe);
  t_db_txn(function (array &$db) use ($r, $txt) {
    $c2 = sms_cfg($db);
    if ($r['ok']) $c2['st']['sent'] = (int)$c2['st']['sent'] + (int)$r['sent'];
    else { $c2['st']['errs'][] = ['ts' => round(microtime(true) * 1000), 'm' => mb_substr('ارسال گروهی: ' . $r['msg'], 0, 160)];
           $c2['st']['errs'] = array_slice($c2['st']['errs'], -5); }
    $db['sms'] = $c2;
    return null;
  });
  if (!$r['ok']) throw new Exception($r['msg']);
  return ['sent' => (int)$r['sent'], 'msg' => $r['msg'] . ' — به ' . count($phones) . ' عضو'];
}

/* ── ارسال تکی (فاکتور — مدیر و صندوق) ── */
function sms_send_one(array $in): array {
  $db = t_db_get();
  $c  = sms_cfg($db);
  if (!$c['en']) throw new Exception('پنل پیامکی هنوز تنظیم نشده است — از باشگاه مشتریان، «تنظیمات پنل پیامکی» را کامل کنید');
  $phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
  if (!preg_match('/^09\d{9}$/', $phone)) throw new Exception('شمارهٔ موبایل معتبر نیست');
  $txt = trim((string)($in['txt'] ?? ''));
  if ($txt === '') throw new Exception('متن پیامک خالی است');
  if (mb_strlen($txt) > SMS_TXT_MAX) throw new Exception('متن پیامک حداکثر ' . SMS_TXT_MAX . ' نویسه است');
  $r = sms_send_batch($c, [$phone], $txt, (string)($db['menu']['brand']['name'] ?? 'کافه'));
  if (!$r['ok']) throw new Exception($r['msg']);
  return ['sent' => 1, 'msg' => 'پیامک به ' . $phone . ' ارسال شد'];
}
