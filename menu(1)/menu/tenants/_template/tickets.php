<?php
declare(strict_types=1);
/* ═══════════════════════════════════════════════════════════════════════════
   تیکت پشتیبانی — کافه‌دار v2 (حباب + فایل ضمیمه)
   ═══════════════════════════════════════════════════════════════════════════ */

$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}

$user = (string)($_SESSION['tenant_user'] ?? 'کاربر');

/* CSRF_V1 */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$root = dirname(__DIR__, 2);
$tkFile = $root . '/data/tickets.json';
$filesDir = $root . '/data/ticket-files';

require_once dirname(__DIR__, 2) . '/api/lib/atomic-json.php';

function tk_dedupe(array $j): array {
  if (!isset($j['tickets'])) $j['tickets'] = [];
  if (!isset($j['nextId'])) $j['nextId'] = 1;
  $seen = []; $clean = []; $maxId = 0;
  foreach ($j['tickets'] as $t) {
    $id = (int)($t['id'] ?? 0);
    if ($id <= 0) continue;
    if (isset($seen[$id])) continue;
    $seen[$id] = true;
    $clean[] = $t;
    if ($id > $maxId) $maxId = $id;
  }
  $j['tickets'] = $clean;
  if ($maxId >= (int)$j['nextId']) $j['nextId'] = $maxId + 1;
  return $j;
}

function tk_load(string $f): array {
  if (!is_file($f)) return ['nextId'=>1, 'tickets'=>[]];
  $j = json_decode((string)@file_get_contents($f), true);
  if (!is_array($j)) return ['nextId'=>1, 'tickets'=>[]];
  return tk_dedupe($j);
}

