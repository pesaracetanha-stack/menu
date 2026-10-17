<?php
/* ═══════════════════════════════════════════════════════════════════════════
   دسترسی‌های کافه — صفحه‌ی میانی بعد از ورود
   لینک‌های اختصاصی: مدیریت (panel-admin) · صندوق (cashier) · آشپزخانه (kds)
   ═══════════════════════════════════════════════════════════════════════════ */
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
require_once __DIR__ . '/api/lib/auth.php';   /* t_license اینجاست — نبودن آن یعنی Fatal 500 */
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
  $__bText = '🚨 دوره‌ی آزمایشی تمام شد — برای ادامه، اشتراک تهیه کنید.';
} elseif ($__status === 'active' && $__days <= 3) {
  $__showBanner = true; $__bColor = '#B4801F';
  $__bText = '⚠️ تنها ' . $faNum($__days) . ' روز تا پایان اشتراک — لطفاً تمدید کنید.';
} elseif ($__status === 'trial' && $__days <= 3) {
  $__showBanner = true; $__bColor = '#B4801F';
  $__bText = '⚠️ تنها ' . $faNum($__days) . ' روز از دوره‌ی آزمایشی باقی مانده — برای ادامه، اشتراک تهیه کنید.';
} elseif ($__status === 'active' && $__days <= 7) {
  $__showBanner = true; $__bColor = '#8B7355';
  $__bText = '🔔 ' . $faNum($__days) . ' روز تا پایان اشتراک باقی مانده.';
} elseif ($__status === 'trial' && $__days <= 7) {
  $__showBanner = true; $__bColor = '#8B7355';
  $__bText = '🔔 ' . $faNum($__days) . ' روز از دوره‌ی آزمایشی باقی مانده.';
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
/* ── Modern Dashboard CSS ── */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f4f6f8; --surface:#ffffff; --text-main:#1e293b; --text-muted:#64748b;
  --primary:#4f46e5; --primary-hover:#4338ca; --border:#e2e8f0; --radius:16px;
  --shadow-sm:0 1px 3px rgba(0,0,0,0.05); --shadow-md:0 4px 6px -1px rgba(0,0,0,0.07);
  --shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1);
}
body{font-family:Vazirmatn,sans-serif;background:var(--bg);color:var(--text-main);min-height:100dvh;padding:24px 16px 60px;line-height:1.6}
.wrap{max-width:900px;margin:0 auto}
.hd{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:8px;flex-wrap:wrap}
.hd b{font-size:20px;color:var(--text-main);display:flex;align-items:center;gap:8px}
.hd .user-badge{background:var(--surface);border:1px solid var(--border);padding:6px 14px;border-radius:50px;font-size:13px;color:var(--text-muted);box-shadow:var(--shadow-sm)}
.who{color:var(--text-muted);font-size:13px;margin-bottom:24px}

