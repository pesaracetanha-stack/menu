<?php
declare(strict_types=1);

/* ═══ v5.0.5-test.1: صندوق پیام‌های سیستم — تولید رویداد برای همهٔ نقش‌ها ═══
   to = admin | cashier | kitchen | customer (customer همراه با phone) */
function t_notify(array &$db, string $to, string $title, string $body = '', ?string $phone = null): void {
  $db['notifs'][] = [
    'id'    => 'n' . bin2hex(random_bytes(4)),
    'ts'    => round(microtime(true) * 1000),
    'to'    => $to,
    'phone' => $phone,
    'title' => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $title)), 0, 80),
    'body'  => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $body)), 0, 200),
    'read'  => false,
  ];
  if (count($db['notifs']) > 200) $db['notifs'] = array_slice($db['notifs'], -200);
}

/* پیکربندی امتیاز باشگاه — با پیش‌فرض امن (v5.0.5-test.1: کاملاً توسط مدیر قابل ویرایش) */
function t_pts(array $S): array {
  $p = is_array($S['points'] ?? null) ? $S['points'] : [];
  return [
    'on'     => !isset($p['on']) ? true : (bool)$p['on'],
    'earn'   => max(1000, (int)($p['earn'] ?? 10000)),
    'value'  => max(500, (int)($p['value'] ?? 1000)),
    'maxPct' => max(5, min(100, (int)($p['maxPct'] ?? 50))),
  ];
}

/* ثبت سفارش — عمومی؛ قیمت‌ها سمت سرور بازمحاسبه می‌شوند
   v5.0.5-test.1: مشتری فقط «پرداخت آنلاین» دارد — روش‌های نقدی/کارتخوان فقط از صندوق
   (نشست پرسنل/مدیر کافه) قابل انتخاب‌اند؛ درخواست عمومی همیشه method=online می‌شود */