function tk_update(string $f, callable $fn): array {
  return ajl_update($f, function(array $d) use ($fn) {
    $d = tk_dedupe($d);
    $result = $fn($d);
    return is_array($result) ? $result : $d;
  });
}
function tk_save(string $f, array $d): void {
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
/* ═══════════════════════════════════════════════════════════════
   TICKET_ID_V6_HELPERS
   ═══════════════════════════════════════════════════════════════ */
function tk_jalali(int $ts): array {
  $gy = (int)date('Y', $ts);
  $gm = (int)date('n', $ts);
  $gd = (int)date('j', $ts);
  $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
  $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
  $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
  $jy = -1595 + (33 * ((int)($days / 12053)));
  $days %= 12053;
  $jy += 4 * ((int)($days / 1461));
  $days %= 1461;
  if ($days > 365) {
    $jy += (int)(($days - 1) / 365);
    $days = ($days - 1) % 365;
  }
  if ($days < 186) { $jm = 1 + (int)($days / 31); $jd = 1 + ($days % 31); }
  else { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
  return [$jy, $jm, $jd];
}

function tk_make_id(int $ts = 0, int $seq = 0): string {
  if ($ts <= 0) $ts = time();
  [$jy, $jm, $jd] = tk_jalali($ts);
  $months = ['FA','OR','KH','TI','MO','SH','ME','AB','AZ','DE','BA','ES'];
  $dow = (int)date('w', $ts);
  $persianDow = ($dow + 1) % 7;
  $days = ['SH','YE','DO','SE','CH','PA','JO'];
  $monthShort = $months[$jm - 1] ?? 'XX';
  $dayShort = $days[$persianDow] ?? 'XX';
  $hour = date('H', $ts); $min = date('i', $ts); $sec = date('s', $ts);
  if ($seq > 0) {
    $suffix = strtoupper(str_pad(dechex($seq), 4, '0', STR_PAD_LEFT));
  } else {
    $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
  }
  return $monthShort . '-' . $dayShort . '-' . $jy . '-' .
         str_pad((string)$jm, 2, '0', STR_PAD_LEFT) . '-' .
         str_pad((string)$jd, 2, '0', STR_PAD_LEFT) . '-' .
         $hour . '-' . $min . '-' . $sec . '-' . $suffix;
}

function tk_find(array $tickets, string $q): ?array {
  $q = trim($q);
  if ($q === '') return null;
  $isNum = ctype_digit($q);
  foreach ($tickets as $t) {
    if ($isNum && (int)($t['id'] ?? 0) === (int)$q) return $t;
    if (($t['tid'] ?? '') === $q) return $t;
  }
  return null;
}

/* tk_load با dedupe + auto-tid */
function tk_load_clean(string $f): array {
  if (!is_file($f)) return ['nextId'=>1, 'tickets'=>[]];
  $j = json_decode((string)@file_get_contents($f), true);
  if (!is_array($j)) return ['nextId'=>1, 'tickets'=>[]];
  if (!isset($j['tickets'])) $j['tickets'] = [];
  if (!isset($j['nextId'])) $j['nextId'] = 1;

  $seen = []; $clean = []; $maxId = 0; $changed = false;
  foreach ($j['tickets'] as $t) {
    $id = (int)($t['id'] ?? 0);
    if ($id <= 0) continue;
    if (isset($seen[$id])) { $changed = true; continue; }
    $seen[$id] = true;
    if (empty($t['tid'])) {
      $t['tid'] = tk_make_id((int)($t['created'] ?? time()), $id);
      $changed = true;
    }
    $clean[] = $t;
    if ($id > $maxId) $maxId = $id;
  }
  $j['tickets'] = $clean;
  if ($maxId >= (int)$j['nextId']) $j['nextId'] = $maxId + 1;

  if ($changed) {
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($f, json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
  }
  return $j;
}


/* ─── آپلود فایل ضمیمه ─── */
function tk_upload(array $file, int $ticketId): ?array {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) return null;

  $allowed = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'application/pdf' => 'pdf', 'text/plain' => 'txt',
    'application/zip' => 'zip', 'application/x-zip-compressed' => 'zip',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
  ];

  $mime = mime_content_type($file['tmp_name']) ?: '';
  if (!isset($allowed[$mime])) return null;

  $ext = $allowed[$mime];
  $hash = bin2hex(random_bytes(12));
  $dir = dirname(__DIR__, 2) . '/data/ticket-files/' . $ticketId;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $dest = $dir . '/' . $hash . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

  return [
    'hash' => $hash,
    'ext'  => $ext,
    'name' => mb_substr((string)($file['name'] ?? 'file'), 0, 100),
    'size' => (int)$file['size'],
    'mime' => $mime,
  ];
}

/* ─── دانلود فایل ─── */
if (isset($_GET['dl']) && isset($_GET['f'])) {
  $fid = (int)$_GET['dl'];
  $fhash = preg_replace('/[^a-f0-9]/', '', (string)$_GET['f']);
  if ($fid <= 0 || $fhash === '') { http_response_code(400); exit('bad request'); }

  $d = tk_load($tkFile);
  $ok = false; $mime = 'application/octet-stream'; $name = 'file'; $path = '';
  foreach ($d['tickets'] as $t) {
    if ((int)$t['id'] !== $fid || ($t['slug'] ?? '') !== $slug) continue;
    foreach ($t['messages'] as $m) {
      if (!isset($m['file'])) continue;
      $f = $m['file'];
      if ($f['hash'] === $fhash) {
        $path = dirname(__DIR__, 2) . '/data/ticket-files/' . $fid . '/' . $f['hash'] . '.' . $f['ext'];
        $mime = $f['mime'] ?? 'application/octet-stream';
        $name = $f['name'] ?? 'file';
        $ok = true; break 2;
      }
    }
  }
  if (!$ok || !is_file($path)) { http_response_code(404); exit('not found'); }

  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
  header('Content-Length: ' . (string)filesize($path));
  readfile($path); exit;
}

$msg = ''; $err = '';
$act = (string)($_POST['act'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    $err = 'نشست منقضی شده — صفحه را رفرش کنید';
  } elseif ($act === 'new') {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body    = trim((string)($_POST['body'] ?? ''));
    $cat     = (string)($_POST['category'] ?? 'general');
    if (mb_strlen($subject) < 3) $err = 'موضوع حداقل ۳ حرف';
    elseif (mb_strlen($subject) > 120) $err = 'موضوع حداکثر ۱۲۰ حرف';
    elseif (mb_strlen($body) < 10) $err = 'متن حداقل ۱۰ حرف';
    elseif (mb_strlen($body) > 2000) $err = 'متن حداکثر ۲۰۰۰ حرف';
    else {
      if (!in_array($cat, ['technical','billing','feature','general'], true)) $cat = 'general';

      /* مرحله ۱: تخصیص ID اتمیک */
      $newId = 0;
      tk_update($tkFile, function(array $d) use (&$newId) {
        $newId = (int)($d['nextId'] ?? 1);
        $d['nextId'] = $newId + 1;
        return $d;
      });

      /* آپلود فایل */
      $fileData = null;
      if (!empty($_FILES['attach']['name'])) {
        $fileData = tk_upload($_FILES['attach'], $newId);
        if ($fileData === null) $err = 'فایل نامعتبر یا بزرگ‌تر از ۵ مگابایت';
      }

      if ($err === '' && $newId > 0) {
        tk_update($tkFile, function(array $d) use ($newId, $slug, $subject, $cat, $user, $body, $fileData) {
          $message = ['from'=>'cafe','author'=>$user,'text'=>$body,'ts'=>time()];
          if ($fileData) $message['file'] = $fileData;
          $d['tickets'][] = [
            'id'=>$newId, 'tid'=>tk_make_id(), 'slug'=>$slug, 'subject'=>$subject, 'category'=>$cat,
            'status'=>'open', 'created'=>time(), 'updated'=>time(),
            'messages'=>[$message],
          ];
          return $d;
        });
        $msg = '✅ تیکت ثبت شد';
      }
    }
  } elseif ($act === 'reply') {
    $id = (int)($_POST['id'] ?? 0);
    $text = trim((string)($_POST['text'] ?? ''));
    if ($id <= 0) $err = 'شناسه نامعتبر';
    elseif (mb_strlen($text) < 1) $err = 'متن را بنویسید';
    elseif (mb_strlen($text) > 2000) $err = 'متن حداکثر ۲۰۰۰ حرف';
    else {
      $fileData = null;
      if (!empty($_FILES['attach']['name'])) {
        $fileData = tk_upload($_FILES['attach'], $id);
        if ($fileData === null) $err = 'فایل نامعتبر';
      }
      if ($err === '') {
        tk_update($tkFile, function(array $d) use ($id, $slug, $user, $text, $fileData, &$err, &$msg) {
          foreach ($d['tickets'] as $i => $t) {
            if ((int)$t['id'] !== $id) continue;
            if (($t['slug'] ?? '') !== $slug) { $err = 'دسترسی'; return $d; }
            if (($t['status'] ?? '') === 'closed') { $err = 'تیکت بسته است'; return $d; }
            $m = ['from'=>'cafe','author'=>$user,'text'=>$text,'ts'=>time()];
            if ($fileData) $m['file'] = $fileData;
            $d['tickets'][$i]['messages'][] = $m;
            $d['tickets'][$i]['updated'] = time();
            $d['tickets'][$i]['status'] = 'open';
            $msg = '✅ پاسخ ثبت شد';
            break;
          }
          return $d;
        });
      }
    }
  } elseif ($act === 'close') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      tk_update($tkFile, function(array $d) use ($id, $slug) {
        foreach ($d['tickets'] as $i => $t) {
          if ((int)$t['id'] !== $id || ($t['slug'] ?? '') !== $slug) continue;
          $d['tickets'][$i]['status'] = 'closed';
          $d['tickets'][$i]['updated'] = time();
          break;
        }
        return $d;
      });
      $msg = '✅ بسته شد';
    }
  }
}

