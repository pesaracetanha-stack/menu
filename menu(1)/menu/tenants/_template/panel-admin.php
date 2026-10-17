<?php
/* ═══════════════════════════════════════════════════════════════════════════
   پنل کافه‌دار — داشبورد
   ═══════════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap-i18n.php';
$slug = basename(__DIR__);
$sch   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? '';
$dirb  = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$menuLink = $sch . '://' . $host . $dirb . '/';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}
/* v5.0.3: پرسنل (صندوق/آشپزخانه) به پنل مدیریت راه ندارد — بازگشت به hub */
if (($_SESSION['tenant_role'] ?? 'admin') !== 'admin') {
  header('Location: hub.php'); exit;
}

require_once __DIR__ . '/api/lib/db.php';
require_once __DIR__ . '/api/lib/auth.php';
$db  = t_db_get();
$lic = t_license($db);
/* LICENSE_GATE_V1: قفل مرحله‌ای پس از انقضا */
$__licDays = (int)($lic['days_left'] ?? 999);
$__licStat = (string)($lic['status'] ?? 'active');
if (($__licStat === 'active' || $__licStat === 'trial') && $__licDays < -3) {
  header('Location: expired.php'); exit;
}
$S   = $db['settings'];
$brand = $db['menu']['brand'] ?? [];

/* آمار امروز */
$todayStart = strtotime('today') * 1000;
$today = array_filter($db['orders'], fn($o) => ($o['ts'] ?? 0) >= $todayStart);
$todayCount = count($today);
$todaySum   = array_sum(array_column($today, 'total'));

/* پیامک/چاپ‌پذیر بودن ساعت‌ها */
$faNum = fn($n) => str_replace(array_map('strval', range(0,9)), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل <?= htmlspecialchars($brand['name'] ?: $slug) ?> — GitiArts</title>
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
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); }
.card-title { font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-bottom: 20px; }
.stat-box { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; text-align: center; box-shadow: var(--shadow-sm); }
.stat-box .value { font-size: 24px; font-weight: 900; color: var(--accent-500); margin-bottom: 4px; }
.stat-box .label { font-size: 12px; color: var(--text-secondary); font-weight: 600; }

