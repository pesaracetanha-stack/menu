<?php
declare(strict_types=1);
/* پنل ادمین — تیکت‌های پشتیبانی v2 (حباب + فایل) */

require_once __DIR__ . '/../api/lib/platform-auth.php';
require_once __DIR__ . '/../api/lib/platform-admin.php';
require_once __DIR__ . '/../api/lib/paths.php';
require_once __DIR__ . '/../bootstrap-i18n.php';

sec_session();
pauth_require();

$root = dirname(__DIR__);
$tkFile = $root . '/data/tickets.json';

require_once __DIR__ . '/../api/lib/atomic-json.php';

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

/* ATOMIC_RMW: read-modify-write با flock */
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

function tk_upload(array $file, int $ticketId): ?array {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) return null;
  $allowed = [
    'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',
    'application/pdf'=>'pdf','text/plain'=>'txt',
    'application/zip'=>'zip','application/x-zip-compressed'=>'zip',
    'application/msword'=>'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
    'application/vnd.ms-excel'=>'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
  ];
  $mime = mime_content_type($file['tmp_name']) ?: '';
  if (!isset($allowed[$mime])) return null;
  $ext = $allowed[$mime];
  $hash = bin2hex(random_bytes(12));
  $dir = dirname(__DIR__) . '/data/ticket-files/' . $ticketId;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $dest = $dir . '/' . $hash . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
  return ['hash'=>$hash,'ext'=>$ext,'name'=>mb_substr((string)($file['name'] ?? 'file'),0,100),'size'=>(int)$file['size'],'mime'=>$mime];
}

/* دانلود */
if (isset($_GET['dl']) && isset($_GET['f'])) {
  $fid = (int)$_GET['dl'];
  $fhash = preg_replace('/[^a-f0-9]/', '', (string)$_GET['f']);
  if ($fid <= 0 || $fhash === '') { http_response_code(400); exit('bad'); }
  $d = tk_load($tkFile);
  $ok = false; $mime='application/octet-stream'; $name='file'; $path='';
  foreach ($d['tickets'] as $t) {
    if ((int)$t['id'] !== $fid) continue;
    foreach ($t['messages'] as $m) {
      if (!isset($m['file'])) continue;
      $f = $m['file'];
      if ($f['hash'] === $fhash) {
        $path = dirname(__DIR__) . '/data/ticket-files/' . $fid . '/' . $f['hash'] . '.' . $f['ext'];
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

$msg=''; $err='';
$act = (string)($_POST['act'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $err = 'نشست منقضی شده';
  } elseif ($act === 'reply') {
    $id = (int)($_POST['id'] ?? 0);
    $text = trim((string)($_POST['text'] ?? ''));
    if ($id <= 0) $err = 'شناسه نامعتبر';
    elseif (mb_strlen($text) < 1) $err = 'متن را بنویسید';
    elseif (mb_strlen($text) > 2000) $err = 'حداکثر ۲۰۰۰';
    else {
      $fileData = null;
      if (!empty($_FILES['attach']['name'])) {
        $fileData = tk_upload($_FILES['attach'], $id);
        if ($fileData === null) $err = 'فایل نامعتبر یا بزرگ‌تر از ۵ مگابایت';
      }
      if ($err === '') {
        tk_update($tkFile, function(array $d) use ($id, $text, $fileData) {
          foreach ($d['tickets'] as $i => $t) {
            if ((int)$t['id'] !== $id) continue;
            if (($t['status'] ?? '') === 'closed') return $d;
            $m = ['from'=>'admin','author'=>'پشتیبانی','text'=>$text,'ts'=>time()];
            if ($fileData) $m['file'] = $fileData;
            $d['tickets'][$i]['messages'][] = $m;
            $d['tickets'][$i]['updated'] = time();
            $d['tickets'][$i]['status'] = 'answered';
            break;
          }
          return $d;
        });
        $msg = '✅ پاسخ ارسال شد';
        plat_log('ticket_reply', ['id'=>$id]);
      }
    }
  } elseif ($act === 'close') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      tk_update($tkFile, function(array $d) use ($id) {
        foreach ($d['tickets'] as $i => $t) {
          if ((int)$t['id'] !== $id) continue;
          $d['tickets'][$i]['status'] = 'closed';
          $d['tickets'][$i]['updated'] = time();
          break;
        }
        return $d;
      });
      $msg = '✅ بسته شد';
      plat_log('ticket_close', ['id'=>$id]);
    }
  } elseif ($act === 'reopen') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      tk_update($tkFile, function(array $d) use ($id) {
        foreach ($d['tickets'] as $i => $t) {
          if ((int)$t['id'] !== $id) continue;
          $d['tickets'][$i]['status'] = 'open';
          $d['tickets'][$i]['updated'] = time();
          break;
        }
        return $d;
      });
      $msg = '✅ باز شد';
    }
  }
}

