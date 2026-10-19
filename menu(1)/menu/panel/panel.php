<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/lib/platform-auth.php';
require_once __DIR__ . '/../api/lib/platform-admin.php';
require_once __DIR__ . '/../api/lib/paths.php';
require_once __DIR__ . '/../api/lib/export-control.php';
require_once __DIR__ . '/../api/lib/updater.php';
require_once __DIR__ . '/../bootstrap-i18n.php';
sec_session();
pauth_require();

/* BACKUP_DASH_V1 */
require_once __DIR__ . '/../api/lib/backup.php';
try { $__bakLazy = bak_lazy_check(); } catch (Throwable $e) { $__bakLazy = null; }
/* SMS_LAZY_V1 */
try { $__smsLazy = sms_reminder_run(); } catch (Throwable $e) { $__smsLazy = null; }

$msg = ''; $ok = ''; $tmpPass = ''; $exportLink = '';
$updRep = null; $updScan = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $msg = 'نشست منقضی شده';
  } else try {
    switch ($act) {
      case 'logout':  plat_log('logout'); pauth_logout(); header('Location: index.php'); exit;
      case 'change':  $m = pauth_change($_POST['old'] ?? '', $_POST['p1'] ?? '', $_POST['p2'] ?? '');
                      if ($m === '') { $ok = 'رمز عوض شد'; plat_log('pass_change'); } else $msg = $m; break;
      case 'activate': plat_activate($_POST['slug'] ?? '', max(30, (int)($_POST['days'] ?? 365)));
                       $ok = 'لایسنس فعال شد'; plat_log('activate', ['slug' => $_POST['slug'] ?? '', 'days' => (int)($_POST['days'] ?? 365)]); break;
      case 'setplan': plat_set_plan($_POST['slug'] ?? '', $_POST['plan'] ?? 'free');
                    $ok = 'پلن تغییر کرد'; plat_log('setplan', ['slug' => $_POST['slug'] ?? '', 'plan' => $_POST['plan'] ?? '']); break;
      case 'save_plans':
        $incoming = $_POST['plans'] ?? [];
        if (!is_array($incoming)) { $msg = 'داده نامعتبر'; break; }
        $allowed = ['free', 'basic', 'pro'];
        $clean = [];
        foreach ($allowed as $k) {
          if (!isset($incoming[$k]) || !is_array($incoming[$k])) continue;
          $clean[$k] = [
            'name_fa'          => trim((string)($incoming[$k]['name_fa'] ?? '')),
            'price_toman'      => max(0, (int)($incoming[$k]['price_toman'] ?? 0)),
            'max_users'        => max(0, (int)($incoming[$k]['max_users'] ?? 0)),
            'sms_enabled'      => !empty($incoming[$k]['sms_enabled']),
            'max_menu_items'   => max(0, (int)($incoming[$k]['max_menu_items'] ?? 0)),
            'max_orders_month' => max(0, (int)($incoming[$k]['max_orders_month'] ?? 0)),
          ];
        }
        $pFile = __DIR__ . '/../data/plans.json';
        $existing = is_file($pFile) ? json_decode((string)file_get_contents($pFile), true) : [];
        if (!is_array($existing)) $existing = [];
        $existing['plans'] = array_merge($existing['plans'] ?? [], $clean);
        $existing['updated_at'] = (int)(microtime(true) * 1000);
        if (file_put_contents($pFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n") === false) {
          $msg = 'خطا در ذخیره plans.json'; break;
        }
        $ok = '✅ قیمت‌ها و پلن‌ها ذخیره شد';
        plat_log('save_plans', ['keys' => array_keys($clean)]);
        break;      case 'save_sms':
        $smsFile = __DIR__ . '/../data/platform-sms.json';
        $incoming = $_POST['sms'] ?? [];
        $clean = [
            'provider' => trim((string)($incoming['provider'] ?? 'mydnspanel')),
            'key' => trim((string)($incoming['key'] ?? '')),
            'sender' => trim((string)($incoming['sender'] ?? '')),
        ];
        file_put_contents($smsFile, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $ok = '✅ تنظیمات پیامک ذخیره شد';
        plat_log('save_sms', ['provider' => $clean['provider']]);
        break;
      case 'suspend':  plat_suspend($_POST['slug'] ?? ''); $ok = 'کافه غیرفعال شد'; plat_log('suspend', ['slug' => $_POST['slug'] ?? '']); break;
      case 'resetpass': $tmpPass = plat_reset_pass($_POST['slug'] ?? '');
                        $ok = 'رمز موقت مدیر کافه ساخته شد'; plat_log('resetpass', ['slug' => $_POST['slug'] ?? '']); break;
      case 'resetuser': $tmpPass = plat_reset_user($_POST['slug'] ?? '', $_POST['uid'] ?? '');
                        $ok = 'رمز موقت پرسنل ساخته شد'; plat_log('resetuser', ['slug' => $_POST['slug'] ?? '', 'uid' => $_POST['uid'] ?? '']); break;
      case 'delete':   plat_delete($_POST['slug'] ?? '', ($_POST['final'] ?? '') === '1'); $ok = 'کافه حذف شد'; break;
      case 'sweep':    $r = plat_sweep_orphans(); $ok = "جاروبرقی: {$r['deleted']} حذف شد"; plat_log('sweep', $r); break;
      case 'trash':    $n = plat_trash_sweep(); $ok = "$n کافه‌ی منقضی پاک شد"; plat_log('trash_sweep', ['n' => $n]); break;
      case 'renewexpiring':
                       $__days = max(1, (int)($_POST['days'] ?? 30)); $__cnt = 0;
                       foreach (plat_list() as $__x) {
                         $__lic = $__x['lic'] ?? []; $__s = $__lic['status'] ?? ''; $__d = $__lic['days_left'] ?? null;
                         if (($__s === 'active' || $__s === 'trial') && $__d !== null && $__d <= 7) {
                           try { plat_renew((string)$__x['slug'], $__days); $__cnt++; } catch (Throwable $e) {}
                         }
                       }
                       $ok = "تمدید گروهی: $__cnt کافه"; plat_log('renewexpiring', ['ok' => $__cnt]); break;
      case 'renew':      plat_renew($_POST['slug'] ?? '', (int)($_POST['days'] ?? 365)); $ok = 'لایسنس تمدید شد'; break;
      case 'extendtrial': plat_extend_trial($_POST['slug'] ?? '', (int)($_POST['days'] ?? 14)); $ok = 'دوره آزمایشی تمدید شد'; break;
      case 'rename':     plat_rename($_POST['slug'] ?? '', (string)($_POST['name'] ?? '')); $ok = 'نام کافه عوض شد'; break;
      case 'downloadbackup':
                       $__name = basename((string)($_POST['file'] ?? ''));
                       if (preg_match('/^[a-zA-Z0-9_\-]+\.zip$/', $__name)) {
                         $__path = __DIR__ . '/../data/exports/' . $__name;
                         if (is_file($__path)) {
                           plat_log('downloadbackup', ['file' => $__name]);
                           header('Content-Type: application/zip');
                           header('Content-Disposition: attachment; filename="' . $__name . '"');
                           header('Content-Length: ' . (string)filesize($__path)); readfile($__path); exit;
                         }
                       } $msg = 'فایل یافت نشد'; break;
      case 'downloadall':
                       $__backupRoot = __DIR__ . '/../data/exports';
                       $__zips = is_dir($__backupRoot) ? glob($__backupRoot . '/*.zip') : [];
                       if (!empty($__zips)) {
                         $__out = $__backupRoot . '/all-backups-' . date('Ymd-His') . '.zip';
                         $__zip = new ZipArchive();
                         if ($__zip->open($__out, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                           foreach ($__zips as $__f) $__zip->addFile($__f, basename($__f));
                           $__zip->close();
                           header('Content-Type: application/zip');
                           header('Content-Disposition: attachment; filename="' . basename($__out) . '"');
                           header('Content-Length: ' . (string)filesize($__out)); readfile($__out); @unlink($__out); exit;
                         }
                       } $msg = 'هیچ بکاپی یافت نشد'; break;
      case 'backupall':
                       $__cnt = 0; foreach (plat_list() as $__t) { try { plat_backup((string)$__t['slug']); $__cnt++; } catch (Throwable $e) {} }
                       $ok = "بکاپ $__cnt کافه گرفته شد"; plat_log('backupall', ['ok' => $__cnt]); break;
      case 'backupdl':   $bk = plat_backup($_POST['slug'] ?? '');
                         header('Content-Type: application/zip');
                         header('Content-Disposition: attachment; filename="gitiarts-backup-' . basename($bk['path']) . '"');
                         header('Content-Length: ' . (string)filesize($bk['path'])); readfile($bk['path']); exit;
      case 'restore_backup':
                       $__bf = (string)($_POST['backup'] ?? ''); $__sg = (string)($_POST['slug'] ?? '');
                       try { $__r = plat_restore_backup($__sg, $__bf); $ok = "بازگردانی موفق: کافه «{$__sg}»"; } catch (Throwable $e) { $msg = 'خطا: ' . $e->getMessage(); } break;
      case 'restore':    plat_restore($_POST['tname'] ?? ''); $ok = 'کافه بازیابی شد'; break;
      case 'trashdel':   plat_trash_delete_one($_POST['tname'] ?? ''); $ok = 'حذف قطعی انجام شد'; break;
      case 'approve_export':
        $tok = xc_approve((int)($_POST['id'] ?? 0), (string)($_POST['slug'] ?? ''));
        $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $exportLink = $sch . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/export-download.php?t=' . $tok;
        $ok = 'لینک یک‌بارمصرف ساخته شد'; break;
      case 'deny_export': xc_deny((int)($_POST['id'] ?? 0), (string)($_POST['slug'] ?? '')); $ok = 'درخواست رد شد'; break;
      case 'update_scan': $updScan = upd_scan(); $ok = 'اسکن انجام شد'; break;
      case 'update_apply':
        $f = $_FILES['upzip'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
          $updRep = upd_apply($f['tmp_name'], ($_POST['force'] ?? '') === '1');
          plat_log('update_apply', ['version' => (string)($updRep['version'] ?? '')]);
          $ok = "آپدیت نسخه {$updRep['version']} نصب شد";
        } else { $msg = 'فایل آپدیت دریافت نشد'; } break;
    }
  } catch (Throwable $e) { $msg = $e->getMessage(); }
}

$cfg  = p_config();
$list = plat_list();
$reqs = xc_load()['reqs'];
$aud  = xc_audit_tail(60);
$stats = plat_stats();
$trash = plat_trash_list();
$totalMb = $stats['total_mb'];
$names = []; foreach ($list as $x) $names[$x['slug']] = $x['name'];
try { $updVer = upd_current_version(); } catch (Throwable $e) { $updVer = '?'; }
$updLogs = upd_log_load();
$__bak = bak_status();

$currentView = $_GET['view'] ?? 'dashboard';

function plat_ev_fa(string $ev): string {
  static $m = [
    'plat.activate'=>'فعال‌سازی لایسنس','plat.suspend'=>'تعلیق کافه','plat.rename'=>'تغییر نام کافه',
    'plat.extend_trial'=>'تمدید دوره آزمایشی','plat.renew'=>'تمدید لایسنس','plat.resetpass'=>'ریست رمز مدیر کافه',
    'plat.resetuser'=>'ریست رمز پرسنل','plat.delete'=>'حذف کافه (به سطل)','plat.restore'=>'بازیابی از سطل زباله',
    'plat.trash_delete'=>'حذف قطعی از سطل','plat.backup'=>'پشتیبان‌گیری کافه','plat.sweep'=>'جارو رسانه‌های یتیم',
    'plat.trash_sweep'=>'پاک‌سازی سطل منقضی','plat.setup'=>'راه‌اندازی اولیه پنل','plat.login'=>'ورود موفق',
    'plat.login_fail'=>'تلاش ناموفق ورود','plat.logout'=>'خروج از پنل','plat.pass_change'=>'تغییر رمز مدیر',
    'plat.update_apply'=>'نصب آپدیت','request'=>'درخواست فایل نصبی','approve'=>'تأیید درخواست',
    'deny'=>'رد درخواست','export-used'=>'دانلود بسته',
  ];
  return $m[$ev] ?? $ev;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ── پالت رنگی اختصاصی شما (کرم / قهوه‌ای / نارنجی) ── */
:root {
  --bg-canvas: #EFE7D6;
  --bg-surface: #FBF6EB;
  --bg-surface-hover: #F2EADA;
  --bg-sidebar: #271D12;
  --border-subtle: #DFD2B6;
  --text-primary: #271D12;
  --text-secondary: #7D6C54;
  --text-inverse: #FBF6EB;
  --accent-500: #B4531F;
  --accent-600: #9A4517;
  --success-500: #4F7A3D;
  --warning-500: #B4801F;
  --danger-500: #A93B2A;
  --radius-md: 10px;
  --radius-lg: 16px;
  --shadow-sm: 0 1px 3px rgba(39, 29, 18, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(39, 29, 18, 0.08);
  --font-family: 'Vazirmatn', sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); background: var(--bg-canvas); color: var(--text-primary); display: flex; min-height: 100vh; line-height: 1.6; }

/* ── Layout ── */
.app-layout { display: flex; width: 100%; }
.main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.topbar { height: 64px; background: var(--bg-surface); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; }
.content-area { padding: 24px; flex: 1; overflow-y: auto; }

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

/* ── Topbar ── */
.page-title { font-size: 18px; font-weight: 700; }
.topbar-actions { display: flex; gap: 12px; align-items: center; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

/* ── Views (Tabs) ── */
.view { display: none; animation: fadeIn 0.3s ease; }
.view.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); }
.card-title { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-box { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 20px; text-align: center; box-shadow: var(--shadow-sm); }
.stat-box .value { font-size: 28px; font-weight: 800; color: var(--accent-500); margin-bottom: 4px; }
.stat-box .label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
.stat-box.hot .value { color: var(--danger-500); }

/* ── Alerts ── */
.alert { padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.alert.ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.alert.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.alert.warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

/* ── Buttons ── */
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: var(--radius-md); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; text-decoration: none; }
.btn-primary { background: var(--accent-500); color: white; }
.btn-primary:hover { background: var(--accent-600); }
.btn-outline { background: transparent; border-color: var(--border-subtle); color: var(--text-primary); }
.btn-outline:hover { background: var(--bg-surface-hover); }
.btn-danger { background: #fef2f2; color: var(--danger-500); border-color: #fecaca; }
.btn-danger:hover { background: var(--danger-500); color: white; }
.btn-sm { padding: 4px 10px; font-size: 12px; }

/* ── Forms ── */
.form-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary); }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); font-family: inherit; font-size: 14px; outline: none; transition: border 0.2s; background: var(--bg-surface); color: var(--text-primary); }
.form-control:focus { border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(180,83,31,0.1); }

