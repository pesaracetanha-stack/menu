<?php
/* ════════════════════════════════════════════════════════════
   دسترسی‌های کافه — صفحهٔ میانی بعد از ورود
   لینک‌های اختصاصی: مدیریت (panel-admin) · صندوق (cashier) · آشپزخانه (kds)
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);
$slug = basename(__DIR__);
$sch   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? '';
$dirb  = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$base  = $sch . '://' . $host . $dirb;
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}

require_once __DIR__ . '/api/lib/db.php';
require_once __DIR__ . '/api/lib/auth.php';
$db    = t_db_get();
$lic   = t_license($db);
/* LICENSE_GATE_V1: قفل مرحله‌ای پس از انقضا */
$__licDays = (int)($lic['days_left'] ?? 999);
$__licStat = (string)($lic['status'] ?? 'active');
if (($__licStat === 'active' || $__licStat === 'trial') && $__licDays < -3) {
  header('Location: expired.php'); exit;
}
$brand = $db['menu']['brand']['name'] ?? $slug;
$user  = $_SESSION['tenant_user'] ?? 'مدیر';
$role  = $_SESSION['tenant_role'] ?? 'admin';

$links = [
  ['ic'=>'⚙️','t'=>'پنل مدیریت','d'=>'منو، طراحی، سفارش‌ها، مشتریان و تنظیمات','url'=>'panel-admin.php?role=admin','full'=>$base.'/panel-admin.php?role=admin'],
  ['ic'=>'🎨','t'=>'طراحی منو','d'=>'ساخت و ویرایش منو، دسته‌بندی، آیتم و تصاویر','url'=>'index.html','full'=>$base.'/index.html'],
  ['ic'=>'💳','t'=>'صندوق','d'=>'ثبت سفارش حضوری، رسید و تسویه','url'=>'cashier.php','full'=>$base.'/cashier.php'],
  ['ic'=>'🍳','t'=>'آشپزخانه','d'=>'صف سفارش‌های زنده و «آماده شد»','url'=>'kds.php','full'=>$base.'/kds.php'],
  ['ic'=>'🎫','t'=>'پشتیبانی','d'=>'ارسال تیکت و پیگیری درخواست‌ها','url'=>'tickets.php','full'=>$base.'/tickets.php'],
  ['ic'=>'📢','t'=>'اعلانات','d'=>'اطلاعیه‌های تیم GitiArts','url'=>'announcements.php','full'=>$base.'/announcements.php'],
  ['ic'=>'💳','t'=>'تمدید اشتراک','d'=>'تمدید یا ارتقای پلن','url'=>'renew.php','full'=>$base.'/renew.php'],
];
/* v5: پرسنل (ورود با پین) فقط صندوق و آشپزخانه را می‌بیند — کارت مدیریت حذف می‌شود */
$isStaff = ($_SESSION['tenant_role'] ?? 'admin') !== 'admin';

/* ANNOUNCEMENT_POPUP_V1 */
$__annoUnread = null;
try {
  $__annoF = dirname(__DIR__, 2) . '/data/announcements.json';
  if (is_file($__annoF)) {
    $__aj = json_decode((string)@file_get_contents($__annoF), true);
    if (is_array($__aj) && !empty($__aj['items'])) {
      $__now = time();
      foreach ($__aj['items'] as $__a) {
        if (empty($__a['active'])) continue;
        if (!empty($__a['expires']) && (int)$__a['expires'] < $__now) continue;
        $__tg = $__a['target'] ?? 'all';
        if ($__tg !== 'all' && is_array($__tg) && !in_array($slug, $__tg, true)) continue;
        $__rb = $__a['read_by'] ?? [];
        if (in_array($slug, $__rb, true)) continue;
        $__annoUnread = $__a;
        break;
      }
    }
  }
} catch (Throwable $e) { $__annoUnread = null; }