function order_create(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $lic = t_license($db);
    if ($lic['status'] === 'suspended') throw new Exception('سفارش‌گیری این کافه موقتاً غیرفعال است');
    if (!$lic['ok']) throw new Exception('دورهٔ آزمایشی به پایان رسیده — منو فعلاً فقط نمایشی است');

    $itemsIn = $in['items'] ?? [];
    if (!is_array($itemsIn) || !count($itemsIn)) throw new Exception('سبد خالی است');

    /* ساخت خط‌ها با قیمت از منو */
    $lines = []; $sub = 0;
    foreach ($itemsIn as $l) {
      $it = t_find_item($db, (string)($l['id'] ?? ''));
      if (!$it) throw new Exception('یکی از آیتم‌ها در منو نیست');
      if (!empty($it['tags']['soldout'])) throw new Exception('«' . $it['name'] . '» ناموجود است');
      $qty   = max(1, (int)($l['qty'] ?? 1));
      $price = t_eff_price($it);
      $opts  = [];
      foreach (($l['opts'] ?? []) as $oid)
        foreach ($it['opts'] as $o)
          if (($o['id'] ?? '') === $oid)
            $opts[] = ['name' => (string)$o['name'], 'price' => (int)($o['price'] ?? 0)];
      $optSum = array_sum(array_column($opts, 'price'));
      $sub   += ($price + $optSum) * $qty;
      $lines[] = ['id' => $it['id'], 'name' => $it['name'], 'price' => $price, 'qty' => $qty, 'opts' => $opts];
    }

    $S = $db['settings'];

    /* کد تخفیف */
    $promoAmt = 0; $promoCode = null;
    $code = strtoupper(trim((string)($in['promo'] ?? '')));
    if ($code !== '')
      foreach ($db['promos'] as $p)
        if (($p['on'] ?? true) && strtoupper((string)($p['code'] ?? '')) === $code) {
          $promoCode = $p['code'];
          $promoAmt  = (int)round($sub * (int)($p['off'] ?? 0) / 100);
          break;
        }

    /* ساعت خوش */
    $h = (int)date('G'); $hhAmt = 0;
    if (!empty($S['hh']['on']) && $h >= (int)($S['hh']['from'] ?? 0) && $h < (int)($S['hh']['to'] ?? 0))
      $hhAmt = (int)round(($sub - $promoAmt) * (int)($S['hh']['off'] ?? 0) / 100);

    /* امتیاز باشگاه — v5.0.5-test.1: نرخ و ارزش و سقف از تنظیمات مدیر کافه */
    $PT = t_pts($S);
    $phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
    $cust  = null; $custIdx = null;
    foreach ($db['customers'] as $k => $c)
      if (($c['phone'] ?? '') === $phone) { $cust = $db['customers'][$k]; $custIdx = $k; break; }
    $remain = max(0, $sub - $promoAmt - $hhAmt);
    $ptsCeil = $PT['on'] ? (int)floor($remain * $PT['maxPct'] / 100 / $PT['value']) : 0;
    $maxPts = ($cust && $PT['on']) ? min((int)$cust['points'], $ptsCeil) : 0;
    $ptsAmt = !empty($in['usePts']) ? $maxPts * $PT['value'] : 0;
    $disc   = min($sub, $promoAmt + $hhAmt + $ptsAmt);

    /* v5.0.4-test.6: لوکیشن/آدرس اختیاری — برای سفارش از راه دور (پیک/بیرون‌بر)؛
       نویسهٔ کنترلی حذف و به ۳۰۰ نویسه محدود می‌شود */
    $loc = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['loc'] ?? ''))), 0, 300);
    /* شمارهٔ اشتراک ۱..۵ رقمی — عضو قدیمی از پرونده‌اش، عضو تازه = شمارنده + ۱
       (t_norm اعضای بدون شماره را پیش از این پش‌ساخت کرده است) */
    $subNo = $cust ? (int)($cust['sub_no'] ?? 0) : ($phone !== '' ? (int)$db['counters']['cust'] + 1 : 0);

    /* مالیات و انعام — v5.0.4-test.4: انعام در هر دو روش پرداخت؛ درصد (حداکثر ۳۰٪) یا مبلغ دلخواه
       (سقف = مبلغ سفارش) + توضیح کوتاه انعام */
    $vat = !empty($S['vatOn']) ? (int)round(($sub - $disc) * (int)($S['vatPct'] ?? 0) / 100) : 0;
    $base = max(0, $sub - $disc);
    $tip = 0;
    if (!empty($S['tipOn'])) {
      $tipFix = (int)($in['tipFix'] ?? 0);
      if ($tipFix > 0) {
        $tip = min(max(0, $tipFix), $base);
      } else {
        $tipPct = max(0, min(30, (int)($in['tip'] ?? 0)));
        $tip = (int)round($base * $tipPct / 100);
      }
    }
    $total = $sub - $disc + $vat + $tip;

    /* v5.0.5-test.1: روش پرداخت — فقط سفارشِ همراه با نشست کافه (صندوق/مدیر) می‌تواند
       نقدی/کارتخوان باشد؛ درخواست عمومیِ مشتری همیشه «درگاه آنلاین» است.
       روش «counter» (پرداخت در صندوق) از گردش مشتری حذف شد و دیگر ساخته نمی‌شود. */
    $isStaff = function_exists('t_is_tenant') && t_is_tenant();
    if ($isStaff) {
      $method = in_array(($in['method'] ?? ''), ['cash', 'card', 'online'], true) ? $in['method'] : 'online';
    } else {
      $method = 'online';
    }
    $tipNote = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['tipNote'] ?? ''))), 0, 140);
    $now    = round(microtime(true) * 1000);
    /* صدور امتیاز فقط برای پرداخت قطعی (نقدی/کارتخوانِ صندوق یا درگاه آنلاین) */
    $earned = ($phone !== '' && $PT['on']) ? (int)floor($total / $PT['earn']) : 0;

    $o = [
      'id' => 'x' . bin2hex(random_bytes(4)),
      'num' => (int)$db['counters']['order']++,
      'ts' => $now, 'kts' => $now,
      'items' => $lines,
      'sub' => $sub, 'promoCode' => $promoCode, 'promoAmt' => $promoAmt, 'hhAmt' => $hhAmt,
      'discount' => $ptsAmt, 'vat' => $vat, 'tip' => $tip, 'tipNote' => $tipNote !== '' ? $tipNote : null, 'total' => $total,
      'method' => $method, 'phone' => $phone ?: null,
      'name' => mb_substr(trim((string)($in['name'] ?? '')), 0, 60),
      'loc' => $loc !== '' ? $loc : null, 'custNo' => $subNo,
      'earned' => $earned,
      'status' => 'preparing',
      'ready' => false, 'rdy' => 0, 'dts' => 0, 'rated' => 0,
      'source' => $isStaff ? 'cashier' : 'customer', 'cashier' => null,
    ];

    /* میز → در انتظار تحویل */
    $tno = (int)($in['tableNo'] ?? 0);
    if ($tno) foreach ($db['tables'] as &$tb)
      if ((int)($tb['no'] ?? 0) === $tno) {
        $o['tableId'] = $tb['id']; $o['tableNo'] = $tno; $tb['status'] = 'waiting';
      }
    unset($tb);

    /* کسر موجودی */
    foreach ($lines as $l)
      foreach ($db['menu']['cats'] as &$c)
        foreach ($c['items'] as &$it2)
          if (($it2['id'] ?? '') === $l['id'] && isset($it2['stock']) && $it2['stock'] !== null) {
            $it2['stock'] = max(0, (int)$it2['stock'] - $l['qty']);
            if ($it2['stock'] === 0 && isset($it2['tags'])) $it2['tags']['soldout'] = true;
          }
    unset($c, $it2);

    $db['orders'][] = $o;

    /* امتیاز باشگاه + شمارهٔ اشتراک (v5.0.4-test.6) */
    if ($cust) {
      $db['customers'][$custIdx]['points'] =
        max(0, (int)$cust['points'] - (int)($ptsAmt / $PT['value']) + $earned);
      if ($loc !== '') $db['customers'][$custIdx]['loc'] = $loc;   /* آخرین لوکیشن */
    } elseif ($phone !== '') {
      $db['counters']['cust'] = $subNo;   /* رزرو شماره — ۱ تا ۵ رقم، بدون تداخل */
      $db['customers'][] = ['phone' => $phone,
        'name' => mb_substr(trim((string)($in['name'] ?? 'مشتری')), 0, 60),
        'points' => $earned, 'since' => $now,
        'sub_no' => $subNo, 'loc' => $loc !== '' ? $loc : null];
      /* v5.0.5-test.5: عضو تازه → صف همگام‌سازی خودکار پنل پیامکی (فقط ثبت، بدون HTTP) */
      if (function_exists('sms_queue_member')) sms_queue_member($db, $phone);
    }

    /* ═══ v5.0.5-test.1: پیام‌های مرتبط به همهٔ نقش‌ها ═══ */
    $nt = 'سفارش #' . number_format($o['num']);
    t_notify($db, 'cashier', '💰 ' . $nt . ' — پرداخت آنلاین شد',
      'مبلغ ' . number_format($total) . ' تومان از درگاه دریافت شد' . (($o['tableNo'] ?? 0) ? ' — میز ' . (int)$o['tableNo'] : ''));
    t_notify($db, 'kitchen', '🍳 ' . $nt . ' وارد آشپزخانه شد',
      count($lines) . ' قلم — جمع ' . number_format($total) . ' تومان');
    t_notify($db, 'admin', '🧾 ' . $nt . ' ثبت شد',
      'روش: درگاه آنلاین · مبلغ ' . number_format($total) . ' تومان');
    if ($phone !== '')
      t_notify($db, 'customer', '✅ سفارش شما ثبت شد',
        'کد سفارش #' . number_format($o['num']) . ' — در حال آماده‌سازی', $phone);

    return ['num' => $o['num'], 'total' => $total, 'earned' => $earned, 'status' => $o['status'],
            'subno' => $subNo ?: null];
  });
}