/* ── Tables & Lists ── */
.table-wrap { overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 12px 16px; text-align: right; border-bottom: 1px solid var(--border-subtle); }
th { background: var(--bg-surface-hover); font-weight: 700; color: var(--text-secondary); }
tr:last-child td { border-bottom: 0; }
tr:hover td { background: var(--bg-surface-hover); }

/* ── Cafe Cards ── */
.cafe-item { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; margin-bottom: 12px; transition: box-shadow 0.2s; }
.cafe-item:hover { box-shadow: var(--shadow-md); }
.cafe-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.cafe-name { font-size: 15px; font-weight: 700; }
.cafe-meta { display: flex; gap: 16px; font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; flex-wrap: wrap; }
.cafe-actions { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 12px; border-top: 1px dashed var(--border-subtle); }

/* ── Pills ── */
.pill { display: inline-flex; padding: 2px 8px; border-radius: 50px; font-size: 11px; font-weight: 700; }
.pill.ok { background: #ecfdf5; color: #047857; }
.pill.warn { background: #fffbeb; color: #b45309; }
.pill.bad { background: #fef2f2; color: #b91c1c; }

/* ── Accordion ── */
details { border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: 12px; background: var(--bg-surface); }
summary { padding: 12px 16px; background: var(--bg-surface-hover); cursor: pointer; font-weight: 600; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
summary::-webkit-details-marker { display: none; }
details[open] summary { border-bottom: 1px solid var(--border-subtle); }
.details-body { padding: 16px; }

/* ── Utilities ── */
.flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.text-muted { color: var(--text-secondary); font-size: 12px; }
.mt-2 { margin-top: 8px; }
</style>
</head>
<body>

<div class="app-layout">
  <!-- منوی کناری مشترک -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Area -->
  <main class="main-content">
    <header class="topbar">
      <div class="page-title" id="current-page-title">داشبورد</div>
      <div class="topbar-actions">
        <div class="user-badge">مدیر کل</div>
      </div>
    </header>

    <div class="content-area">
      <?php if ($msg): ?><div class="alert err">⛔ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="alert ok">✅ <?= htmlspecialchars($ok) ?></div><?php endif; ?>
      <?php if ($tmpPass): ?>
        <div class="alert ok" style="background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8">
          🔑 رمز موقت: <b dir="ltr" style="font-family:monospace; font-size:16px;"><?= htmlspecialchars($tmpPass) ?></b>
        </div>
      <?php endif; ?>

      <!-- View: Dashboard -->
      <section id="view-dashboard" class="view <?= $currentView === 'dashboard' ? 'active' : '' ?>">
        <?php if ($stats['expiring'] > 0): ?>
          <div class="alert err flex-between">
            <div>
              <b style="display:block">⚠️ هشدار انقضای نزدیک</b>
              <span class="text-muted" style="color:#991b1b"><?= pnum($stats['expiring']) ?> کافه تا کمتر از ۷ روز دیگر منقضی می‌شوند.</span>
            </div>
            <form method="post" style="margin:0">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="renewexpiring">
              <input type="hidden" name="days" value="30">
              <button class="btn btn-danger btn-sm">تمدید ۳۰ روزه</button>
            </form>
          </div>
        <?php endif; ?>

        <div class="grid-stats">
          <div class="stat-box"><div class="value"><?= $stats['total'] ?></div><div class="label">کل کافه‌ها</div></div>
          <div class="stat-box"><div class="value"><?= $stats['active'] ?></div><div class="label">فعال</div></div>
          <div class="stat-box"><div class="value"><?= $stats['trial'] ?></div><div class="label">آزمایشی</div></div>
          <div class="stat-box <?= $stats['expiring'] ? 'hot' : '' ?>"><div class="value"><?= $stats['expiring'] ?></div><div class="label">نزدیک انقضا</div></div>
          <div class="stat-box"><div class="value"><?= $stats['orders24h'] ?></div><div class="label">سفارش ۲۴ساعت</div></div>
          <div class="stat-box"><div class="value"><?= pnum(round($totalMb, 1)) ?></div><div class="label">MB مصرفی</div></div>
        </div>
      </section>

      <!-- View: Cafes -->
      <section id="view-cafes" class="view <?= $currentView === 'cafes' ? 'active' : '' ?>">
        <div class="card">
          <div class="card-header"><div class="card-title">🏪 مدیریت کافه‌ها</div></div>
          <div class="form-row" style="margin-bottom:16px">
            <input type="text" id="q" class="form-control" placeholder="جستجوی نام یا اسلاگ کافه..." style="flex:1; min-width:200px" oninput="cafFilter()">
            <select id="filter-status" class="form-control" style="width:auto" onchange="cafFilter()">
              <option value="all">همه وضعیت‌ها</option>
              <option value="active">فعال</option>
              <option value="trial">آزمایشی</option>
            </select>
          </div>
          <?php foreach ($list as $x): $lic = $x['lic']; $det = plat_details($x['slug']); ?>
            <div class="cafe-item" data-cafe="<?= htmlspecialchars(mb_strtolower($x['name'] . ' ' . $x['slug'])) ?>" data-st="<?= htmlspecialchars($lic['status']) ?>">
              <div class="cafe-header">
                <div class="cafe-name"><?= htmlspecialchars($x['name']) ?> <span class="pill <?= $x['active'] ? 'ok' : 'bad' ?>"><?= $x['active'] ? 'فعال' : 'غیرفعال' ?></span></div>
                <div class="text-muted" dir="ltr">/<?= htmlspecialchars($x['slug']) ?></div>
              </div>
              <div class="cafe-meta">
                <span>لایسنس: <?php if ($lic['status']==='active'): ?><span class="pill ok">فعال (<?= $lic['days_left'] ?> روز)</span><?php else: ?><span class="pill warn">آزمایشی (<?= $lic['days_left'] ?> روز)</span><?php endif; ?></span>
                <span>فضا: <b><?= pnum($x['size_mb']) ?> MB</b></span>
              </div>
              <div class="cafe-actions">
                <form method="post" class="form-row" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="renew"><input type="hidden" name="slug" value="<?= $x['slug'] ?>">
                  <input type="number" name="days" value="365" class="form-control" style="width:70px; padding:4px 8px">
                  <button class="btn btn-outline btn-sm">تمدید</button>
                </form>
                <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="resetpass"><input type="hidden" name="slug" value="<?= $x['slug'] ?>"><button class="btn btn-outline btn-sm">🔑 ریست رمز</button></form>
                <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="backupdl"><input type="hidden" name="slug" value="<?= $x['slug'] ?>"><button class="btn btn-outline btn-sm">📦 بکاپ</button></form>
                <form method="post" style="margin:0" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="slug" value="<?= $x['slug'] ?>"><button class="btn btn-danger btn-sm">حذف</button></form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- View: System -->
      <section id="view-system" class="view <?= $currentView === 'system' ? 'active' : '' ?>">
        <div class="card">
          <div class="card-header"><div class="card-title">🔄 بروزرسانی و سلامت</div></div>
          <p class="text-muted">نسخه نصب‌شده: <b dir="ltr"><?= htmlspecialchars($updVer) ?></b></p>
          <div class="form-row mt-2">
            <form method="post" enctype="multipart/form-data" class="flex-between" style="margin:0; width:100%">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="update_apply">
              <input type="file" name="upzip" accept=".zip" required class="form-control" style="width:200px">
              <button class="btn btn-primary">نصب آپدیت</button>
            </form>
            <form method="post" style="margin:0">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="update_scan">
              <button class="btn btn-outline">🩺 اسکن سلامت</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">💾 بکاپ و پایدارسازی</div></div>
          <?php if (!empty($__bak['list'])): ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>کافه</th><th>تاریخ</th><th>حجم</th><th>عملیات</th></tr></thead>
                <tbody>
                  <?php foreach ($__bak['list'] as $__b): ?>
                    <tr>
                      <td dir="ltr"><?= htmlspecialchars($__b['slug']) ?></td>
                      <td><?= htmlspecialchars(bak_human_ago($__b['ts'])) ?></td>
                      <td><?= htmlspecialchars(bak_human_size($__b['size'])) ?></td>
                      <td>
                        <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="downloadbackup"><input type="hidden" name="file" value="<?= htmlspecialchars($__b['name']) ?>"><button class="btn btn-outline btn-sm">📥</button></form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?><p class="text-muted">هنوز بکاپی گرفته نشده.</p><?php endif; ?>
        </div>
      </section>

      <!-- View: Plans & Pricing -->
      <section id="view-plans" class="view <?= $currentView === 'plans' ? 'active' : '' ?>">
        <div class="card">
          <div class="card-header"><div class="card-title">💰 مدیریت پلن‌ها و قیمت‌ها</div></div>
          <p style="margin-bottom:16px;color:#7D6C54;font-size:13px">
            تغییرات فوراً در <code>renew.php</code> اعمال می‌شود — بدون ری‌استارت. کلید پلن (free/basic/pro) قابل تغییر نیست.
          </p>
          <?php $__plans = giti_get_plans(); ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="act" value="save_plans">
            <?php foreach ($__plans as $__k => $__p): ?>
            <div class="card" style="margin-bottom:14px;background:#FBF6EB">
              <div style="margin-bottom:10px;font-size:15px;font-weight:700">
                <?= htmlspecialchars($__p['name_fa'] ?? $__k) ?>
                <code style="font-size:11px;color:#7D6C54;font-weight:400">(<?= htmlspecialchars($__k) ?>)</code>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
                <label style="font-size:12px">نام فارسی
                  <input type="text" name="plans[<?= $__k ?>][name_fa]" value="<?= htmlspecialchars($__p['name_fa'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid #DFD2B6;border-radius:8px;margin-top:4px">
                </label>
                <label style="font-size:12px">قیمت ماهانه (تومان)
                  <input type="number" min="0" name="plans[<?= $__k ?>][price_toman]" value="<?= (int)($__p['price_toman'] ?? 0) ?>" style="width:100%;padding:8px;border:1px solid #DFD2B6;border-radius:8px;margin-top:4px">
                </label>
                <label style="font-size:12px">حداکثر پرسنل (۰=نامحدود)
                  <input type="number" min="0" name="plans[<?= $__k ?>][max_users]" value="<?= (int)($__p['max_users'] ?? 0) ?>" style="width:100%;padding:8px;border:1px solid #DFD2B6;border-radius:8px;margin-top:4px">
                </label>
                <label style="font-size:12px">حداکثر آیتم منو (۰=نامحدود)
                  <input type="number" min="0" name="plans[<?= $__k ?>][max_menu_items]" value="<?= (int)($__p['max_menu_items'] ?? 0) ?>" style="width:100%;padding:8px;border:1px solid #DFD2B6;border-radius:8px;margin-top:4px">
                </label>
                <label style="font-size:12px">حداکثر سفارش ماهانه (۰=نامحدود)
                  <input type="number" min="0" name="plans[<?= $__k ?>][max_orders_month]" value="<?= (int)($__p['max_orders_month'] ?? 0) ?>" style="width:100%;padding:8px;border:1px solid #DFD2B6;border-radius:8px;margin-top:4px">
                </label>
                <label style="font-size:12px;display:flex;align-items:center;gap:6px;padding-top:22px">
                  <input type="checkbox" name="plans[<?= $__k ?>][sms_enabled]" value="1" <?= !empty($__p['sms_enabled']) ? 'checked' : '' ?>>
                  پیامک فعال
                </label>
              </div>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary" style="padding:10px 24px">💾 ذخیره همه پلن‌ها</button>
          </form>
        </div>
      </section>
      <!-- View: SMS Settings -->
      <section id="view-sms" class="view <?= $currentView === 'sms' ? 'active' : '' ?>">
        <div class="card" style="max-width:600px">
          <div class="card-header"><div class="card-title">📱 تنظیمات پنل پیامکی</div></div>
          <p style="margin-bottom:16px;color:#7D6C54;font-size:13px">
            API Key خود را از پنل (mydnspanel) دریافت و اینجا وارد کنید. تغییرات فوراً اعمال می‌شود.
          </p>
          <?php $__sms = json_decode((string)@file_get_contents(__DIR__.'/../data/platform-sms.json'), true) ?: []; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="act" value="save_sms">
            <label style="display:block;margin-bottom:12px;font-size:13px;font-weight:700">سرویس‌دهنده
              <select name="sms[provider]" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
                <option value="mydnspanel" <?= ($__sms['provider'] ?? '') === 'mydnspanel' ? 'selected' : '' ?>>mydnspanel</option>
                <option value="kavenegar" <?= ($__sms['provider'] ?? '') === 'kavenegar' ? 'selected' : '' ?>>Kavenegar</option>
              </select>
            </label>
            <label style="display:block;margin-bottom:12px;font-size:13px;font-weight:700">کلید API (API Key)
              <input type="text" name="sms[key]" value="<?= htmlspecialchars($__sms['key'] ?? '') ?>" dir="ltr" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
            </label>
            <label style="display:block;margin-bottom:20px;font-size:13px;font-weight:700">شماره فرستنده (اختیاری)
              <input type="text" name="sms[sender]" value="<?= htmlspecialchars($__sms['sender'] ?? '') ?>" dir="ltr" placeholder="اگر خالی باشد، از خط پیش‌فرض پنل استفاده می‌شود" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
            </label>
            <button type="submit" class="btn btn-primary" style="padding:10px 24px">💾 ذخیره تنظیمات</button>
          </form>
        </div>
      </section>
      <!-- View: Security & Logs -->
      <section id="view-security" class="view <?= $currentView === 'security' ? 'active' : '' ?>">
        <div class="card" style="max-width:500px">
          <div class="card-header"><div class="card-title">🔐 تغییر رمز مدیر</div></div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="act" value="change">
            <div class="form-group"><label>رمز فعلی</label><input type="password" name="old" class="form-control" required></div>
            <div class="form-group"><label>رمز جدید</label><input type="password" name="p1" class="form-control" required></div>
            <div class="form-group"><label>تکرار رمز جدید</label><input type="password" name="p2" class="form-control" required></div>
            <button class="btn btn-primary" style="width:100%">ثبت رمز جدید</button>
          </form>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">📜 لاگ عملیات</div></div>
          <div class="table-wrap" style="max-height:400px; overflow-y:auto;">
            <table>
              <thead><tr><th>زمان</th><th>رویداد</th></tr></thead>
              <tbody>
                <?php foreach ($aud as $a): ?>
                  <tr><td style="white-space:nowrap"><?= jdate((int)$a['ts'], 'm/d H:i') ?></td><td><b><?= htmlspecialchars(plat_ev_fa((string)$a['ev'])) ?></b></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </main>
</div>

<script>
function cafFilter() {
  var q = (document.getElementById('q').value || '').trim().toLowerCase();
  var st = document.getElementById('filter-status').value;
  document.querySelectorAll('.cafe-item').forEach(function(el) {
    var okQ = !q || el.getAttribute('data-cafe').indexOf(q) >= 0;
    var okS = st === 'all' || el.getAttribute('data-st') === st;
    el.style.display = (okQ && okS) ? '' : 'none';
  });
}
</script>
</body>
</html>