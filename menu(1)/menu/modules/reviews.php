<?php
declare(strict_types=1);

/* ثبت نظر — عمومی؛ تا تأیید مدیر پنهان است.
   v5.0.1: پاسخ = وضعیت پاک‌سازی‌شده (بدون هش رمز) — هرگز سند خام.
   v5.0.3: 🔒 پاسخ = فقط تأیید — قبلاً t_state_admin برمی‌گشت که «عمومی» بود و کل
   سند (سفارش‌ها، مشتریان، پرسنل و هش رمزها) به هر بازدیدکننده ناشناس لو می‌رفت.
   کلاینتِ review-send پاسخ را مصرف نمی‌کند (فایل actions.js). */
function review_add(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $txt = mb_substr(trim((string)($in['txt'] ?? '')), 0, 120);
    if (mb_strlen($txt) < 3) throw new Exception('متن نظر حداقل ۳ حرف باشد');
    $db['reviews'][] = [
      'id' => 'x' . bin2hex(random_bytes(4)),
      'itemId' => (string)($in['itemId'] ?? ''),
      'oid' => $in['oid'] ?? null,
      'by' => mb_substr(trim((string)($in['by'] ?? 'مشتری')), 0, 40),
      'stars' => min(5, max(1, (int)($in['stars'] ?? 5))),
      'txt' => $txt, 'ok' => false, 'ts' => round(microtime(true) * 1000),
    ];
    return ['ok' => true];   /* v5.0.3: عمومی — هرگز سند کامل برنگردد */
  });
}
function review_toggle(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    foreach ($db['reviews'] as &$r)
      if (($r['id'] ?? '') === ($in['id'] ?? '')) { $r['ok'] = !$r['ok']; return t_state_admin($db); }
    throw new Exception('نظر یافت نشد');
  });
}
function review_del(array $in): array {
  return t_db_txn(function (array &$db) use ($in) {
    $db['reviews'] = array_values(array_filter($db['reviews'],
      fn($r) => ($r['id'] ?? '') !== ($in['id'] ?? '')));
    return t_state_admin($db);
  });
}
