<?php
/* ═══════════════════════════════════════════════════════════════
   v5.0.6: درگاه پرداخت زرین‌پال — هر کافه مرچنت خودش
   - این ماژول فقط تنظیمات و تست اتصال را مدیریت می‌کند
   - پرداخت واقعی در فاز بعدی اضافه می‌شود
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const PAY_PROVS = ['zarinpal'];
const PAY_PROV_FA = ['zarinpal' => 'زرین‌پال'];

/* ── پیکربندی ── */
function pay_cfg(array $db): array {
  $c = is_array($db['payment'] ?? null) ? $db['payment'] : [];
  $d = [
    'en'         => false,
    'prov'       => 'zarinpal',
    'merchant'   => '',
    'sandbox'    => false,
    'st'         => [],
  ];
  foreach ($d as $k => $v) if (!array_key_exists($k, $c)) $c[$k] = $v;
  if (!in_array($c['prov'], PAY_PROVS, true)) $c['prov'] = 'zarinpal';
  foreach (['en', 'sandbox'] as $k) $c[$k] = !empty($c[$k]);
  foreach (['merchant'] as $k) $c[$k] = (string)$c[$k];
  if (!is_array($c['st'])) $c['st'] = [];
  return $c;
}

/* ── ماسک کردن مرچنت برای نمایش ── */
function pay_mask(string $s): string {
  $s = (string)$s;
  if (strlen($s) < 8) return $s === '' ? '' : '****';
  return substr($s, 0, 6) . str_repeat('•', 12) . substr($s, -6);
}

/* ── نسخه عمومی برای مرورگر (بدون مرچنت واقعی) ── */
function pay_pub(array $c): array {
  return [
    'en'       => $c['en'],
    'prov'     => $c['prov'],
    'provFa'   => PAY_PROV_FA[$c['prov']] ?? $c['prov'],
    'merchant' => '',
    'merchantMasked' => $c['merchant'] !== '' ? pay_mask($c['merchant']) : '',
    'merchantSet'    => $c['merchant'] !== '',
    'sandbox'  => $c['sandbox'],
    'st'       => $c['st'],
  ];
}

/* ── وضعیت (GET) ── */
function payment_state($in): array {
  $db = t_db_get();
  $c  = pay_cfg($db);
  return ['cfg' => pay_pub($c)];
}

/* ── ذخیره تنظیمات (POST) ── */
function payment_cfg_save(array $in): array {
  $db = t_db_get();
  $c  = pay_cfg($db);

  if (isset($in['en']))      $c['en']      = !empty($in['en']);
  if (isset($in['sandbox'])) $c['sandbox'] = !empty($in['sandbox']);
  if (isset($in['prov']) && in_array($in['prov'], PAY_PROVS, true)) $c['prov'] = (string)$in['prov'];

  /* مرچنت — اگر رشته خالی نبود و ماسک نبود، جایگزین کن */
  if (array_key_exists('merchant', $in)) {
    $m = preg_replace('/\s+/', '', (string)$in['merchant']);
    if ($m !== '' && strpos($m, '•') === false) {
      if (!preg_match('/^[A-Za-z0-9\-]{36}$/', $m)) {
        throw new Exception('مرچنت کد باید ۳۶ کاراکتر باشد (حروف، اعداد یا خط‌تیره)');
      }
      $c['merchant'] = $m;
    }
  }

  d_save('payment', $c);
  return ['cfg' => pay_pub($c), 'msg' => 'تنظیمات پرداخت ذخیره شد'];
}

/* ── تست اتصال (POST) ── */
function payment_test(array $in): array {
  $db = t_db_get();
  $c  = pay_cfg($db);

  if ($c['merchant'] === '') {
    throw new Exception('ابتدا مرچنت کد را وارد کنید');
  }

  /* در حالت sandbox فقط فرمت را چک می‌کنیم (بدون تماس واقعی با زرین‌پال) */
  if ($c['sandbox']) {
    $c['st']['lastTest'] = [
      'ok'  => true,
      'msg' => 'حالت تست — فرمت مرچنت کد معتبر است (بدون تماس واقعی با زرین‌پال)',
      'ts'  => time(),
    ];
    d_save('payment', $c);
    return ['cfg' => pay_pub($c), 'msg' => $c['st']['lastTest']['msg']];
  }

  /* حالت واقعی: یک درخواست پرداخت آزمایشی به زرین‌پال بفرست و لغو کن */
  $url = 'https://api.zarinpal.com/pg/v4/payment/request.json';
  $body = json_encode([
    'merchant_id'  => $c['merchant'],
    'amount'       => 1000,  /* ۱ تومان برای تست */
    'callback_url' => 'https://example.com/callback',
    'description'  => 'تست اتصال از GitiArts',
  ], JSON_UNESCAPED_UNICODE);

  $ctx = stream_context_create(['http' => [
    'method'  => 'POST',
    'timeout' => 10,
    'ignore_errors' => true,
    'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
    'content' => $body,
  ]]);
  $res = @file_get_contents($url, false, $ctx);

  if ($res === false) {
    $c['st']['lastTest'] = ['ok' => false, 'msg' => 'ارتباط با زرین‌پال برقرار نشد — اینترنت سرور را بررسی کنید', 'ts' => time()];
    d_save('payment', $c);
    return ['cfg' => pay_pub($c), 'msg' => $c['st']['lastTest']['msg']];
  }

  $j = json_decode($res, true);
  $code = (int)($j['data']['code'] ?? 0);
  $ok = ($code === 100) || ($code === 101);

  $msg = $ok
    ? 'مرچنت کد معتبر است — اتصال به زرین‌پال با موفقیت برقرار شد'
    : 'مرچنت کد نامعتبر است یا حساب شما هنوز تأیید نشده';

  $c['st']['lastTest'] = ['ok' => $ok, 'msg' => $msg, 'ts' => time()];
  d_save('payment', $c);

  return ['cfg' => pay_pub($c), 'msg' => $msg];
}