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
  /* v5.0.4-test.6: شمارهٔ اشتراک ۱..۵ رقمی برای هر عضو باشگاه —
     شمارندهٔ یکتای هر کافه + پش‌ساخت خودکار اعضای قدیمی به ترتیب عضویت.
     t_norm روی هر خواندن/نوشتن اجرا می‌شود و تعیین شماره قطعی (بر اساس ترتیب آرایه)
     است؛ یعنی پیش از هر نوشتن هم همان شماره‌ها ماندگار می‌شوند — بدون تداخل. */
  if (!isset($db['counters']['cust'])) $db['counters']['cust'] = 0;
  foreach ($db['customers'] as &$c)
    if (!isset($c['sub_no']) || (int)($c['sub_no'] ?? 0) <= 0) $c['sub_no'] = ++$db['counters']['cust'];
  unset($c);
  /* v5.0.5-test.1: صندوق پیام‌های سیستم (نوتیفیکیشن) — همهٔ نقش‌ها */
  if (!isset($db['notifs']) || !is_array($db['notifs'])) $db['notifs'] = [];
  if (count($db['notifs']) > 200) $db['notifs'] = array_slice($db['notifs'], -200);
  /* v5.0.5-test.1: پیکربندی باشگاه مشتریان — نرخ صدور امتیاز، ارزش هر امتیاز
     و سقف مصرف (درصد سبد) — همه توسط مدیر کافه از تنظیمات قابل ویرایش */
  if (!isset($db['settings']['points']) || !is_array($db['settings']['points'])) $db['settings']['points'] = [];
  $pt = &$db['settings']['points'];
  $pt['on']     = !isset($pt['on']) ? true : (bool)$pt['on'];
  $pt['earn']   = max(1000, (int)($pt['earn'] ?? 10000));    /* هر X تومان خرید = ۱ امتیاز */
  $pt['value']  = max(500, (int)($pt['value'] ?? 1000));     /* هر امتیاز = X تومان تخفیف */
  $pt['maxPct'] = max(5, min(100, (int)($pt['maxPct'] ?? 50)));
  unset($pt);
  /* v5.0.5-test.5: پیکربندی پنل پیامکی هر کافه — کلیدها فقط همین‌جا می‌مانند */
  if (!isset($db['sms']) || !is_array($db['sms'])) $db['sms'] = [];
  $sm = &$db['sms'];
  foreach (['prov', 'key', 'user', 'pass', 'line', 'welTxt', 'hookUrl', 'hookMethod', 'hookHd', 'hookBody'] as $k)
    if (!isset($sm[$k]) || !is_string($sm[$k])) $sm[$k] = '';
  foreach (['en', 'auto', 'wel'] as $k) $sm[$k] = !empty($sm[$k]);
  if (!isset($sm['q']) || !is_array($sm['q'])) $sm['q'] = [];
  if (count($sm['q']) > 200) $sm['q'] = array_slice($sm['q'], -200);
  if (!isset($sm['st']) || !is_array($sm['st'])) $sm['st'] = [];
  unset($sm);
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