/* HUB_UNREAD_BADGE_V1: پیام‌های نخوانده تیکت */
$__tkUnread = 0;
try {
  $__tkF = dirname(__DIR__, 2) . '/data/tickets.json';
  if (is_file($__tkF)) {
    $__tkD = json_decode((string)@file_get_contents($__tkF), true);
    if (is_array($__tkD) && isset($__tkD['tickets'])) {
      $__now = time();
      foreach ($__tkD['tickets'] as $__t) {
        if (($__t['slug'] ?? '') !== $slug) continue;
        if (($__t['status'] ?? '') === 'closed') continue;
        $__cafeRead = (int)($__t['cafe_read_at'] ?? 0);
        foreach (($__t['messages'] ?? []) as $__m) {
          if (($__m['from'] ?? '') !== 'admin') continue;
          if ((int)($__m['ts'] ?? 0) > $__cafeRead) $__tkUnread++;
        }
      }
    }
  }
} catch (Throwable $e) { $__tkUnread = 0; }
if ($isStaff) $links = array_values(array_filter($links, fn($l) => !in_array($l['url'], ['panel-admin.php?role=admin','index.html'], true)));
$faNum = fn($n) => str_replace(array_map('strval', range(0,9)), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);

/* FIX_BANNER_BTN_V1 */ /* FIX_BANNER_ORDER_V1: بنر هشدار انقضا (بعد از faNum) */
$__days = (int)($lic['days_left'] ?? 999);
$__status = (string)($lic['status'] ?? 'active');
$__showBanner = false; $__bColor = ''; $__bText = '';
if ($__status === 'active' && $__days <= 0) {
  $__showBanner = true; $__bColor = '#B4531F';
  $__bText = '🚨 اشتراک شما منقضی شده — برای ادامه سرویس، همین امروز تمدید کنید.';
} elseif ($__status === 'trial' && $__days <= 0) {
  $__showBanner = true; $__bColor = '#B4531F';
  $__bText = '🚨 دورهٔ آزمایشی تمام شد — برای ادامه، اشتراک تهیه کنید.';
} elseif ($__status === 'active' && $__days <= 3) {
  $__showBanner = true; $__bColor = '#B4801F';
  $__bText = '⚠️ تنها ' . $faNum($__days) . ' روز تا پایان اشتراک — لطفاً تمدید کنید.';
} elseif ($__status === 'trial' && $__days <= 3) {
  $__showBanner = true; $__bColor = '#B4801F';
  $__bText = '⚠️ تنها ' . $faNum($__days) . ' روز از دورهٔ آزمایشی باقی مانده — برای ادامه، اشتراک تهیه کنید.';
} elseif ($__status === 'active' && $__days <= 7) {
  $__showBanner = true; $__bColor = '#8B7355';
  $__bText = '🔔 ' . $faNum($__days) . ' روز تا پایان اشتراک باقی مانده.';
} elseif ($__status === 'trial' && $__days <= 7) {
  $__showBanner = true; $__bColor = '#8B7355';
  $__bText = '🔔 ' . $faNum($__days) . ' روز از دورهٔ آزمایشی باقی مانده.';
} elseif ($__status === 'suspended') {
  $__showBanner = true; $__bColor = '#B4531F';
  $__bText = '🚫 حساب کاربری شما معلق شده — با پشتیبانی تماس بگیرید.';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دسترسی‌های <?= htmlspecialchars((string)$brand) ?> — GitiArts</title>
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
.sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.page-title { font-size: 18px; font-weight: 700; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

/* ── Alerts ── */
.alert { padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.alert.ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.alert.warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.alert.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* ── Quick Links ── */
.quick-links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.quick-link-card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; text-align: center; text-decoration: none; color: var(--text-primary); display: flex; flex-direction: column; align-items: center; transition: all 0.2s; box-shadow: var(--shadow-sm); position: relative; }
.quick-link-card:hover { border-color: var(--accent-500); transform: translateY(-4px); box-shadow: var(--shadow-md); }
.quick-link-card .icon { font-size: 32px; margin-bottom: 12px; }
.quick-link-card b { font-size: 16px; display: block; margin-bottom: 6px; font-weight: 800; }
.quick-link-card p { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }
.badge { position: absolute; top: 12px; left: 12px; background: var(--danger-500); color: #fff; border-radius: 50px; min-width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; padding: 0 8px; }

/* ── Copy Links ── */
.copy-links { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px; margin-top: 24px; box-shadow: var(--shadow-sm); }
.copy-links .title { font-size: 15px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.copy-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-subtle); font-size: 13px; }
.copy-row:last-child { border-bottom: 0; padding-bottom: 0; }
.copy-row .label { color: var(--text-primary); font-weight: 500; display: flex; align-items: center; gap: 6px; min-width: 120px; }
.copy-row code { direction: ltr; display: inline-block; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 6px 12px; font-size: 12px; color: var(--text-secondary); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; text-align: left; font-family: monospace; }
.copy-row button { border: 1px solid var(--border-subtle); background: var(--bg-surface); color: var(--text-primary); border-radius: 8px; padding: 6px 16px; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
.copy-row button:hover { background: var(--accent-500); color: #fff; border-color: var(--accent-500); }

/* ── Setup Checklist ── */
.setup-card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
.setup-card .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); }
.setup-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; color: var(--text-primary); text-decoration: none; border-bottom: 1px dashed var(--border-subtle); font-size: 14px; transition: opacity 0.2s; }
.setup-item:last-child { border-bottom: 0; }
.setup-item:hover { opacity: 0.8; }
.setup-item.done .text { text-decoration: line-through; opacity: 0.5; }
.setup-item .action { color: var(--accent-500); font-size: 13px; font-weight: 700; }

/* ── Modal ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px); }
.modal-content { background: var(--bg-surface); border-radius: 20px; max-width: 520px; width: 100%; max-height: 90vh; overflow: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.modal-head { padding: 24px 24px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-subtle); }
.modal-body { padding: 24px; font-size: 14px; line-height: 2; color: var(--text-primary); white-space: pre-wrap; word-wrap: break-word; }
.modal-footer { padding: 16px 24px 24px; }
.modal-btn { width: 100%; padding: 14px; background: var(--accent-500); color: #fff; border: 0; border-radius: 12px; font-family: inherit; font-size: 15px; font-weight: 800; cursor: pointer; transition: background 0.2s; }
.modal-btn:hover { background: var(--accent-600); }

@media (max-width: 600px) {
  .quick-links-grid { grid-template-columns: 1fr; }
  .copy-row { flex-direction: column; align-items: flex-start; gap: 8px; }
  .copy-row code { width: 100%; text-align: center; }
  .copy-row button { width: 100%; }
}
</style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">🚀 دسترسی‌های کافه</div>
      <div class="user-badge"><?= htmlspecialchars((string)$user) ?></div>
    </header>

    <div class="content-area">
      <?php if ($lic['ok']): ?>
        <div class="alert ok">
          <span style="font-size:20px">✅</span>
          <span>لایسنس <?= $lic['status']==='active' ? 'فعال' : 'آزمایشی' ?> <?= $lic['days_left'] !== null ? '— ' . $faNum($lic['days_left']) . ' روز باقی‌مانده' : '' ?></span>
        </div>
      <?php else: ?>
        <div class="alert err">
          <span style="font-size:20px">⏳</span>
          <span>لایسنس منقضی یا معلق است — برای تمدید با پشتیبانی در تماس باشید.</span>
        </div>
      <?php endif; ?>

      <?php if ($__showBanner): ?>
        <div class="alert warn" style="justify-content:space-between;flex-wrap:wrap">
          <div style="font-size:14px;color:<?= $__bColor ?>;font-weight:700;line-height:1.6"><?= htmlspecialchars($__bText) ?></div>
          <button type="button" onclick="location.href='renew.php'" style="background:<?= $__bColor ?>;color:#fff;padding:8px 18px;border-radius:10px;border:0;cursor:pointer;font-family:inherit;font-weight:800;font-size:13px;white-space:nowrap">🔄 تمدید اشتراک</button>
        </div>
      <?php endif; ?>

      <?php /* SETUP_CHECKLIST_V1 */
        $__cl = [
          ['t' => 'افزودن اولین دسته‌بندی منو', 'u' => 'index.html',                              'd' => !empty($db['menu']['cats'] ?? [])],
          ['t' => 'تنظیم ساعت کاری کافه',        'u' => 'panel-admin.php?role=admin&tab=brand', 'd' => !empty((string)($db['menu']['brand']['hours']   ?? ''))],
          ['t' => 'افزودن آدرس کافه',            'u' => 'panel-admin.php?role=admin&tab=brand', 'd' => !empty((string)($db['menu']['brand']['address'] ?? ''))],
          ['t' => 'بارگذاری لوگو',               'u' => 'panel-admin.php?role=admin&tab=brand', 'd' => !empty((string)($db['menu']['brand']['logo']    ?? ''))],
        ];
        $__done  = count(array_filter($__cl, fn($x) => $x['d']));
        $__total = count($__cl);
      ?>
      <?php if ($__done < $__total && !$isStaff): ?>
      <div class="setup-card">
        <div class="head">
          <b>🚀 راهنمای شروع</b>
          <span style="font-size:12px;color:var(--text-secondary)"><?= $__done ?> از <?= $__total ?> انجام شد</span>
        </div>
        <?php foreach ($__cl as $__item): ?>
          <a href="<?= htmlspecialchars($__item['u']) ?>" class="setup-item <?= $__item['d'] ? 'done' : '' ?>">
            <span style="font-size:16px;flex:none"><?= $__item['d'] ? '✅' : '⬜' ?></span>
            <span class="text" style="flex:1"><?= htmlspecialchars($__item['t']) ?></span>
            <?php if (!$__item['d']): ?><span class="action">شروع ›</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Quick Links Grid -->
      <div class="quick-links-grid">
        <?php foreach ($links as $l): ?>
          <a class="quick-link-card" href="<?= htmlspecialchars($l['url']) ?>">
            <?php if ($l['url'] === 'tickets.php' && $__tkUnread > 0): ?><span class="badge"><?= $faNum($__tkUnread) ?></span><?php endif; ?>
            <span class="icon"><?= $l['ic'] ?></span>
            <b><?= htmlspecialchars($l['t']) ?></b>
            <p><?= htmlspecialchars($l['d']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Copy Links Section -->
      <div class="copy-links">
        <div class="title">🔗 لینک‌های اختصاصی (برای کپی و بوکمارک)</div>
        <?php foreach ($links as $l): ?>
          <div class="copy-row">
            <span class="label"><?= $l['ic'] ?> <?= htmlspecialchars($l['t']) ?></span>
            <code title="<?= htmlspecialchars($l['full']) ?>"><?= htmlspecialchars(mb_substr($l['full'], 0, 64)) ?><?= mb_strlen($l['full']) > 64 ? '…' : '' ?></code>
            <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($l['full']) ?>">کپی</button>
          </div>
        <?php endforeach; ?>
        <div class="copy-row">
          <span class="label">📱 منوی عمومی مشتریان</span>
          <code title="<?= htmlspecialchars($base) ?>/index.html"><?= htmlspecialchars(mb_substr($base, 0, 56)) ?>/…</code>
          <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($base) ?>/index.html">کپی</button>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
function cp(b){
  const u = b.dataset.u;
  (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject())
    .then(()=>{ b.textContent='کپی شد ✓'; setTimeout(()=>b.textContent='کپی',1400); })
    .catch(()=>{ const i=document.createElement('input'); i.value=u; document.body.appendChild(i);
      i.select(); document.execCommand('copy'); i.remove(); b.textContent='کپی شد ✓';
      setTimeout(()=>b.textContent='کپی',1400); });
}
</script>

<?php if ($__annoUnread): /* ANNOUNCEMENT_POPUP_HTML */ ?>
<div id="anno-popup" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-head">
      <span style="font-size:22px">📢</span>
      <div style="flex:1">
        <div style="font-size:16px;font-weight:800;color:var(--text-primary)"><?= htmlspecialchars($__annoUnread['title']) ?></div>
        <div style="font-size:11px;color:var(--text-secondary);margin-top:3px">اعلان از تیم GitiArts</div>
      </div>
    </div>
    <?php if (!empty($__annoUnread['image'])): ?>
    <div style="padding:0 24px;margin-top:16px">
      <img src="../../data/announcement-images/<?= htmlspecialchars($__annoUnread['image']) ?>" style="width:100%;border-radius:12px;display:block" alt="">
    </div>
    <?php endif; ?>
    <div class="modal-body">
      <?= htmlspecialchars($__annoUnread['body']) ?>
    </div>
    <div class="modal-footer">
      <button onclick="dismissAnno(<?= (int)$__annoUnread['id'] ?>)" class="modal-btn">متوجه شدم ✓</button>
    </div>
  </div>
</div>
<script>
function dismissAnno(id){
  var body = new URLSearchParams();
  body.append('id', id);
  fetch('announcements-dismiss.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: body.toString()
  }).finally(function(){ var p=document.getElementById('anno-popup'); if(p) p.remove(); });
}
</script>
<?php endif; ?>

</body>
</html>