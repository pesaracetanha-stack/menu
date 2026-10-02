<?php
declare(strict_types=1);
/* ═══════════════════════════════════════════════════════════════
   دانلود اکسل — فقط مدیر کافه (v5.0.4-test.1 / test.5)
   ?get=template   → قالب خالی منو با ۲ ردیف مثال + شیت راهنما
   ?get=sample     → منوی نمونهٔ کامل (قابل ایمپورت مستقیم برای تست)
   ?get=customers  → خروجی باشگاه مشتریان برای پنل پیامکی (v5.0.4-test.5)
   ═══════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/modules/excel.php';

t_require_admin();

$get = $_GET['get'] ?? '';
if ($get !== 'template' && $get !== 'sample' && $get !== 'customers') {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(404);
  echo json_encode(['ok' => false, 'err' => 'درخواست نامعتبر'], JSON_UNESCAPED_UNICODE);
  exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'xl_');
if (!$tmp) { http_response_code(500); exit; }

if ($get === 'template') {
  xl_template_xlsx($tmp);
  $name = 'قالب-منو.xlsx';
} elseif ($get === 'sample') {
  xl_sample_xlsx($tmp);
  $name = 'منوی-نمونه.xlsx';
} else {
  /* v5.0.4-test.5: باشگاه مشتریان — از دیتای زندهٔ همین لحظه */
  $db = t_db_get();
  xl_customers_xlsx($tmp, is_array($db['customers'] ?? null) ? $db['customers'] : [],
                         is_array($db['orders'] ?? null) ? $db['orders'] : []);
  $name = 'مشتریان-باشگاه.xlsx';
}

header('Content-Type: ' . XL_MIME);
header('Content-Length: ' . (string)filesize($tmp));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($tmp);
@unlink($tmp);
exit;