function &_find_order(array &$db, string $id): array {
  foreach ($db['orders'] as $i => $o)
    if (($o['id'] ?? '') === $id) return $db['orders'][$i];
  throw new Exception('سفارش یافت نشد');
}

/* دریافت وجه در صندوق (فروش حضوری نقدی/کارتخوان) */
function order_pay(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $o = &_find_order($db, (string)($in['id'] ?? ''));
    $o['status'] = 'preparing'; $o['ready'] = false;
    $o['kts'] = round(microtime(true) * 1000); $o['method'] = 'cash';
    /* v5.0.5-test.1: پیام به آشپزخانه + مشتری */
    t_notify($db, 'kitchen', '🍳 سفارش #' . number_format((int)$o['num']) . ' وارد آشپزخانه شد',
      'وجه در صندوق دریافت شد — ' . count($o['items']) . ' قلم');
    if (!empty($o['phone']))
      t_notify($db, 'customer', '✅ وجه سفارش شما دریافت شد',
        'کد سفارش #' . number_format((int)$o['num']) . ' — در حال آماده‌سازی', (string)$o['phone']);
    return t_state_out($db);
  });
}
/* آشپزخانه: آماده شد */
function order_ready(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $o = &_find_order($db, (string)($in['id'] ?? ''));
    $o['ready'] = true; $o['rdy'] = round(microtime(true) * 1000);
    /* v5.0.5-test.1: خبر آماده‌شدن به صندوق + مشتری */
    t_notify($db, 'cashier', '🛎 سفارش #' . number_format((int)$o['num']) . ' آمادهٔ تحویل است',
      'آشپزخانه «آماده شد» را ثبت کرد');
    if (!empty($o['phone']))
      t_notify($db, 'customer', '🛎 سفارش شما آماده شد',
        'کد سفارش #' . number_format((int)$o['num']) . ' — نوش جان!', (string)$o['phone']);
    return t_state_out($db);
  });
}
/* تحویل در صندوق */
function order_deliver(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $o = &_find_order($db, (string)($in['id'] ?? ''));
    $o['status'] = 'done'; $o['dts'] = round(microtime(true) * 1000);
    if (!empty($o['tableId']))
      foreach ($db['tables'] as &$t)
        if (($t['id'] ?? '') === $o['tableId']) $t['status'] = 'served';
    unset($t);
    if (!empty($o['phone']))
      t_notify($db, 'customer', '🎉 سفارش شما تحویل شد',
        'کد سفارش #' . number_format((int)$o['num']) . ' — امتیاز باشگاه اعمال شد', (string)$o['phone']);
    return t_state_out($db);
  });
}