$d = tk_load($tkFile);
$myTickets = array_values(array_filter($d['tickets'], fn($t) => ($t['slug'] ?? '') === $slug));

/* CAFE_CAT_FILTER_V1 */
$__catFilter = (string)($_GET['cat'] ?? 'all');
$__allowedCats = ['technical','billing','feature','general'];
if (!in_array($__catFilter, $__allowedCats, true) && $__catFilter !== 'all') $__catFilter = 'all';
$__catCounts = ['all' => count($myTickets), 'technical'=>0, 'billing'=>0, 'feature'=>0, 'general'=>0];
foreach ($myTickets as $__t) {
  $__cc = $__t['category'] ?? 'general';
  if (isset($__catCounts[$__cc])) $__catCounts[$__cc]++;
}
if ($__catFilter !== 'all') {
  $myTickets = array_values(array_filter($myTickets, fn($t) => ($t['category'] ?? 'general') === $__catFilter));
}
usort($myTickets, fn($a, $b) => $b['updated'] <=> $a['updated']);

$__tidQuery = trim((string)($_GET['tid'] ?? $_GET['id'] ?? ''));
$__allTickets = $d['tickets'] ?? [];
if (!empty($slug)) {
  $__allTickets = array_values(array_filter($__allTickets, fn($t) => ($t['slug'] ?? '') === $slug));
}
$__pool = $__allTickets;
$__found = tk_find($__pool, $__tidQuery);
$activeId = $__found ? (int)$__found['id'] : (ctype_digit($__tidQuery) ? (int)$__tidQuery : 0);
$active = null;
foreach (($__allTickets ?? $myTickets) as $t) if ((int)$t['id'] === $activeId) { $active = $t; break; }
/* CAFE_READ_AT_FIXED */
if ($active !== null && $activeId > 0) {
  try {
    tk_update($tkFile, function(array $__d) use ($activeId, $slug) {
      foreach ($__d['tickets'] as $__i => $__t) {
        if ((int)$__t['id'] === $activeId && ($__t['slug'] ?? '') === $slug) {
          $__d['tickets'][$__i]['cafe_read_at'] = time();
          break;
        }
      }
      return $__d;
    });
  } catch (Throwable $e) { }
}

