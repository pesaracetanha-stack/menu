<?php
declare(strict_types=1);
/* بازگشت از زرین‌پال */

$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}

$root = dirname(__DIR__, 2);
require_once $root . '/api/lib/zarinpal.php';

/* پارامترهای بازگشتی */
$authority = (string)($_GET['Authority'] ?? $_GET['authority'] ?? '');
$status = (string)($_GET['Status'] ?? $_GET['status'] ?? '');

if ($authority === '') {
  header('Location: renew.php?failed=1'); exit;
}

$tx = zp_tx_find_by_authority($authority);
if ($tx === null) {
  header('Location: renew.php?failed=1'); exit;
}

/* اگر قبلاً پرداخت شده — برو به صفحه موفقیت */
if (($tx['status'] ?? '') === 'paid') {
  header('Location: renew.php?success=1&ref=' . urlencode((string)($tx['ref_id'] ?? ''))); exit;
}

/* کاربر لغو کرد */
if (strtoupper($status) !== 'OK') {
  zp_tx_update((int)$tx['id'], ['status' => 'cancelled', 'cancelled_at' => time()]);
  header('Location: renew.php?failed=1'); exit;
}

/* تأیید */
$client = zp_client();
if ($client === null) {
  header('Location: renew.php?failed=1'); exit;
}

$amountRial = (int)($tx['amount_rial'] ?? 0);
$r = $client->verify($amountRial, $authority);

if (!$r['ok']) {
  zp_tx_update((int)$tx['id'], ['status' => 'failed_verify', 'error' => $r['error'] ?? '']);
  header('Location: renew.php?failed=1'); exit;
}

/* ثبت پرداخت */
zp_tx_update((int)$tx['id'], [
  'status'   => 'paid',
  'ref_id'   => $r['ref_id'] ?? '',
  'card_pan' => $r['card_pan'] ?? '',
  'paid_at'  => time(),
]);

/* ─── تمدید لایسنس کافه ─── */
$days = (int)($tx['days'] ?? 30);
$plan = (string)($tx['plan'] ?? 'basic');

try {
  $dbf = __DIR__ . '/data/db.sqlite';
  if (is_file($dbf)) {
    require_once $root . '/api/lib/class-gstore.php';
    $store = GStore::for(__DIR__);
    $store->txn(function (array &$db) use ($days, $plan) {
      if (empty($db)) return $db;
      if (!isset($db['license'])) $db['license'] = [];
      $now = round(microtime(true) * 1000);
      $currentExpires = (int)($db['license']['paid_until'] ?? $db['license']['expires'] ?? 0);
      $base = max($currentExpires, $now);
      $db['license']['status'] = 'active';
      $db['license']['plan'] = $plan;
      $db['license']['paid_until'] = $base + ($days * 86400000);
      $db['license']['last_payment'] = $now;
      return $db;
    });
  }
} catch (Throwable $e) {
  /* لاگ اما کاربر را به صفحه موفقیت بفرست چون پول گرفته شده */
  error_log('[gitiarts] license renew failed: ' . $e->getMessage());
}

header('Location: renew.php?success=1&ref=' . urlencode((string)($r['ref_id'] ?? '')));
exit;