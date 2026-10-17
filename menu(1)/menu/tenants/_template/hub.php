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
require_once __DIR__ . '/api/lib/auth.php';   /* t_license اینجاست — نبودِ آن یعنی Fatal 500 */
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
  /* DESIGN_CARD_V1 */
  ['ic'=>'🎨','t'=>'طراحی منو','d'=>'ساخت و ویرایش منو، دسته‌بندی، آیتم و تصاویر','url'=>'index.html','full'=>$base.'/index.html'],
  ['ic'=>'💳','t'=>'صندوق','d'=>'ثبت سفارش حضوری، رسید و تسویه','url'=>'cashier.html','full'=>$base.'/cashier.html'],
  ['ic'=>'🍳','t'=>'آشپزخانه','d'=>'صف سفارش‌های زنده و «آماده شد»','url'=>'kds.html','full'=>$base.'/kds.html'],
  /* SUPPORT_CARD_V1 */
  ['ic'=>'🎫','t'=>'پشتیبانی','d'=>'ارسال تیکت و پیگیری درخواست‌ها','url'=>'tickets.php','full'=>$base.'/tickets.php'],
  /* ANNOUNCEMENT_CARD_V1 */
  ['ic'=>'📢','t'=>'اعلانات','d'=>'اطلاعیه‌های تیم GitiArts','url'=>'announcements.php','full'=>$base.'/announcements.php'],
  /* RENEW_BTN_V1 */
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
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#EFE7D6;--panel:#FBF6EB;--panel2:#F2EADA;--ink:#271D12;--ink2:#7D6C54;--line:#DFD2B6;--acc:#B4531F}
body{font-family:Vazirmatn,sans-serif;background:var(--bg);color:var(--ink);min-height:100dvh;padding:26px 14px 60px}
.wrap{max-width:680px;margin:0 auto}
.hd{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap}
.hd b{font-size:17px}
.who{color:var(--ink2);font-size:12.5px;margin-bottom:18px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}
.card{background:var(--panel);border:1.5px solid var(--line);border-radius:18px;padding:20px 16px;text-align:center;text-decoration:none;color:var(--ink);display:block;transition:.15s}
.card:hover{border-color:var(--acc);transform:translateY(-2px)}
.card .ic{font-size:34px;display:block;margin-bottom:8px}
.card b{font-size:15px;display:block;margin-bottom:4px}
.card p{font-size:11.5px;color:var(--ink2);line-height:1.9}
.lnk{background:var(--panel);border:1px dashed var(--line2,#CBBB99);border-radius:14px;padding:12px 14px;margin-top:14px}
.lnk .t{font-size:12.5px;font-weight:800;margin-bottom:8px}
.lrow{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:5px 0;border-bottom:1px dashed var(--line);font-size:11.5px}
.lrow:last-child{border-bottom:0}
.lrow span{color:var(--ink2)}
.lrow code{direction:ltr;display:inline-block;background:var(--panel2);border-radius:6px;padding:2px 8px;font-size:10.5px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lrow button{border:0;background:var(--acc);color:#fff;border-radius:8px;padding:4px 10px;font:inherit;font-size:10.5px;font-weight:800;cursor:pointer;flex-shrink:0}
.lic{border-radius:14px;padding:10px 14px;font-size:12px;font-weight:700;margin-top:16px;border:1.5px solid}
.lic.ok{background:rgba(79,122,61,.09);border-color:#4F7A3D;color:#4F7A3D}
.lic.bad{background:rgba(169,59,42,.09);border-color:#A93B2A;color:#A93B2A}
.out{margin-top:18px;text-align:center}
.out a{color:#A93B2A;text-decoration:none;font-size:12.5px;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <div class="hd"><b>🌿 <?= htmlspecialchars((string)$brand) ?></b>
    <span style="font-size:12px;color:var(--ink2)">سلام، <?= htmlspecialchars((string)$user) ?> 👋</span></div>
  <p class="who"><?= $isStaff ? 'حالت پرسنل — دسترسی به صندوق و آشپزخانه. لینک هر بخش قابل کپی و بوکمارک است.' : 'کدام بخش را باز می‌کنید؟ لینک هر بخش را می‌توانید کپی و بوکمارک کنید.' ?></p>

  <?php if ($lic['ok']): ?>
    <div class="lic ok">✅ لایسنس <?= $lic['status']==='active' ? 'فعال' : 'آزمایشی' ?>
      <?= $lic['days_left'] !== null ? '— ' . $faNum($lic['days_left']) . ' روز باقی‌مانده' : '' ?></div>
  <?php else: ?>
    <div class="lic bad">⏳ لایسنس منقضی یا معلق است — برای تمدید با پشتیبانی در تماس باشید.</div>
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
  <div class="setup-card" style="margin-top:16px;background:var(--panel2,#F2EADA);border:1px solid var(--line,#DFD2B6);border-radius:14px;padding:14px 16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <b style="font-size:15px">🚀 راهنمای شروع</b>
      <span style="font-size:12px;color:var(--ink2,#7D6C54)"><?= $__done ?> از <?= $__total ?> انجام شد</span>
    </div>
    <?php foreach ($__cl as $__item): ?>
      <a href="<?= htmlspecialchars($__item['u']) ?>" style="display:flex;align-items:center;gap:8px;padding:8px 0;color:inherit;text-decoration:none;border-top:1px dashed var(--line,#DFD2B6)">
        <span style="font-size:16px;flex:none"><?= $__item['d'] ? '✅' : '⬜' ?></span>
        <span style="flex:1;<?= $__item['d'] ? 'text-decoration:line-through;opacity:.55' : '' ?>"><?= htmlspecialchars($__item['t']) ?></span>
        <?php if (!$__item['d']): ?><span style="color:var(--acc,#B4531F);font-size:13px;flex:none">شروع ›</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
    <?php if ($__showBanner): ?>
  <div style="background:#fff;border:2px solid <?= $__bColor ?>;border-radius:12px;padding:14px 18px;margin:14px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="font-size:14px;color:<?= $__bColor ?>;font-weight:700;line-height:1.6"><?= htmlspecialchars($__bText) ?></div>
    <button type="button" onclick="location.href='renew.php'" style="background:<?= $__bColor ?>;color:#fff;padding:8px 18px;border-radius:10px;border:0;cursor:pointer;font-family:inherit;font-weight:800;font-size:13px;white-space:nowrap">🔄 تمدید اشتراک</button>
  </div>
  <?php endif; ?>

  <div class="grid" style="margin-top:16px">
    <?php foreach ($links as $l): ?>
      <a class="card" href="<?= htmlspecialchars($l['url']) ?>" style="position:relative">
      <?php if ($l['url'] === 'tickets.php' && $__tkUnread > 0): ?><span style="position:absolute;top:10px;left:10px;background:#B4531F;color:#fff;border-radius:50%;min-width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;padding:0 6px;box-shadow:0 2px 6px rgba(180,83,31,.3)"><?= $faNum($__tkUnread) ?></span><?php endif; ?>
        <span class="ic"><?= $l['ic'] ?></span>
        <b><?= htmlspecialchars($l['t']) ?></b>
        <p><?= htmlspecialchars($l['d']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="lnk">
    <div class="t">🔗 لینک‌های اختصاصی (برای کپی و بوکمارک)</div>
    <?php foreach ($links as $l): ?>
      <div class="lrow"><span><?= $l['ic'] ?> <?= htmlspecialchars($l['t']) ?></span>
        <code title="<?= htmlspecialchars($l['full']) ?>"><?= htmlspecialchars(mb_substr($l['full'], 0, 64)) ?><?= mb_strlen($l['full']) > 64 ? '…' : '' ?></code>
        <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($l['full']) ?>">کپی</button></div>
    <?php endforeach; ?>
    <div class="lrow"><span>📱 منوی عمومی مشتریان</span>
      <code title="<?= htmlspecialchars($base) ?>/index.html"><?= htmlspecialchars(mb_substr($base, 0, 56)) ?>/…</code>
      <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($base) ?>/index.html">کپی</button></div>
  </div>

  <div class="out"><a href="logout.php">خروج از حساب</a></div>
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
<div id="anno-popup" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)">
  <div style="background:#FBF6EB;border-radius:18px;max-width:520px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="padding:20px 22px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px dashed #DFD2B6">
      <span style="font-size:22px">📢</span>
      <div style="flex:1">
        <div style="font-size:16px;font-weight:800;color:#271D12"><?= htmlspecialchars($__annoUnread['title']) ?></div>
        <div style="font-size:11px;color:#7D6C54;margin-top:3px">اعلان از تیم GitiArts</div>
      </div>
    </div>
    <?php if (!empty($__annoUnread['image'])): ?>
    <div style="padding:0 22px;margin-top:16px">
      <img src="../../data/announcement-images/<?= htmlspecialchars($__annoUnread['image']) ?>" style="width:100%;border-radius:12px;display:block" alt="">
    </div>
    <?php endif; ?>
    <div style="padding:18px 22px">
      <div style="font-size:14px;line-height:2;color:#271D12;white-space:pre-wrap;word-wrap:break-word"><?= htmlspecialchars($__annoUnread['body']) ?></div>
    </div>
    <div style="padding:14px 22px 20px">
      <button onclick="dismissAnno(<?= (int)$__annoUnread['id'] ?>)" style="width:100%;padding:13px;background:#B4531F;color:#fff;border:0;border-radius:12px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer">متوجه شدم ✓</button>
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