$catFa = ['technical'=>'🔧 فنی','billing'=>'💰 مالی','feature'=>'💡 قابلیت','general'=>'📝 عمومی'];
$stFa  = ['open'=>'در انتظار','answered'=>'پاسخ داده شده','closed'=>'بسته'];
$stBg  = ['open'=>'#FBEAE4', 'answered'=>'#E8F4E3', 'closed'=>'#EFE7D6'];
$stFg  = ['open'=>'#8A2C12', 'answered'=>'#3D6F2B', 'closed'=>'#7D6C54'];

$brand = $slug;
try {
  $dbf = __DIR__ . '/data/db.sqlite';
  if (is_file($dbf)) {
    $pdo = new PDO('sqlite:' . $dbf);
    $stmt = $pdo->prepare("SELECT v FROM doc WHERE scope=? AND k='db' LIMIT 1");
    $stmt->execute([$slug]);
    $raw = $stmt->fetchColumn();
    if ($raw) { $j = json_decode($raw, true); if (!empty($j['menu']['brand']['name'])) $brand = (string)$j['menu']['brand']['name']; }
  }
} catch (Throwable $e) {}

function ftime(int $ts): string {
  if ($ts <= 0) return '';
  $diff = time() - $ts;
  if ($diff < 60) return 'همین الان';
  if ($diff < 3600) return (int)($diff/60) . ' دقیقه پیش';
  if ($diff < 86400) return (int)($diff/3600) . ' ساعت پیش';
  return (int)($diff/86400) . ' روز پیش';
}
function fsize(int $b): string {
  if ($b < 1024) return $b . 'B';
  if ($b < 1048576) return round($b/1024, 1) . 'KB';
  return round($b/1048576, 1) . 'MB';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پشتیبانی — <?= htmlspecialchars($brand) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ── پالت رنگی اختصاصی شما ── */
:root {
  --bg-canvas: #EFE7D6; --bg-surface: #FBF6EB; --bg-surface-hover: #F2EADA;
  --bg-sidebar: #271D12; --border-subtle: #DFD2B6; --text-primary: #271D12;
  --text-secondary: #7D6C54; --text-inverse: #FBF6EB;
  --accent-500: #B4531F; --accent-600: #9A4517;
  --success-500: #4F7A3D; --warning-500: #B07C1F; --danger-500: #A93B2A;
  --radius-md: 10px; --radius-lg: 16px; --shadow-sm: 0 1px 3px rgba(39, 29, 18, 0.05);
  --font-family: 'Vazirmatn', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); background: var(--bg-canvas); color: var(--text-primary); display: flex; min-height: 100vh; line-height: 1.8; }

/* ── Layout ── */
.app-layout { display: flex; width: 100%; }
.main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; }
.topbar { height: 64px; background: var(--bg-surface); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; }
.content-area { padding: 24px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; }