$d = tk_load($tkFile);
$allTickets = $d['tickets'];
usort($allTickets, fn($a, $b) => $b['updated'] <=> $a['updated']);

$filter = (string)($_GET['f'] ?? 'active');
$filtered = $allTickets;
$filter = in_array($filter, ['active','open','answered','closed','all'], true) ? $filter : 'active';
if ($filter === 'active') {
  $filtered = array_values(array_filter($allTickets, fn($t) => ($t['status'] ?? 'open') !== 'closed'));
} elseif ($filter !== 'all') {
  $filtered = array_values(array_filter($allTickets, fn($t) => ($t['status'] ?? '') === $filter));
}

/* ADMIN_CAT_FILTER_V1 */
$__catFilter = (string)($_GET['c'] ?? 'all');
$__allowedCats = ['technical','billing','feature','general'];
if (!in_array($__catFilter, $__allowedCats, true) && $__catFilter !== 'all') $__catFilter = 'all';
$__catCounts = ['all' => count($allTickets), 'technical'=>0, 'billing'=>0, 'feature'=>0, 'general'=>0];
foreach ($allTickets as $__t) {
  $__cc = $__t['category'] ?? 'general';
  if (isset($__catCounts[$__cc])) $__catCounts[$__cc]++;
}
if ($__catFilter !== 'all') {
  $filtered = array_values(array_filter($filtered, fn($t) => ($t['category'] ?? 'general') === $__catFilter));
}

$counts = ['all'=>count($allTickets), 'open'=>0, 'answered'=>0, 'closed'=>0];
foreach ($allTickets as $t) { $s = $t['status'] ?? 'open'; if (isset($counts[$s])) $counts[$s]++; }

$__tidQuery = trim((string)($_GET['tid'] ?? $_GET['id'] ?? ''));
/* FIX_TK_SEARCH_V1 */
$__allTickets = $d['tickets'] ?? [];
if (!empty($slug)) {
  $__allTickets = array_values(array_filter($__allTickets, fn($t) => ($t['slug'] ?? '') === $slug));
}
$__pool = $__allTickets;
$__found = tk_find($__pool, $__tidQuery);
$activeId = $__found ? (int)$__found['id'] : (ctype_digit($__tidQuery) ? (int)$__tidQuery : 0);
$active = null;
foreach ($allTickets as $t) if ((int)$t['id'] === $activeId) { $active = $t; break; }
/* ADMIN_READ_AT_FIXED */
if ($active !== null && $activeId > 0) {
  try {
    tk_update($tkFile, function(array $__d) use ($activeId) {
      foreach ($__d['tickets'] as $__i => $__t) {
        if ((int)$__t['id'] === $activeId) {
          $__d['tickets'][$__i]['admin_read_at'] = time();
          break;
        }
      }
      return $__d;
    });
  } catch (Throwable $e) { }
}

$tenantsMap = [];
try { foreach (plat_list() as $x) $tenantsMap[$x['slug']] = (string)($x['name'] ?? $x['slug']); } catch (Throwable $e) {}

$catFa = ['technical'=>'🔧 فنی','billing'=>'💰 مالی','feature'=>'💡 قابلیت','general'=>'📝 عمومی'];
$stFa  = ['open'=>'در انتظار','answered'=>'پاسخ داده','closed'=>'بسته'];
$stBg  = ['open'=>'#FBEAE4','answered'=>'#E8F4E3','closed'=>'#EFE7D6'];
$stFg  = ['open'=>'#8A2C12','answered'=>'#3D6F2B','closed'=>'#7D6C54'];