/* ── Buttons ── */
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: var(--radius-md); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; text-decoration: none; }
.btn-primary { background: var(--text-primary); color: var(--bg-surface); }
.btn-primary:hover { background: #3a2d1d; }
.btn-outline { background: transparent; border-color: var(--border-subtle); color: var(--text-primary); }
.btn-outline:hover { background: var(--bg-surface-hover); }
.btn-danger { background: #FBEAE4; color: var(--danger-500); border-color: #EFC5B6; }
.btn-danger:hover { background: var(--danger-500); color: white; }
.btn-big { padding: 16px; font-size: 15px; flex-direction: column; gap: 8px; }
.btn-big .icon { font-size: 24px; }

/* ── Forms & Inputs ── */
input[type=number], input[type=text] { padding: 8px 12px; border: 1px solid var(--border-subtle); border-radius: 8px; font-family: inherit; font-size: 13px; background: #fff; color: var(--text-primary); }
input:focus { outline: none; border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(180,83,31,.1); }

/* ── Utilities ── */
.flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.text-muted { color: var(--text-secondary); font-size: 12px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }

/* ── License Banner ── */
.lic-banner { border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px; border: 1.5px solid; display: flex; flex-direction: column; gap: 8px; }
.lic-banner.trial { background: rgba(176,124,31,.09); border-color: var(--warning-500); }
.lic-banner.trial.soon { background: rgba(169,59,42,.08); border-color: var(--danger-500); }
.lic-banner.active { background: rgba(79,122,61,.09); border-color: var(--success-500); }
.lic-banner h3 { font-size: 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 800; }
.lic-banner .bar { height: 8px; background: rgba(0,0,0,.1); border-radius: 99px; overflow: hidden; margin: 8px 0 4px; }
.lic-banner .bar i { display: block; height: 100%; border-radius: 99px; transition: width .5s; }
.lic-banner p { font-size: 12px; color: var(--text-secondary); margin: 0; }
.lic-banner a { color: var(--accent-500); font-weight: 800; text-decoration: none; }

/* ── Views ── */
.view { display: none; animation: fadeIn 0.3s ease; }
.view.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* ── Tables & Lists ── */
.table-wrap { overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 12px 16px; text-align: right; border-bottom: 1px solid var(--border-subtle); }
th { background: var(--bg-surface-hover); font-weight: 700; color: var(--text-secondary); }
tr:last-child td { border-bottom: 0; }
tr:hover td { background: var(--bg-surface-hover); }
</style>
</head>
<body>

<div class="app-layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">🌱 <?= htmlspecialchars($brand['name'] ?: 'کافه من') ?></div>
    <ul class="nav-menu">
      <li class="nav-item active" onclick="showView('dashboard', this)">
        <span class="icon">📊</span> داشبورد
      </li>
      <li class="nav-item" onclick="window.location.href='index.html'">
        <span class="icon">🎨</span> طراحی منو
      </li>
      <li class="nav-item" onclick="window.location.href='cashier.html'">
        <span class="icon">💳</span> صندوق
      </li>
      <li class="nav-item" onclick="window.location.href='kds.html'">
        <span class="icon">🍳</span> آشپزخانه
      </li>
      
      <div class="nav-divider"></div>
      
      <li class="nav-item" onclick="showView('customers', this)">
        <span class="icon">👥</span> باشگاه مشتریان
      </li>
      <li class="nav-item" onclick="showView('settings', this)">
        <span class="icon">⚙️</span> بکاپ و تنظیمات
      </li>
      
      <div class="nav-divider"></div>
      
      <li class="nav-item" onclick="window.location.href='tickets.php'">
        <span class="icon">🎫</span> پشتیبانی
      </li>
      <li class="nav-item" onclick="window.location.href='renew.php'">
        <span class="icon">💳</span> تمدید اشتراک
      </li>
    </ul>
    <div class="sidebar-footer">
      <a href="logout.php" class="btn btn-outline" style="width:100%; color:white; border-color:rgba(255,255,255,0.2)">🚪 خروج از پنل</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="topbar">
      <div style="font-size:18px; font-weight:700;" id="page-title">داشبورد</div>
      <div class="flex-between">
        <span class="text-muted" style="background:var(--bg-canvas); padding:6px 16px; border-radius:50px; border:1px solid var(--border-subtle); font-weight:600">مدیر کافه</span>
      </div>
    </header>

    <div class="content-area">
      
      <!-- View: Dashboard -->
      <section id="view-dashboard" class="view active">
        <!-- License Banner -->
        <?php if ($lic['status']==='trial'): $pct=min(100,round($lic['days_left']/14*100)); $soon=$lic['days_left']<=3; $done=$lic['days_left']<=0; ?>
          <div class="lic-banner trial <?= $done?'soon':($soon?'soon':'') ?>">
            <h3><span><?= $done?'⛔ دوره آزمایشی تمام شد':'⏳ دوره آزمایشی' ?></span> <b><?= $done?'۰':$faNum($lic['days_left']) ?> روز مانده</b></h3>
            <div class="bar"><i style="width:<?= $done?0:$pct ?>%; background:<?= $soon?'var(--danger-500)':'var(--warning-500)' ?>"></i></div>
            <p><?= $done ? 'سفارش‌گیری متوقف شده. برای فعال‌سازی با پشتیبانی تماس بگیرید: ' : 'پس از پایان دوره، سفارش‌گیری متوقف می‌شود. تمدید: ' ?> <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p>
          </div>
        <?php elseif ($lic['status']==='active'): $soon=$lic['days_left']<=30; ?>
          <div class="lic-banner active">
            <h3><span><?= $soon?'🔔 اشتراک فعال — نیاز به تمدید':'✅ اشتراک فعال' ?></span> <b><?= $faNum($lic['days_left']) ?> روز</b></h3>
            <?php if ($soon): ?><div class="bar"><i style="width:<?= min(100,round($lic['days_left']/365*100)) ?>%; background:var(--warning-500)"></i></div><p>برای تمدید با پشتیبانی تماس بگیرید: <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="lic-banner trial soon"><h3><span>⛔ اشتراک معلق است</span></h3><p>برای رفع با پشتیبانی تماس بگیرید: <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid-stats">
          <div class="stat-box"><div class="value"><?= $faNum($todayCount) ?></div><div class="label">سفارش امروز</div></div>
          <div class="stat-box"><div class="value"><?= toman($todaySum) ?></div><div class="label">فروش امروز (تومان)</div></div>
          <div class="stat-box"><div class="value"><?= $faNum(count($db['customers'])) ?></div><div class="label">اعضای باشگاه</div></div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header"><div class="card-title">🛠 دسترسی سریع</div></div>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <a href="index.html" class="btn btn-primary btn-big"><span class="icon">🎨</span> طراحی منو</a>
            <a href="kds.html" class="btn btn-outline btn-big"><span class="icon">🍳</span> آشپزخانه</a>
            <a href="cashier.html" class="btn btn-outline btn-big"><span class="icon">💳</span> صندوق</a>
            <a href="index.html" target="_blank" class="btn btn-outline btn-big"><span class="icon">👁</span> منوی مشتری</a>
          </div>
          <p class="text-muted mt-2">«طراحی منو» همان اپ کامل است: منو، سفارش‌ها، میزها، گزارش‌ها و تنظیمات.</p>
        </div>

        <!-- Public Menu & QR -->
        <div class="card">
          <div class="card-header"><div class="card-title">🔗 منوی عمومی مشتریان</div></div>
          <div class="flex-between" style="padding:10px 0; border-bottom:1px dashed var(--border-subtle)">
            <span>لینک منو (بدون میز):</span>
            <b dir="ltr" style="font-size:12px"><a href="index.html" target="_blank" style="color:var(--accent-500)"><?= htmlspecialchars($menuLink) ?></a></b>
          </div>
          <details style="margin-top:12px">
            <summary style="cursor:pointer; font-weight:700; font-size:13px; list-style:none">📱 QR میزها (ساخت و چاپ)</summary>
            <div id="qrs" style="margin-top:12px"></div>
            <div class="flex-between mt-2">
              <div style="display:flex; gap:8px; align-items:center;">
                <label class="text-muted">تعداد میز:</label>
                <input type="number" id="tcount" value="<?= max(1,count($db['tables'])) ?>" min="1" max="60" style="width:70px">
                <button class="btn btn-outline" onclick="genQR()">ساخت QRها</button>
              </div>
              <button class="btn btn-primary" onclick="window.print()">🖨 چاپ</button>
            </div>
            <div id="qrprint" style="display:none"></div>
          </details>
        </div>
      </section>

      <!-- View: Customers -->
      <section id="view-customers" class="view">
        <div class="card">
          <div class="card-header"><div class="card-title">👥 باشگاه مشتریان (<?= $faNum(count($db['customers'])) ?> عضو)</div></div>
          <?php if (!count($db['customers'])): ?>
            <p class="text-muted">هنوز عضوی ثبت نشده.</p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>شماره تماس</th><th>نام</th><th>امتیاز</th></tr></thead>
                <tbody>
                  <?php foreach (array_reverse($db['customers']) as $c): ?>
                    <tr>
                      <td dir="ltr"><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                      <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
                      <td><b><?= $faNum($c['points'] ?? 0) ?></b> امتیاز</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- View: Settings & Backup -->
      <section id="view-settings" class="view">
        <div class="card">
          <div class="card-header"><div class="card-title">💾 پشتیبان داده‌ها</div></div>
          <p class="text-muted" style="margin-bottom:12px">یک فایل JSON از منو، سفارش‌ها و مشتریان دانلود کنید و جای امن نگه دارید.</p>
          <a class="btn btn-outline" href="api/index.php?action=backup" download="backup-<?= $slug ?>.json">⬇ دانلود پشتیبان</a>
        </div>

        <div class="card" id="exportCard">
          <div class="card-header"><div class="card-title">📦 فایل نصبی (زیپ کامل)</div></div>
          <div id="exportBody"><p class="text-muted">در حال بررسی...</p></div>
        </div>
      </section>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
'use strict';
const SLUG = <?= json_encode($slug) ?>;

// ── Navigation Logic ──
function showView(viewId, el) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.getElementById('view-' + viewId).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (el) el.classList.add('active');
  
  const titles = { 'dashboard': 'داشبورد', 'customers': 'باشگاه مشتریان', 'settings': 'بکاپ و تنظیمات' };
  document.getElementById('page-title').innerText = titles[viewId] || 'پنل';
}

// ── Export Logic ──
async function apiAct(action, body){
  const r = await fetch('api/index.php?action='+action, {
    method: body!==undefined ? 'POST' : 'GET',
    headers: {'Content-Type':'application/json'},
    body: body!==undefined ? JSON.stringify(body) : undefined
  });
  return r.json();
}
function esc(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
async function loadExport(){
  const el = document.getElementById('exportBody');
  try{
    const j = await apiAct('export_status');
    if(!j.ok){ el.innerHTML = '<p class="text-muted">خطا در دریافت وضعیت</p>'; return; }
    const req = j.data && j.data.req, mode = j.data && j.data.mode;
    if(mode === 'standalone'){
      el.innerHTML = '<p class="text-muted">✅ این نسخه مستقل است. برای پشتیبانی با <span dir="ltr">+98 919 834 5661</span> (تلگرام) در تماس باشید.</p>';
      return;
    }
    let html = '';
    if(!req){
      html = '<p class="text-muted" style="margin-bottom:8px">فایل نصبی کامل (منو + داده‌ها) برای نصب روی هاست خودت از طریق مدیر پلتفرم صادر می‌شود. درخواست ثبت کن تا پس از تأیید، لینک دانلود همین‌جا نمایش داده شود.</p>' +
             '<input id="expNote" type="text" placeholder="توضیح (اختیاری) — مثلاً شماره تراکنش" style="width:100%;padding:8px;border:1px solid var(--border-subtle);border-radius:8px;font:inherit;margin-bottom:8px">' +
             '<button class="btn btn-primary" id="expBtn">📨 ثبت درخواست به مدیر</button>';
    } else if(req.status === 'pending'){
      html = '<p style="color:var(--success-500);font-weight:700">📨 درخواست شما ثبت شد و در انتظار تأیید مدیر است.</p><p class="text-muted">به‌محض تأیید، لینک دانلود (۷۲ ساعته) همین‌جا ظاهر می‌شود.</p>';
    } else if(req.status === 'approved' && !req.used){
      html = '<p style="color:var(--success-500);font-weight:700">✅ تأیید شد! لینک دانلود آماده است (یک‌بارمصرف — ' + esc(new Date(req.expires*1000).toLocaleString('fa-IR')) + ' منقضی می‌شود)</p>' +
             '<p class="text-muted" style="margin:6px 0">لینک را فقط از مدیر پلتفرم بگیرید؛ لینک این بخش برای امنیت فعال نمی‌شود.</p>' +
             '<button class="btn btn-outline" onclick="alert(\'لینک دانلود را مدیر برای تو می‌فرستد — از تلگرام/تماس\')">ℹ️ لینک کجاست؟</button>';
    } else if(req.status === 'approved' && req.used){
      html = '<p class="text-muted">⬇ فایل قبلاً دانلود شده. اگر دوباره نیاز داری، درخواست جدید ثبت کن.</p>' +
             '<button class="btn btn-outline" onclick="reqExport()">📨 درخواست مجدد</button>';
    } else if(req.status === 'denied'){
      html = '<p style="color:var(--danger-500);font-weight:700">درخواست رد شد. برای پیگیری با پشتیبانی در تماس باش.</p>' +
             '<button class="btn btn-outline" onclick="reqExport()">📨 درخواست جدید</button>';
    }
    el.innerHTML = html;
    const b = document.getElementById('expBtn');
    if(b) b.onclick = reqExport;
  }catch(e){ el.innerHTML = '<p class="text-muted">خطا در ارتباط</p>'; }
}
async function reqExport(){
  const noteEl = document.getElementById('expNote');
  const note = noteEl ? noteEl.value : '';
  const j = await apiAct('export_request', {note});
  alert(j.ok ? (j.data.msg || 'ثبت شد') : (j.err || 'خطا'));
  loadExport();
}
loadExport();

// ── QR Generation ──
function genQR(){
  const n = Math.min(60, Math.max(1, parseInt(document.getElementById('tcount').value)||1));
  const box = document.getElementById('qrs'); box.innerHTML = '';
  box.style.display='grid'; box.style.gridTemplateColumns='1fr 1fr'; box.style.gap='10px';
  for(let i=1;i<=n;i++){
    const url = location.origin + location.pathname.replace(/panel-admin\.php.*/,'') + 'index.html?table='+i;
    let imgHTML = '<div style="color:#888;font-size:10px">QR</div>';
    try{
      const q = qrcode(0,'M'); q.addData(url); q.make();
      imgHTML = '<img src="'+q.createDataURL(4,6)+'" style="width:100%">';
    }catch(e){}
    box.insertAdjacentHTML('beforeend','<div style="border:1px dashed #CBBB99;border-radius:10px;padding:8px;text-align:center;background:#fff"><b style="font-size:12px">میز '+String(i).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])+'</b>'+imgHTML+'</div>');
  }
}
</script>
</body>
</html>