/* ── Sidebar ── */
.sidebar { width: 260px; background: var(--bg-sidebar); color: var(--text-inverse); display: flex; flex-direction: column; flex-shrink: 0; }
.brand { padding: 24px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--bg-surface); }
.nav-menu { list-style: none; padding: 16px 0; flex: 1; }
.nav-item { padding: 12px 24px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background 0.2s; color: #CBBB99; font-weight: 500; text-decoration: none; position: relative; }
.nav-item:hover { background: rgba(255,255,255,0.05); color: var(--bg-surface); }
.nav-item.active { background: var(--accent-500); color: white; border-right: 4px solid #E59A6B; }
.nav-item .icon { font-size: 18px; }
.nav-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 16px 24px; }
.sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.page-title { font-size: 18px; font-weight: 700; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

.alert{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px}
.alert.ok{background:#E8F4E3;border:1px solid #BFDCA8;color:#3D6F2B}
.alert.err{background:#FBEAE4;border:1px solid #EFC5B6;color:#8A2C12}

/* ── Ticket List ── */
.bubble-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.bubble{position:relative;background:linear-gradient(145deg,var(--bg-surface) 0%,var(--bg-surface-hover) 100%);border:1.5px solid var(--border-subtle);border-radius:18px;padding:16px 18px 14px;cursor:pointer;text-decoration:none;color:inherit;transition:all .18s ease;overflow:hidden;display:block;box-shadow:0 2px 6px rgba(139,115,85,.05)}
.bubble:hover{transform:translateY(-2px);border-color:var(--accent-500);box-shadow:0 6px 16px rgba(180,83,31,.13)}
.bubble::before{content:'';position:absolute;top:0;right:0;width:6px;height:100%;background:var(--st-color,var(--border-subtle))}
.bubble-sub{font-size:14px;font-weight:800;color:var(--text-primary);margin-bottom:8px;line-height:1.5;padding-right:8px}
.bubble-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:11px;color:var(--text-secondary);padding-right:8px}
.bubble-pill{position:absolute;top:14px;left:16px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800}
.bubble-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:11px;background:var(--text-primary);color:#fff;font-size:11px;font-weight:800;padding:0 6px}

/* ── Chat Bubbles ── */
.msgs{padding:10px 0;margin-bottom:14px}
.msg-row{padding:12px 16px;border-radius:18px;margin-bottom:12px;max-width:82%;line-height:1.85;font-size:14px;word-wrap:break-word;position:relative;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
.msg-row.cafe{background:linear-gradient(135deg,var(--accent-500) 0%,var(--accent-600) 100%);color:#fff;margin-left:auto;border-bottom-left-radius:6px}
.msg-row.admin{background:#fff;color:var(--text-primary);margin-right:auto;border:1px solid var(--border-subtle);border-bottom-right-radius:6px}
.msg-row .who{font-size:11px;margin-bottom:6px;font-weight:800;opacity:.85}
.msg-row .when{font-size:10px;margin-top:8px;opacity:.7}
.msg-row .text{white-space:pre-wrap}
.file-attach{display:inline-flex;align-items:center;gap:8px;margin-top:10px;padding:8px 12px;background:rgba(255,255,255,.18);border-radius:10px;text-decoration:none;color:inherit;font-size:12px;font-weight:700}
.msg-row.admin .file-attach{background:var(--bg-surface-hover);border:1px solid var(--border-subtle)}

/* ── Forms & Inputs ── */
input[type=text],textarea,select{width:100%;padding:10px 12px;border:1px solid var(--border-subtle);border-radius:10px;font-family:inherit;font-size:14px;background:#fff;color:var(--text-primary)}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--accent-500);box-shadow:0 0 0 3px rgba(180,83,31,.1)}
textarea{resize:vertical;min-height:90px}
.file-input{padding:8px;border:1.5px dashed var(--border-subtle);border-radius:10px;background:var(--bg-surface);cursor:pointer;font-size:13px;width:100%}
.file-input::file-selector-button{padding:6px 14px;background:var(--accent-500);color:#fff;border:0;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:700;margin-left:8px}
label span{display:block;margin-bottom:5px;font-weight:700;font-size:13px;color:var(--text-secondary)}
.btn{display:inline-block;padding:11px 22px;background:var(--accent-500);color:#fff;border:0;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none}
.btn:hover{opacity:.9}
.btn.ghost{background:transparent;color:var(--text-primary);border:1px solid var(--border-subtle)}
.btn.danger{background:var(--danger-500)}

/* ── Categories ── */
.cat-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:var(--bg-surface);border:1px solid var(--border-subtle);border-radius:20px;text-decoration:none;color:var(--text-primary);font-size:12px;font-weight:700;transition:.15s;font-family:inherit}
.cat-tab:hover{background:var(--bg-surface-hover);border-color:var(--accent-500)}
.cat-tab.on{background:var(--accent-500);color:#fff;border-color:var(--accent-500)}
.cat-cnt{display:inline-block;background:rgba(180,83,31,.15);color:var(--accent-500);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:800}
.cat-tab.on .cat-cnt{background:rgba(255,255,255,.3);color:#fff}

.empty{padding:36px 20px;text-align:center;color:var(--text-secondary);font-size:14px;line-height:2}
.empty-icon{font-size:44px;display:block;margin-bottom:8px;opacity:.6}
.detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px dashed var(--border-subtle)}
.detail-head h3{font-size:16px;font-weight:800;margin-bottom:6px}
.detail-head .meta{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:var(--text-secondary)}
</style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">🎫 پشتیبانی</div>
      <div class="user-badge"><?= htmlspecialchars($user) ?></div>
    </header>

    <div class="content-area">
      <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

      <?php if (!$active): ?>
        <!-- List View -->
        <div class="card" style="margin-bottom:16px; padding:16px">
          <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="text" name="tid" placeholder="🔍 جستجوی شناسه تیکت..." style="flex:1;padding:10px 14px;border:1px solid var(--border-subtle);border-radius:12px;font-family:inherit;font-size:14px;background:var(--bg-surface)" min="1">
            <button type="submit" class="btn" style="padding:10px 20px">باز کن</button>
          </form>
        </div>

        <div class="card">
          <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
            <a class="cat-tab <?= $__catFilter==='all'?'on':'' ?>" href="?cat=all">همه <span class="cat-cnt"><?= $__catCounts['all'] ?></span></a>
            <a class="cat-tab <?= $__catFilter==='technical'?'on':'' ?>" href="?cat=technical">🔧 فنی <?php if ($__catCounts['technical'] > 0): ?><span class="cat-cnt"><?= $__catCounts['technical'] ?></span><?php endif; ?></a>
            <a class="cat-tab <?= $__catFilter==='billing'?'on':'' ?>" href="?cat=billing">💰 مالی <?php if ($__catCounts['billing'] > 0): ?><span class="cat-cnt"><?= $__catCounts['billing'] ?></span><?php endif; ?></a>
            <a class="cat-tab <?= $__catFilter==='feature'?'on':'' ?>" href="?cat=feature">💡 قابلیت <?php if ($__catCounts['feature'] > 0): ?><span class="cat-cnt"><?= $__catCounts['feature'] ?></span><?php endif; ?></a>
            <a class="cat-tab <?= $__catFilter==='general'?'on':'' ?>" href="?cat=general">📝 عمومی <?php if ($__catCounts['general'] > 0): ?><span class="cat-cnt"><?= $__catCounts['general'] ?></span><?php endif; ?></a>
          </div>
          
          <h2 style="font-size:16px; font-weight:800; margin-bottom:16px; border-bottom:1px dashed var(--border-subtle); padding-bottom:12px">📋 تیکت‌های من</h2>
          
          <?php if (empty($myTickets)): ?>
            <div class="empty">
              <span class="empty-icon">📭</span>
              هنوز تیکتی ثبت نکرده‌اید<br>
              <span style="font-size:12px">اگر سوال یا مشکلی دارید، فرم زیر را پر کنید</span>
            </div>
          <?php else: ?>
            <div class="bubble-grid">
              <?php foreach ($myTickets as $t): ?>
                <a class="bubble" href="?id=<?= (int)$t['id'] ?>" style="--st-color:<?= $stFg[$t['status']] ?? '#7D6C54' ?>">
                  <span class="bubble-pill" style="background:<?= $stBg[$t['status']] ?? '#EFE7D6' ?>;color:<?= $stFg[$t['status']] ?? '#7D6C54' ?>"><?= $stFa[$t['status']] ?? $t['status'] ?></span>
                  <div class="bubble-sub"><?= htmlspecialchars(mb_substr($t['subject'], 0, 50)) ?></div>
                  <div class="bubble-meta">
                    <span style="font-weight:800">#<?= htmlspecialchars($t['tid'] ?? (string)$t['id']) ?></span>
                    <span><?= $catFa[$t['category']] ?? $t['category'] ?></span>
                    <span>🕐 <?= ftime((int)$t['updated']) ?></span>
                    <span class="bubble-count"><?= count($t['messages']) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- New Ticket Form -->
        <div class="card">
          <h2 style="font-size:16px; font-weight:800; margin-bottom:16px; border-bottom:1px dashed var(--border-subtle); padding-bottom:12px">✏️ ثبت تیکت جدید</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="act" value="new">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <label style="display:block; margin-bottom:12px"><span>موضوع</span>
              <input type="text" name="subject" required maxlength="120" placeholder="مثلاً: مشکل در چاپ فاکتور">
            </label>
            <label style="display:block; margin-bottom:12px"><span>دسته‌بندی</span>
              <select name="category">
                <option value="technical">🔧 فنی</option>
                <option value="billing">💰 مالی / اشتراک</option>
                <option value="feature">💡 درخواست قابلیت</option>
                <option value="general">📝 عمومی</option>
              </select>
            </label>
            <label style="display:block; margin-bottom:12px"><span>توضیحات</span>
              <textarea name="body" required maxlength="2000" placeholder="مشکل یا درخواست خود را کامل توضیح دهید..."></textarea>
            </label>
            <label style="display:block; margin-bottom:16px"><span>📎 فایل ضمیمه (اختیاری — حداکثر ۵ مگابایت)</span>
              <input class="file-input" type="file" name="attach" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.zip">
            </label>
            <button class="btn" type="submit">📤 ارسال تیکت</button>
          </form>
        </div>

      <?php else: ?>
        <!-- Detail View -->
        <div class="card">
          <div class="detail-head">
            <div style="flex:1">
              <h3>#<?= htmlspecialchars($active['tid'] ?? (string)$active['id']) ?> — <?= htmlspecialchars($active['subject']) ?></h3>
              <div class="meta">
                <span><?= $catFa[$active['category']] ?? $active['category'] ?></span>
                <span>🕐 ایجاد: <?= ftime((int)$active['created']) ?></span>
              </div>
            </div>
            <span class="bubble-pill" style="position:static;background:<?= $stBg[$active['status']] ?? '#EFE7D6' ?>;color:<?= $stFg[$active['status']] ?? '#7D6C54' ?>"><?= $stFa[$active['status']] ?? $active['status'] ?></span>
          </div>

          <div class="msgs">
            <?php foreach ($active['messages'] as $m): ?>
              <div class="msg-row <?= $m['from'] === 'cafe' ? 'cafe' : 'admin' ?>">
                <div class="who"><?= $m['from'] === 'cafe' ? '👤 '.htmlspecialchars($m['author']) : '🎧 پشتیبانی GitiArts' ?></div>
                <div class="text"><?= htmlspecialchars($m['text']) ?></div>
                <?php if (!empty($m['file'])): ?>
                  <?php $f = $m['file']; ?>
                  <a class="file-attach" href="?dl=<?= (int)$active['id'] ?>&f=<?= htmlspecialchars($f['hash']) ?>" download>
                    📎 <?= htmlspecialchars($f['name']) ?> · <?= fsize((int)$f['size']) ?>
                  </a>
                <?php endif; ?>
                <div class="when"><?= ftime((int)$m['ts']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($active['status'] !== 'closed'): ?>
            <form method="post" enctype="multipart/form-data" style="margin-top:16px; border-top:1px solid var(--border-subtle); padding-top:16px">
              <input type="hidden" name="act" value="reply">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
              <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
              <textarea name="text" required maxlength="2000" placeholder="پاسخ خود را بنویسید..."></textarea>
              <label style="margin-top:10px; display:block"><span>📎 فایل ضمیمه (اختیاری)</span>
                <input class="file-input" type="file" name="attach" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.zip">
              </label>
              <div style="display:flex;gap:8px;flex-wrap:wrap; margin-top:12px">
                <button class="btn" type="submit">📤 ارسال پاسخ</button>
                <a href="tickets.php" class="btn ghost">‹ بازگشت</a>
              </div>
            </form>
            <form method="post" style="margin-top:10px" onsubmit="return confirm('تیکت بسته شود؟')">
              <input type="hidden" name="act" value="close">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
              <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
              <button class="btn danger" type="submit" style="font-size:12px">✓ بستن تیکت</button>
            </form>
          <?php else: ?>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:16px">این تیکت بسته شده است.</p>
            <a href="tickets.php" class="btn ghost" style="margin-top:10px">‹ بازگشت به لیست</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>