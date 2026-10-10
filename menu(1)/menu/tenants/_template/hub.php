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
 $brand = $db['menu']['brand']['name'] ?? $slug;
 $user  = $_SESSION['tenant_user'] ?? 'مدیر';
 $role  = $_SESSION['tenant_role'] ?? 'admin';

 $links = [
  ['ic'=>'⚙️','t'=>'پنل مدیریت','d'=>'منو، طراحی، سفارش‌ها، مشتریان و تنظیمات','url'=>'panel-admin.php?role=admin','full'=>$base.'/panel-admin.php?role=admin'],
  /* DESIGN_CARD_V1 */
  ['ic'=>'🎨','t'=>'طراحی منو','d'=>'ساخت و ویرایش منو، دسته‌بندی، آیتم و تصاویر','url'=>'index.html','full'=>$base.'/index.html'],
  ['ic'=>'💳','t'=>'صندوق','d'=>'ثبت سفارش حضوری، رسید و تسویه','url'=>'cashier.html','full'=>$base.'/cashier.html'],
  ['ic'=>'🍳','t'=>'آشپزخانه','d'=>'صف سفارش‌های زنده و «آماده شد»','url'=>'kds.html','full'=>$base.'/kds.html'],
 ];
/* v5: پرسنل (ورود با پین) فقط صندوق و آشپزخانه را می‌بیند — کارت مدیریت حذف می‌شود */
 $isStaff = ($_SESSION['tenant_role'] ?? 'admin') !== 'admin';
 if ($isStaff) $links = array_values(array_filter($links, fn($l) => !in_array($l['url'], ['panel-admin.php?role=admin','index.html'], true)));
 $faNum = fn($n) => str_replace(array_map('strval', range(0,9)), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);
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
  <div class="grid" style="margin-top:16px">
    <?php foreach ($links as $l): ?>
      <a class="card" href="<?= htmlspecialchars($l['url']) ?>">
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
</body>
</html>