/* ═══ v5.0.5-test.1: سیستم رزرو میز — عمومی (مشتری) + کنترل صندوق ═══ */

/* درخواست رزرو از سمت مشتری — عمومی؛ وضعیت اولیه = pending (در انتظار تأیید صندوق) */
function reserve_create(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $lic = t_license($db);
    if ($lic['status'] === 'suspended' || !$lic['ok'])
      throw new Exception('رزرو میز این کافه فعلاً غیرفعال است');

    $name = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['name'] ?? ''))), 0, 60);
    $phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
    $time = trim((string)($in['time'] ?? ''));
    $note = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['note'] ?? ''))), 0, 160);
    $persons = max(1, min(40, (int)($in['persons'] ?? 2)));

    if (mb_strlen($name) < 2) throw new Exception('نام خود را وارد کنید');
    if (!preg_match('/^09\d{9}$/', $phone)) throw new Exception('شمارهٔ موبایل معتبر نیست — مثل ۰۹۱۲۱۲۳۴۵۶۷');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) throw new Exception('ساعت رزرو را درست انتخاب کنید');

    /* تاریخ — پیش‌فرض امروز؛ حداکثر ۳۰ روز آینده (فرمت yyyy-mm-dd) */
    $date = trim((string)($in['date'] ?? ''));
    $ts = null;
    if ($date !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
      $stamp = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
      if ($stamp === false) throw new Exception('تاریخ معتبر نیست');
      $ts = $stamp * 1000;
    }
    if ($ts === null) $ts = strtotime('today') * 1000;
    if ($ts < strtotime('today') * 1000) throw new Exception('تاریخ رزرو نمی‌تواند در گذشته باشد');
    if ($ts > (strtotime('today') + 30 * 86400) * 1000) throw new Exception('رزرو فقط تا ۳۰ روز آینده ممکن است');

    [$hh, $mm] = array_map('intval', explode(':', $time));
    if ($hh > 23 || $mm > 59) throw new Exception('ساعت رزرو معتبر نیست');
    $ts += $hh * 3600e3 + $mm * 60e3;

    $r = [
      'id' => 'r' . bin2hex(random_bytes(4)),
      'ts' => $ts, 'created' => round(microtime(true) * 1000),
      'tableId' => null, 'tableNo' => 0,
      'name' => $name, 'phone' => $phone,
      'persons' => $persons, 'note' => $note !== '' ? $note : null,
      'status' => 'pending', 'src' => 'customer',
    ];
    $db['reservations'][] = $r;

    /* پیام به مدیر و صندوق: رزرو جدید در انتظار تأیید */
    t_notify($db, 'cashier', '📅 رزرو جدید — ' . $name,
      'تاریخ ' . $date . ' ساعت ' . $time . ' · ' . $persons . ' نفر' . ($note !== '' ? ' · ' . $note : ''));
    t_notify($db, 'admin', '📅 رزرو جدید — ' . $name,
      $persons . ' نفر · ساعت ' . $time . ($note !== '' ? ' · ' . $note : ''));

    return ['id' => $r['id'], 'ts' => $r['ts'], 'status' => $r['status']];
  });
}

