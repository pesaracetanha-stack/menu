<?php
/* ════════════════════════════════════════════════════════════
   پنل کافه‌دار — داشبورد
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);
 $slug = basename(__DIR__);
/* v5: آدرس‌ها از خود درخواست محاسبه می‌شوند (قابل‌حمل روی هر دامنه/پوشه) */
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
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#EFE7D6;--panel:#FBF6EB;--panel2:#F2EADA;--ink:#271D12;--ink2:#7D6C54;--ink3:#AC9C81;
 --line:#DFD2B6;--line2:#CBBB99;--acc:#B4531F;--ok:#4F7A3D;--bad:#A93B2A;--warn:#B07C1F}
body{font-family:Vazirmatn,sans-serif;background:var(--bg);color:var(--ink);line-height:1.8;min-height:100dvh;padding:20px 14px 60px}
.wrap{max-width:720px;margin:0 auto}
.hd{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.hd b{font-size:16px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:12px}
.sec-t{font-size:13.5px;font-weight:800;display:flex;gap:6px;align-items:center;margin-bottom:10px}
/* نوار لایسنس */
.lic{border-radius:16px;padding:16px;margin-bottom:12px;border:1.5px solid}
.lic.trial{background:rgba(176,124,31,.09);border-color:#B07C1F}
.lic.trial.soon{background:rgba(169,59,42,.08);border-color:var(--bad)}
.lic.trial.done{background:rgba(169,59,42,.14)}
.lic.active{background:rgba(79,122,61,.09);border-color:var(--ok)}
.lic h3{font-size:14.5px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.lic .bar{height:9px;background:rgba(0,0,0,.1);border-radius:99px;overflow:hidden;margin:10px 0 6px}
.lic .bar i{display:block;height:100%;border-radius:99px;transition:width .5s}
.lic p{font-size:12px;color:var(--ink2)}
.lic a{color:var(--acc);font-weight:800;text-decoration:none}
/* آمار */
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.st{background:var(--panel2);border:1px solid var(--line);border-radius:12px;padding:10px;text-align:center}
.st b{display:block;font-size:19px;font-weight:900;color:var(--acc)}
.st span{font-size:11px;color:var(--ink2)}
/* دکمه‌ها */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid var(--ink);background:var(--ink);color:#FBF6EB;font:inherit;font-weight:800;font-size:13.5px;padding:11px 18px;border-radius:12px;cursor:pointer;text-decoration:none;transition:.15s}
.btn:hover{transform:translateY(-1px)}
.btn.ghost{background:transparent;color:var(--ink);border-color:var(--line2)}
.btn.big{width:100%;padding:14px;font-size:15px}
.btn.red{background:var(--bad);border-color:var(--bad)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.muted{color:var(--ink3);font-size:11.5px;line-height:2}
.row{display:flex;justify-content:space-between;gap:10px;font-size:13px;padding:7px 0;border-bottom:1px dashed var(--line);flex-wrap:wrap}
.row:last-child{border:0}
.row b{font-feature-settings:'tnum'}
input[type=number]{width:70px;padding:5px 8px;border:1px solid var(--line2);border-radius:8px;font:inherit}
details summary{cursor:pointer;font-weight:800;font-size:13px;list-style:none}
details summary::-webkit-details-marker{display:none}
details[open] summary{margin-bottom:8px}
</style>
</head>
<body>
<div class="wrap">

  <div class="hd">
    <b>🌿 <?= htmlspecialchars($brand['name'] ?: 'کافه من') ?> — پنل مدیریت</b>
    <a class="btn ghost" href="logout.php">خروج</a>
  </div>

  <!-- نوار لایسنس -->
  <?php $days = $lic['days_left']; if ($lic['status']==='trial'): $pct=min(100,round($days/14*100)); $soon=$days<=3; $done=$days<=0; ?>
  <div class="lic trial <?= $done?'done':($soon?'soon':'') ?>">
    <h3><span><?= $done?'⏰ دورهٔ آزمایشی تمام شد':'⏳ دورهٔ آزمایشی' ?></span>
        <b style="color:<?= $done?'var(--bad)':($soon?'var(--bad)':'var(--warn)') ?>"><?= $done?'۰':$faNum($days) ?> روز مانده</b></h3>
    <div class="bar"><i style="width:<?= $done?0:$pct ?>%;background:<?= $soon?'var(--bad)':'#B07C1F' ?>"></i></div>
    <p><?= $done
      ? 'سفارش‌گیری متوقف شده (منو نمایشی است). برای فعال‌سازی با پشتیبانی تماس بگیرید: '
      : 'تمام‌شدن آزمایشی یعنی سفارش‌گیری متوقف می‌شود (منو نمایشی می‌ماند). فعال‌سازی: ' ?>
      <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p>
  </div>
  <?php elseif ($lic['status']==='active'): $soon=$days<=30; ?>
  <div class="lic active">
    <h3><span><?= $soon?'🔔 اشتراک فعال — به‌زودی نیاز به تمدید':'✅ اشتراک فعال' ?></span>
        <b><?= $faNum($days) ?> روز</b></h3>
    <?php if ($soon): ?><div class="bar"><i style="width:<?= min(100,round($days/365*100)) ?>%;background:var(--warn)"></i></div>
    <p>برای تمدید با پشتیبانی تماس بگیرید: <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="lic trial done"><h3><span>⏰ اشتراک معلق است</span></h3>
    <p>برای رفع با پشتیبانی تماس بگیرید: <a dir="ltr" href="https://t.me/+989198345661">+98 919 834 5661</a></p></div>
  <?php endif; ?>

  <!-- آمار امروز -->
  <div class="card">
    <div class="sec-t">📊 امروز</div>
    <div class="grid3">
      <div class="st"><b><?= $faNum($todayCount) ?></b><span>سفارش</span></div>
      <div class="st"><b><?= number_format($todaySum) ?></b><span>تومان فروش</span></div>
      <div class="st"><b><?= count($db['customers']) ?></b><span>عضو باشگاه</span></div>
    </div>
  </div>

  <!-- ابزارهای اصلی -->
  <div class="card">
    <div class="sec-t">🛠 مدیریت</div>
    <div class="grid2" style="margin-bottom:10px">
      <a class="btn big" href="index.html">🎨 طراحی منو</a>
      <a class="btn big ghost" href="kds.html">🍳 آشپزخانه</a>
      <a class="btn big ghost" href="cashier.html">💳 صندوق</a>
      <a class="btn big ghost" href="index.html?role=cashier" onclick="alert('صفحهٔ مشتری از لینک منو باز می‌شود');return false">👁 منوی مشتری</a>
    </div>
    <p class="muted">«طراحی منو» همان اپ کامل است: منو، سفارش‌ها، میزها، گزارش‌ها و تنظیمات.</p>
  </div>

  <!-- لینک منو و QR میزها -->
  <div class="card">
    <div class="sec-t">🔗 منوی عمومی مشتریان</div>
    <div class="row"><span>لینک منو (بدون میز):</span><b dir="ltr"><a href="index.html" target="_blank"><?= htmlspecialchars($menuLink) ?></a></b></div>
    <details style="margin-top:8px">
      <summary>📱 QR میزها (ساخت و چاپ)</summary>
      <div id="qrs" style="margin-top:10px"></div>
      <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap">
        <label class="muted">تعداد میز:</label>
        <input type="number" id="tcount" value="<?= max(1,count($db['tables'])) ?>" min="1" max="60" style="width:70px">
        <button class="btn ghost" onclick="genQR()">ساخت QRها</button>
        <button class="btn ghost" onclick="window.print()">🖨 چاپ</button>
      </div>
      <div id="qrprint" style="display:none"></div>
    </details>
  </div>

  <!-- مشتریان باشگاه -->
  <div class="card">
    <div class="sec-t">👥 باشگاه مشتریان (<?= $faNum(count($db['customers'])) ?> عضو)</div>
    <?php if (!count($db['customers'])): ?><p class="muted">هنوز عضوی ثبت نشده.</p><?php endif; ?>
    <?php foreach (array_slice(array_reverse($db['customers']),0,8) as $c): ?>
      <div class="row"><b dir="ltr"><?= htmlspecialchars($c['phone'] ?? '') ?></b>
        <span><?= htmlspecialchars($c['name'] ?? '') ?></span><b><?= $faNum($c['points'] ?? 0) ?> امتیاز</b></div>
    <?php endforeach; ?>
  </div>

  <!-- پشتیبان -->
  <div class="card">
    <div class="sec-t">💾 پشتیبان داده‌ها</div>
    <p class="muted" style="margin-bottom:8px">یک فایل JSON از منو، سفارش‌ها و مشتریان دانلود کن و جای امن نگه دار.</p>
    <a class="btn ghost" href="api/index.php?action=backup" download="backup-<?= $slug ?>.json">⬇ دانلود پشتیبان</a>
  </div>

  <!-- درخواست فایل نصبی (تیکت) -->
  <div class="card" id="exportCard">
    <div class="sec-t">📦 فایل نصبی (زیپ کامل)</div>
    <div id="exportBody"><p class="muted">در حال بررسی…</p></div>
  </div>

  <p class="muted" style="text-align:center;margin-top:16px">سِرو · طراحی و اجرا توسط <b>HPR-GitiArts</b> · پشتیبانی: <span dir="ltr">+98 919 834 5661</span></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
'use strict';
const SLUG = <?= json_encode($slug) ?>;

/* ── فایل نصبی: وضعیت تیکت ── */
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
    if(!j.ok){ el.innerHTML = '<p class="muted">خطا در دریافت وضعیت</p>'; return; }
    const req = j.data && j.data.req, mode = j.data && j.data.mode;
    if(mode === 'standalone'){
      el.innerHTML = '<p class="muted">✅ این نسخه <b>مستقل</b> است و روی هاست خودت نصب شده. برای پشتیبانی و بروزرسانی با <span dir="ltr">+98 919 834 5661</span> (تلگرام) در تماس باش.</p>';
      return;
    }
    let html = '';
    if(!req){
      html = '<p class="muted" style="margin-bottom:8px">فایل نصبی کامل (منو + داده‌ها) برای نصب روی هاست خودت از طریق مدیر پلتفرم صادر می‌شود. درخواست ثبت کن تا پس از تأیید، لینک دانلود همین‌جا نمایش داده شود.</p>' +
             '<input id="expNote" type="text" placeholder="توضیح (اختیاری) — مثلاً شماره تراکنش" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #CBBB99;border-radius:10px;font:inherit;margin-bottom:8px">' +
             '<button class="btn ghost" id="expBtn">📨 ثبت درخواست به مدیر</button>';
    } else if(req.status === 'pending'){
      html = '<p class="ok" style="color:#4F7A3D;font-weight:700">📨 درخواست شما ثبت شد و در انتظار تأیید مدیر است.</p><p class="muted">به‌محض تأیید، لینک دانلود (۷۲ ساعته) همین‌جا ظاهر می‌شود.</p>';
    } else if(req.status === 'approved' && !req.used){
      html = '<p class="ok" style="color:#4F7A3D;font-weight:700">✅ تأیید شد! لینک دانلود آماده است (یک‌بارمصرف — ' + esc(new Date(req.expires*1000).toLocaleString('fa-IR')) + ' منقضی می‌شود)</p>' +
             '<p class="muted" style="margin:6px 0">لینک را فقط از مدیر پلتفرم بگیر؛ لینکِ این بخش برای امنیت فعال نمی‌شود.</p>' +
             '<button class="btn ghost" onclick="alert(\'لینک دانلود را مدیر برای تو می‌فرستد — از تلگرام/تماس\')">ℹ️ لینک کجاست؟</button>';
    } else if(req.status === 'approved' && req.used){
      html = '<p class="muted">⬇ فایل قبلاً دانلود شده (لینک یک‌بارمصرف است). اگر به فایل دوباره نیاز داری، درخواست جدید ثبت کن.</p>' +
             '<button class="btn ghost" onclick="reqExport()">📨 درخواست مجدد</button>';
    } else if(req.status === 'denied'){
      html = '<p style="color:#A93B2A;font-weight:700">درخواست رد شد. برای پیگیری با پشتیبانی در تماس باش.</p>' +
             '<button class="btn ghost" onclick="reqExport()">📨 درخواست جدید</button>';
    }
    el.innerHTML = html;
    const b = document.getElementById('expBtn');
    if(b) b.onclick = reqExport;
  }catch(e){ el.innerHTML = '<p class="muted">خطا در ارتباط</p>'; }
}
async function reqExport(){
  const noteEl = document.getElementById('expNote');
  const note = noteEl ? noteEl.value : '';
  const j = await apiAct('export_request', {note});
  alert(j.ok ? (j.data.msg || 'ثبت شد') : (j.err || 'خطا'));
  loadExport();
}
loadExport();

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