/* Cards Grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px 20px;text-align:center;text-decoration:none;color:var(--text-main);display:flex;flex-direction:column;align-items:center;transition:all 0.2s ease;box-shadow:var(--shadow-sm);position:relative;overflow:hidden}
.card:hover{border-color:var(--primary);transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.card .icon-wrap{width:56px;height:56px;border-radius:16px;background:rgba(79,70,229,0.08);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:16px;transition:all 0.2s ease}
.card:hover .icon-wrap{background:var(--primary);color:#fff;transform:scale(1.05)}
.card b{font-size:16px;display:block;margin-bottom:6px;font-weight:700}
.card p{font-size:12px;color:var(--text-muted);line-height:1.7}

/* License / Status Banner */
.lic{border-radius:12px;padding:14px 20px;font-size:13.5px;font-weight:600;margin-top:20px;border:1px solid transparent;display:flex;align-items:center;gap:10px;box-shadow:var(--shadow-sm)}
.lic.ok{background:#ecfdf5;border-color:#a7f3d0;color:#047857}
.lic.bad{background:#fef2f2;border-color:#fecaca;color:#b91c1c}

/* Setup Checklist */
.setup-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-top:20px;box-shadow:var(--shadow-sm)}
.setup-card .head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.setup-card .head b{font-size:15px;color:var(--text-main)}
.setup-card .head span{font-size:12px;background:var(--bg);padding:4px 12px;border-radius:20px;color:var(--text-muted)}
.setup-item{display:flex;align-items:center;gap:12px;padding:10px 0;color:var(--text-main);text-decoration:none;border-bottom:1px dashed var(--border);font-size:14px;transition:opacity 0.2s}
.setup-item:last-child{border-bottom:0}
.setup-item:hover{opacity:0.8}
.setup-item .status{font-size:18px;flex:none}
.setup-item .text{flex:1}
.setup-item.done .text{text-decoration:line-through;opacity:0.5}
.setup-item .action{color:var(--primary);font-size:13px;font-weight:700}

/* Expiration Banner */
.banner{background:var(--surface);border:1px solid var(--border);border-right:4px solid var(--bColor);border-radius:12px;padding:16px 20px;margin:20px 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:var(--shadow-sm)}
.banner .msg{font-size:14px;color:var(--text-main);font-weight:600;line-height:1.6;flex:1}
.banner button{background:var(--primary);color:#fff;padding:10px 20px;border-radius:10px;border:0;cursor:pointer;font-family:inherit;font-weight:700;font-size:13px;transition:background 0.2s}
.banner button:hover{background:var(--primary-hover)}

/* Links Section */
.lnk{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-top:24px;box-shadow:var(--shadow-sm)}
.lnk .t{font-size:15px;font-weight:700;margin-bottom:16px;color:var(--text-main);display:flex;align-items:center;gap:8px}
.lrow{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);font-size:13px}
.lrow:last-child{border-bottom:0;padding-bottom:0}
.lrow .label{color:var(--text-main);font-weight:500;display:flex;align-items:center;gap:6px;min-width:120px}
.lrow code{direction:ltr;display:inline-block;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:6px 12px;font-size:12px;color:var(--text-muted);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;text-align:left;font-family:monospace}
.lrow button{border:1px solid var(--border);background:var(--surface);color:var(--text-main);border-radius:8px;padding:6px 16px;font:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s;flex-shrink:0}
.lrow button:hover{background:var(--primary);color:#fff;border-color:var(--primary)}

.out{margin-top:32px;text-align:center}
.out a{color:#ef4444;text-decoration:none;font-size:14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;transition:background 0.2s}
.out a:hover{background:#fef2f2}

/* Popup Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)}
.modal-content{background:var(--surface);border-radius:20px;max-width:520px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);animation:modalIn 0.3s ease}
@keyframes modalIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}
.modal-head{padding:24px 24px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border)}
.modal-head .icon{font-size:24px}
.modal-head .title{font-size:18px;font-weight:800;color:var(--text-main)}
.modal-head .subtitle{font-size:12px;color:var(--text-muted);margin-top:4px}
.modal-body{padding:24px;font-size:14px;line-height:2;color:var(--text-main);white-space:pre-wrap;word-wrap:break-word}
.modal-footer{padding:16px 24px 24px}
.modal-btn{width:100%;padding:14px;background:var(--primary);color:#fff;border:0;border-radius:12px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer;transition:background 0.2s}
.modal-btn:hover{background:var(--primary-hover)}

/* Badge */
.badge{position:absolute;top:12px;left:12px;background:#ef4444;color:#fff;border-radius:50px;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;padding:0 8px;box-shadow:0 2px 4px rgba(239,68,68,0.4)}

@media (max-width:600px){
  .grid{grid-template-columns:1fr}
  .lrow{flex-direction:column;align-items:flex-start;gap:8px}
  .lrow code{width:100%;text-align:center}
  .lrow button{width:100%}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="hd">
    <b>🌱 <?= htmlspecialchars((string)$brand) ?></b>
    <span class="user-badge">سلام، <?= htmlspecialchars((string)$user) ?> 👋</span>
  </div>
  <p class="who"><?= $isStaff ? 'حالت پرسنل — دسترسی به صندوق و آشپزخانه. لینک هر بخش قابل کپی و بوکمارک است.' : 'کدام بخش را باز می‌کنید؟ لینک هر بخش را می‌توانید کپی و بوکمارک کنید.' ?></p>

  <?php if ($lic['ok']): ?>
    <div class="lic ok">
      <span style="font-size:18px">✅</span>
      <span>لایسنس <?= $lic['status']==='active' ? 'فعال' : 'آزمایشی' ?>
      <?= $lic['days_left'] !== null ? '— ' . $faNum($lic['days_left']) . ' روز باقی‌مانده' : '' ?></span>
    </div>
  <?php else: ?>
    <div class="lic bad">
      <span style="font-size:18px">⛔</span>
      <span>لایسنس منقضی یا معلق است — برای تمدید با پشتیبانی در تماس باشید.</span>
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
      <span><?= $__done ?> از <?= $__total ?> انجام شد</span>
    </div>
    <?php foreach ($__cl as $__item): ?>
      <a href="<?= htmlspecialchars($__item['u']) ?>" class="setup-item <?= $__item['d'] ? 'done' : '' ?>">
        <span class="status"><?= $__item['d'] ? '✅' : '⬜' ?></span>
        <span class="text"><?= htmlspecialchars($__item['t']) ?></span>
        <?php if (!$__item['d']): ?><span class="action">شروع ›</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($__showBanner): ?>
  <div class="banner" style="--bColor:<?= $__bColor ?>">
    <div class="msg"><?= htmlspecialchars($__bText) ?></div>
    <button type="button" onclick="location.href='renew.php'">🔄 تمدید اشتراک</button>
  </div>
  <?php endif; ?>

  <div class="grid" style="margin-top:24px">
    <?php foreach ($links as $l): ?>
      <a class="card" href="<?= htmlspecialchars($l['url']) ?>">
        <?php if ($l['url'] === 'tickets.php' && $__tkUnread > 0): ?><span class="badge"><?= $faNum($__tkUnread) ?></span><?php endif; ?>
        <div class="icon-wrap"><?= $l['ic'] ?></div>
        <b><?= htmlspecialchars($l['t']) ?></b>
        <p><?= htmlspecialchars($l['d']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="lnk">
    <div class="t">🔗 لینک‌های اختصاصی (برای کپی و بوکمارک)</div>
    <?php foreach ($links as $l): ?>
      <div class="lrow">
        <span class="label"><?= $l['ic'] ?> <?= htmlspecialchars($l['t']) ?></span>
        <code title="<?= htmlspecialchars($l['full']) ?>"><?= htmlspecialchars(mb_substr($l['full'], 0, 64)) ?><?= mb_strlen($l['full']) > 64 ? '…' : '' ?></code>
        <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($l['full']) ?>">کپی</button>
      </div>
    <?php endforeach; ?>
    <div class="lrow">
      <span class="label">📱 منوی عمومی مشتریان</span>
      <code title="<?= htmlspecialchars($base) ?>/index.html"><?= htmlspecialchars(mb_substr($base, 0, 56)) ?>/…</code>
      <button type="button" onclick="cp(this)" data-u="<?= htmlspecialchars($base) ?>/index.html">کپی</button>
    </div>
  </div>

  <div class="out"><a href="logout.php">🚪 خروج از حساب</a></div>
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
      <span class="icon">📢</span>
      <div>
        <div class="title"><?= htmlspecialchars($__annoUnread['title']) ?></div>
        <div class="subtitle">اعلان از تیم GitiArts</div>
      </div>
    </div>
    <?php if (!empty($__annoUnread['image'])): ?>
    <div style="padding:0 24px;margin-top:20px">
      <img src="../../data/announcement-images/<?= htmlspecialchars($__annoUnread['image']) ?>" style="width:100%;border-radius:12px;display:block;box-shadow:var(--shadow-sm)" alt="">
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