/* کنترل رزرو توسط صندوق/مدیر — تأیید، نشستن، لغو، عدم حضور */
function reserve_set(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $id = (string)($in['id'] ?? '');
    $st = (string)($in['st'] ?? '');
    if (!in_array($st, ['active', 'done', 'cancel', 'noshow'], true))
      throw new Exception('وضعیت نامعتبر است');
    $r = null;
    foreach ($db['reservations'] as $i => $x)
      if (($x['id'] ?? '') === $id) { $r = &$db['reservations'][$i]; break; }
    if (!$r) throw new Exception('رزرو یافت نشد');

    $r['status'] = $st;
    $label = ['active' => 'تأیید شد', 'done' => 'مهمان نشست', 'cancel' => 'لغو شد', 'noshow' => 'عدم حضور'][$st];

    /* تخصیص میز هنگام تأیید — اگر میزی مشخص نشده، اولین میز خالی */
    if ($st === 'active') {
      $tid = (string)($in['tableId'] ?? '');
      $tb = null;
      if ($tid !== '') {
        foreach ($db['tables'] as &$t)
          if (($t['id'] ?? '') === $tid) { $tb = &$t; break; }
        unset($t);
      } else {
        foreach ($db['tables'] as &$t)
          if (($t['status'] ?? '') === 'free') { $tb = &$t; break; }
        unset($t);
      }
      if ($tb) {
        $r['tableId'] = $tb['id'];
        $r['tableNo'] = (int)$tb['no'];
        $tb['status'] = 'reserved';
      }
    } elseif ($st === 'done') {
      /* مهمان نشست — میز از «رزرو» به «در انتظار سفارش» می‌رود */
      if (!empty($r['tableId']))
        foreach ($db['tables'] as &$t)
          if (($t['id'] ?? '') === $r['tableId'] && ($t['status'] ?? '') === 'reserved') $t['status'] = 'ordering';
      unset($t);
    } elseif ($st === 'cancel' || $st === 'noshow') {
      /* آزاد کردن میزِ رزرو‌شدهٔ همین رزرو */
      if (!empty($r['tableId']))
        foreach ($db['tables'] as &$t)
          if (($t['id'] ?? '') === $r['tableId'] && ($t['status'] ?? '') === 'reserved') $t['status'] = 'free';
      unset($t);
    }

    /* پیام به مشتری */
    if (!empty($r['phone']))
      t_notify($db, 'customer', '📅 رزرو شما ' . $label,
        'کافه ' . ($db['menu']['brand']['name'] ?? '') . ' — ' . (int)($r['persons'] ?? 0) . ' نفره'
        . (!empty($r['tableNo']) ? ' · میز ' . (int)$r['tableNo'] : ''), (string)$r['phone']);

    return t_state_out($db);
  });
}
