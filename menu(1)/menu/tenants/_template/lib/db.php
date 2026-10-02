<?php
/* ═══════════════════════════════════════════════════════════════
   فروشگاه تراکنشی کافه — نسخهٔ ۵ (بانک اطلاعاتی دودرایور)
   · API کاملاً سازگار با نسخهٔ JSON:  t_db_get / t_db_txn / t_norm …
   · پشتیبانی: SQLite (پیش‌فرض خودکار) و MySQL (با db-config.json)
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/class-gstore.php';

define('T_ROOT', dirname(__DIR__, 2));            // tenants/<slug>  (یا ریشه در نصب آزمایشی)
define('T_DATA', T_ROOT . '/data');               // پوشهٔ داده
define('T_SLUG', basename(T_ROOT));

/* سند کامل کافه — خواندنی */
function t_db_get(): array {
  return t_store()->get();
}

/* خواندن + تغییر + نوشتن اتمیک زیر تراکنش — هیچ فراخوانی تودرتو ممنوع */
function t_db_txn(callable $fn): array {
  return t_store()->txn($fn);
}

/* JSON خام سند (برای اسکن رسانه‌ها / خروجی گرفتن) */
function t_db_raw(): string {
  return t_store()->rawJson();
}

/* دسترسی مستقیم به فروشگاه (برای ابزارهای پلتفرم/نصب) */
function t_store(): GStore {
  return GStore::for(T_ROOT, 't_norm');
}

function t_norm(array $db): array {
  foreach (['orders','customers','invoices','tables','customPalettes','reviews','promos','users','reservations'] as $k)
    if (!isset($db[$k]) || !is_array($db[$k])) $db[$k] = [];
  if (!isset($db['menu']) || !is_array($db['menu'])) $db['menu'] = [];
  $m = $db['menu'];
  foreach (['brand','theme'] as $k) if (!isset($m[$k]) || !is_array($m[$k])) $m[$k] = [];
  foreach (['banners','cats'] as $k) if (!isset($m[$k]) || !is_array($m[$k])) $m[$k] = [];
  foreach ($m['cats'] as &$c) {
    if (!isset($c['items']) || !is_array($c['items'])) $c['items'] = [];
    foreach ($c['items'] as &$i) {
      if (!isset($i['opts']) || !is_array($i['opts'])) $i['opts'] = [];
      if (!isset($i['tags']) || !is_array($i['tags'])) $i['tags'] = [];
      $i['tags']['off'] = (int)($i['tags']['off'] ?? 0);
    }
  }
  $db['menu'] = $m;
  if (!isset($db['settings']) || !is_array($db['settings'])) $db['settings'] = [];
  if (!isset($db['counters']['order'])) $db['counters']['order'] = 1000;
  return $db;
}

function t_find_item(array $db, string $id): ?array {
  foreach ($db['menu']['cats'] as $c)
    foreach ($c['items'] as $i)
      if (($i['id'] ?? '') === $id) return $i;
  return null;
}
function t_eff_price(array $it): int {
  $off = (int)($it['tags']['off'] ?? 0);
  return $off ? max(1000, (int)round(((int)$it['price']) * (100 - $off) / 100)) : (int)($it['price'] ?? 0);
}
/* میانگین واقعی آماده‌سازی از ۲۰ سفارش آخر (ETA) */
function t_eta(array $db): ?int {
  $ds = [];
  foreach ($db['orders'] as $o)
    if (!empty($o['kts']) && !empty($o['rdy']) && $o['rdy'] >= $o['kts']) $ds[] = $o['rdy'] - $o['kts'];
  if (!$ds) return null;
  $ds = array_slice($ds, -20);
  return max(1, (int)round(array_sum($ds) / count($ds) / 60000));
}
