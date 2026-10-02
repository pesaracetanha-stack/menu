<?php
/* ═══════════════════════════════════════════════════════════════
   دانلود فایل نصبی با لینک یک‌بارمصرف — نسخهٔ ۱
   · توکن ۶۴کاراکتری · هش‌شده ذخیره می‌شود · ۷۲ ساعت اعتبار
   · فقط یک بار قابل استفاده · همه‌چیز در audit.json لاگ می‌شود
   · اگر لایسنس کافه معلق شده باشد، خروجی مسدود است
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);
require_once __DIR__ . '/api/lib/paths.php';
require_once __DIR__ . '/api/lib/class-gstore.php';
require_once __DIR__ . '/api/lib/platform-admin.php';
require_once __DIR__ . '/api/lib/export-control.php';

function xc_fail_page(string $title, string $msg): void {
  while (ob_get_level() > 0) { ob_end_clean(); }
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($title) . '</title>'
     . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">'
     . '<style>body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;display:grid;place-items:center;min-height:100dvh;margin:0}'
     . '.c{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:18px;padding:28px;max-width:420px;text-align:center}'
     . 'h1{font-size:16px;color:#A93B2A;margin:0 0 10px}p{color:#271D12;font-size:13px;line-height:2;margin:0}</style></head><body>'
     . '<div class="c"><h1>🌿 ' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($msg) . '</p></div></body></html>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
  xc_fail_page('دسترسی نامعتبر', 'این آدرس فقط برای دانلود مستقیم است.');

$token = (string)($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token))
  xc_fail_page('لینک نامعتبر', 'این لینک دانلود معتبر نیست. لینک را دقیقاً همان‌طور که مدیر ارسال کرده باز کنید.');

try {
  $req = xc_validate_token($token);
  $slug = (string)$req['slug'];

  /* کنترل مدیر: کافهٔ معلق خروجی نمی‌گیرد */
  $db = plat_store($slug)->get();
  $st = (string)($db['license']['status'] ?? 'trial');
  if ($st === 'suspended')
    xc_fail_page('خروجی مسدود است', 'وضعیت این منو توسط مدیر پلتفرم معلق شده است. با پشتیبانی در تماس باشید.');

  @mkdir(dirname(XC_FILE) . '/exports', 0755, true);
  $out = xc_out_path($slug);
  $r   = xc_build_zip($slug, $out);
  xc_mark_used($token);
  xc_audit('download', ['slug' => $slug, 'id' => (int)$req['id'],
                        'mb' => round(filesize($out) / 1048576, 2)]);

  while (ob_get_level() > 0) { ob_end_clean(); }
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="gitiarts-menu-' . $slug . '-' . date('ymd_His') . '.zip"');
  header('Content-Length: ' . (string)filesize($out));
  header('Cache-Control: no-store');
  readfile($out);
  @unlink($out);                      // پس از ارسال، فایل روی سرور پاک می‌شود
  exit;
} catch (Throwable $e) {
  xc_audit('download_failed', ['err' => $e->getMessage()]);
  xc_fail_page('دانلود انجام نشد', $e->getMessage());
}