function ftime(int $ts): string {
  if ($ts <= 0) return '—';
  $diff = time() - $ts;
  if ($diff < 60) return 'همین الان';
  if ($diff < 3600) return (int)($diff/60) . 'د';
  if ($diff < 86400) return (int)($diff/3600) . 'س';
  return (int)($diff/86400) . 'روز';
}
function ftimeFull(int $ts): string { return $ts > 0 ? jdate(date('Y-m-d H:i:s', $ts), 'Y/m/d H:i') : '—'; }
function fsize(int $b): string {
  if ($b < 1024) return $b . 'B';
  if ($b < 1048576) return round($b/1024,1) . 'KB';
  return round($b/1048576,1) . 'MB';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تیکت‌ها — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ── پالت رنگی اختصاصی شما ── */
:root {
  --bg-canvas: #EFE7D6; --bg-surface: #FBF6EB; --bg-surface-hover: #F2EADA;
  --bg-sidebar: #271D12; --border-subtle: #DFD2B6; --text-primary: #271D12;
  --text-secondary: #7D6C54; --text-inverse: #FBF6EB;
  --accent-500: #B4531F; --accent-600: #9A4517;
  --success-500: #4F7A3D; --warning-500: #B4801F; --danger-500: #A93B2A;
  --radius-md: 10px; --radius-lg: 16px; --shadow-sm: 0 1px 3px rgba(39, 29, 18, 0.05);
  --font-family: 'Vazirmatn', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); background: var(--bg-canvas); color: var(--text-primary); display: flex; min-height: 100vh; line-height: 1.6; }

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
.badge { background: var(--danger-500); color: white; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 50px; margin-right: auto; }
.sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); }
.btn-outline-sidebar { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: var(--radius-md); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: white; width: 100%; transition: all 0.2s; }
.btn-outline-sidebar:hover { background: rgba(255,255,255,0.1); }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.page-title { font-size: 18px; font-weight: 700; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

.tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.tab{padding:8px 16px;background:var(--bg-surface);border:1px solid var(--border-subtle);border-radius:20px;text-decoration:none;color:var(--text-primary);font-size:13px;font-weight:700;transition:.15s}
.tab:hover{background:var(--bg-surface-hover);border-color:var(--accent-500)}
.tab.on{background:var(--accent-500);color:#fff;border-color:var(--accent-500)}
.tab .cnt{display:inline-block;background:rgba(255,255,255,.3);padding:1px 8px;border-radius:10px;margin-right:4px;font-size:11px}
.tab:not(.on) .cnt{background:var(--bg-canvas)}

.layout{display:grid;grid-template-columns:340px 1fr;gap:14px;flex:1;min-height:0}
@media(max-width:820px){.layout{grid-template-columns:1fr}}

.sidebar-tickets{max-height:100%;overflow:auto;display:flex;flex-direction:column;gap:10px;padding-left:4px}
.bubble{position:relative;background:linear-gradient(145deg,var(--bg-surface) 0%,var(--bg-surface-hover) 100%);border:1.5px solid var(--border-subtle);border-radius:18px;padding:14px 16px;text-decoration:none;color:inherit;transition:all .18s ease;box-shadow:0 2px 6px rgba(139,115,85,.05);overflow:hidden}
.bubble:hover{transform:translateY(-2px);border-color:var(--accent-500);box-shadow:0 6px 16px rgba(180,83,31,.13)}
.bubble.on{background:var(--bg-canvas);border-color:var(--accent-500)}
.bubble::before{content:'';position:absolute;top:0;right:0;width:6px;height:100%;background:var(--st-color,var(--border-subtle))}
.bubble-slug{font-size:12px;color:var(--accent-500);font-weight:800;margin-bottom:6px}
.bubble-sub{font-size:13px;font-weight:700;line-height:1.5;margin-bottom:8px}
.bubble-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:11px;color:var(--text-secondary);align-items:center}
.bubble-pill{position:absolute;top:12px;left:12px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:800}
.bubble-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:10px;background:var(--text-primary);color:#fff;font-size:10px;font-weight:800;padding:0 6px}

.main-ticket{background:var(--bg-surface);border:1px solid var(--border-subtle);border-radius:16px;padding:16px 20px;max-height:100%;overflow:auto;display:flex;flex-direction:column}
.msgs{padding:8px 0;margin-bottom:14px;flex:1;overflow-y:auto}
.msg-row{padding:12px 16px;border-radius:18px;margin-bottom:12px;max-width:80%;line-height:1.85;font-size:14px;word-wrap:break-word}
.msg-row.cafe{background:linear-gradient(135deg,var(--accent-500) 0%,var(--accent-600) 100%);color:#fff;margin-left:auto;border-bottom-left-radius:6px}
.msg-row.admin{background:#fff;color:var(--text-primary);margin-right:auto;border:1px solid var(--border-subtle);border-bottom-right-radius:6px}
.msg-row .who{font-size:11px;margin-bottom:6px;font-weight:800;opacity:.85}
.msg-row .when{font-size:10px;margin-top:8px;opacity:.7}
.msg-row .text{white-space:pre-wrap}
.file-attach{display:inline-flex;align-items:center;gap:8px;margin-top:10px;padding:8px 12px;background:rgba(255,255,255,.18);border-radius:10px;text-decoration:none;color:inherit;font-size:12px;font-weight:700}
.msg-row.admin .file-attach{background:var(--bg-surface-hover);border:1px solid var(--border-subtle)}
.empty{padding:36px;text-align:center;color:var(--text-secondary);font-size:14px}

label{display:block;margin-bottom:12px;font-size:14px}
label span{display:block;margin-bottom:5px;font-weight:700}
textarea{width:100%;padding:11px 14px;border:1px solid var(--border-subtle);border-radius:12px;font-family:inherit;font-size:14px;background:#fff;color:var(--text-primary);resize:vertical;min-height:90px}
textarea:focus{outline:none;border-color:var(--accent-500);box-shadow:0 0 0 3px rgba(180,83,31,.1)}
.file-input{padding:8px;border:1.5px dashed var(--border-subtle);border-radius:10px;background:var(--bg-surface);cursor:pointer;font-size:13px;width:100%}
.file-input::file-selector-button{padding:6px 14px;background:var(--accent-500);color:#fff;border:0;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:700;margin-left:8px}
.btn{display:inline-block;padding:11px 22px;background:var(--accent-500);color:#fff;border:0;border-radius:10px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{opacity:.9}
.btn.ghost{background:transparent;color:var(--text-primary);border:1px solid var(--border-subtle)}
.btn.danger{background:var(--danger-500)}
.alert{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px}
.alert.ok{background:#E8F4E3;border:1px solid #BFDCA8;color:#3D6F2B}
.alert.err{background:#FBEAE4;border:1px solid #EFC5B6;color:#8A2C12}
.detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px dashed var(--border-subtle)}
.detail-head h3{font-size:16px;font-weight:800;margin-bottom:6px}
.detail-head .meta{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:var(--text-secondary)}
</style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">🎫 تیکت‌های پشتیبانی</div>
      <div class="topbar-actions">
        <div class="user-badge">مدیر کل</div>
      </div>
    </header>

    <div class="content-area">
      <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form method="get" style="display:flex;gap:8px;margin-bottom:12px;max-width:400px">
        <?php if (!empty($filter)): ?><input type="hidden" name="f" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
        <?php if (!empty($__catFilter)): ?><input type="hidden" name="c" value="<?= htmlspecialchars($__catFilter) ?>"><?php endif; ?>
        <input type="text" name="tid" placeholder="🔍 شناسه تیکت..." style="flex:1;padding:10px 14px;border:1px solid var(--border-subtle);border-radius:12px;font-family:inherit;font-size:14px;background:var(--bg-surface)" min="1">
        <button type="submit" class="btn" style="padding:10px 20px">باز کن</button>
      </form>

      <div class="tabs">
        <a class="tab <?= $filter==='all'?'on':'' ?>" href="?f=all">همه <span class="cnt"><?= pnum($counts['all']) ?></span></a>
        <a class="tab <?= $filter==='open'?'on':'' ?>" href="?f=open">در انتظار <span class="cnt"><?= pnum($counts['open']) ?></span></a>
        <a class="tab <?= $filter==='answered'?'on':'' ?>" href="?f=answered">پاسخ داده <span class="cnt"><?= pnum($counts['answered']) ?></span></a>
        <a class="tab <?= $filter==='closed'?'on':'' ?>" href="?f=closed">بسته <span class="cnt"><?= pnum($counts['closed']) ?></span></a>
      </div>

      <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
        <a class="tab <?= $__catFilter==='all'?'on':'' ?>" href="?f=<?= htmlspecialchars($filter) ?>&c=all">📋 همه دسته‌ها <span class="cnt"><?= pnum(count($allTickets)) ?></span></a>
        <a class="tab <?= $__catFilter==='technical'?'on':'' ?>" href="?f=<?= htmlspecialchars($filter) ?>&c=technical">🔧 فنی <?php if ($__catCounts['technical']>0): ?><span class="cnt"><?= pnum($__catCounts['technical']) ?></span><?php endif; ?></a>
        <a class="tab <?= $__catFilter==='billing'?'on':'' ?>" href="?f=<?= htmlspecialchars($filter) ?>&c=billing">💰 مالی <?php if ($__catCounts['billing']>0): ?><span class="cnt"><?= pnum($__catCounts['billing']) ?></span><?php endif; ?></a>
        <a class="tab <?= $__catFilter==='feature'?'on':'' ?>" href="?f=<?= htmlspecialchars($filter) ?>&c=feature">💡 قابلیت <?php if ($__catCounts['feature']>0): ?><span class="cnt"><?= pnum($__catCounts['feature']) ?></span><?php endif; ?></a>
        <a class="tab <?= $__catFilter==='general'?'on':'' ?>" href="?f=<?= htmlspecialchars($filter) ?>&c=general">📝 عمومی <?php if ($__catCounts['general']>0): ?><span class="cnt"><?= pnum($__catCounts['general']) ?></span><?php endif; ?></a>
      </div>

      <div class="layout">
        <div class="sidebar-tickets">
          <?php if (empty($filtered)): ?>
            <div class="empty">تیکتی با این فیلتر نیست</div>
          <?php else: ?>
            <?php foreach ($filtered as $t): ?>
              <?php $s = (string)$t['slug']; $cafeName = $tenantsMap[$s] ?? $s; ?>
              <a class="bubble <?= (int)$t['id']===$activeId?'on':'' ?>" href="?id=<?= (int)$t['id'] ?>&f=<?= htmlspecialchars($filter) ?>" style="--st-color:<?= $stFg[$t['status']] ?? '#7D6C54' ?>">
                <span class="bubble-pill" style="background:<?= $stBg[$t['status']] ?? '#EFE7D6' ?>;color:<?= $stFg[$t['status']] ?? '#7D6C54' ?>"><?= $stFa[$t['status']] ?? $t['status'] ?></span>
                <div class="bubble-slug"><?= htmlspecialchars($cafeName) ?></div>
                <div class="bubble-sub"><?= htmlspecialchars(mb_substr($t['subject'], 0, 55)) ?></div>
                <div class="bubble-meta">
                  <span style="font-size:11px">#<?= htmlspecialchars(mb_substr($t['tid'] ?? (string)$t['id'], 0, 22)) ?></span>
                  <span><?= $catFa[$t['category']] ?? '' ?></span>
                  <span>🕐 <?= ftime((int)$t['updated']) ?></span>
                  <span class="bubble-count"><?= pnum(count($t['messages'])) ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="main-ticket">
          <?php if (!$active): ?>
            <div class="empty">تیکتی انتخاب کنید تا پاسخ دهید</div>
          <?php else: ?>
            <?php $s = (string)$active['slug']; $cafeName = $tenantsMap[$s] ?? $s; ?>
            <div class="detail-head">
              <div style="flex:1">
                <h3>#<?= htmlspecialchars($active['tid'] ?? (string)$active['id']) ?> — <?= htmlspecialchars($active['subject']) ?></h3>
                <div class="meta">
                  <span><b><?= htmlspecialchars($cafeName) ?></b></span>
                  <span><?= $catFa[$active['category']] ?? '' ?></span>
                  <span>ایجاد: <?= ftimeFull((int)$active['created']) ?></span>
                </div>
              </div>
              <span class="bubble-pill" style="position:static;background:<?= $stBg[$active['status']] ?? '#EFE7D6' ?>;color:<?= $stFg[$active['status']] ?? '#7D6C54' ?>"><?= $stFa[$active['status']] ?? $active['status'] ?></span>
            </div>

            <div class="msgs">
              <?php foreach ($active['messages'] as $m): ?>
                <div class="msg-row <?= $m['from']==='cafe'?'cafe':'admin' ?>">
                  <div class="who"><?= $m['from']==='cafe' ? '👤 '.htmlspecialchars($m['author']) : '🎧 پشتیبانی GitiArts' ?></div>
                  <div class="text"><?= htmlspecialchars($m['text']) ?></div>
                  <?php if (!empty($m['file'])): $f = $m['file']; ?>
                    <a class="file-attach" href="?dl=<?= (int)$active['id'] ?>&f=<?= htmlspecialchars($f['hash']) ?>" download>
                      📎 <?= htmlspecialchars($f['name']) ?> · <?= fsize((int)$f['size']) ?>
                    </a>
                  <?php endif; ?>
                  <div class="when"><?= ftimeFull((int)$m['ts']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($active['status'] !== 'closed'): ?>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="reply">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <textarea name="text" required maxlength="2000" placeholder="پاسخ به کافه‌دار..."></textarea>
                <label style="margin-top:10px"><span>📎 فایل ضمیمه (اختیاری)</span>
                  <input class="file-input" type="file" name="attach" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.zip">
                </label>
                <button class="btn" type="submit">📤 ارسال پاسخ</button>
              </form>
              <form method="post" style="margin-top:10px" onsubmit="return confirm('بسته شود؟')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="close">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <button class="btn danger" type="submit" style="font-size:12px">✓ بستن</button>
              </form>
            <?php else: ?>
              <p style="font-size:13px;color:var(--text-secondary);margin:12px 0">بسته شده است.</p>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="reopen">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <button class="btn ghost" type="submit">↻ باز کردن</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>