<?php
declare(strict_types=1);
/* BUILD: login-v4 — v5.0.2: فرم استاندارد «نام کاربری (شمارهٔ موبایل) + رمز ورود»
   همهٔ نقش‌ها (مدیر/صندوق/آشپزخانه) به یک شکل وارد می‌شوند:
   • پرسنل: موبایل ثبت‌شده در پنل + رمز (کاربران قدیمیِ فقط-پین: پین در هر دو فیلد یا رمز خالی)
   • مدیر: موبایل ثبت‌شده هنگام ثبت‌نام + رمز پنل
   • مسیر قدیمی «نام/آدرس کافه + رمز» برای سازگاری حفظ شده است */
require_once __DIR__ . '/api/lib/paths.php';
require_once __DIR__ . '/api/lib/class-gstore.php';

function login_store(string $slug): GStore {
  $cfg = p_config();
  return GStore::for($cfg['paths']['tenants'] . '/' . $slug);
}

/* ساخت نشست و خروجی ریدایرکت به hub کافه */
function login_finish(string $slug, string $user, string $role): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('TEN_SESS');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
  }
  session_regenerate_id(true);
  $_SESSION['tenant'] = $slug;
  $_SESSION['tenant_user'] = $user;
  $_SESSION['tenant_role'] = $role;

/* v5: ریدایرکت از خودِ درخواست محاسبه می‌شود — روی هر دامنه/پوشه درست کار می‌کند */
  $sch  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $dirb = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  p_json_out(true, null, ['url' => $sch . '://' . $host . $dirb . "/tenants/$slug/hub.php", 'role' => $role]);
}

/* نرمال‌سازی ارقام فارسی/عربی و ۰۹۸+ به شکل 09… */
function login_digits(string $s): string {
  $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
                   ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $s);
  $d = preg_replace('/\D/', '', $s);
  if (strlen($d) === 13 && str_starts_with($d, '98')) $d = '0' . substr($d, 2);
  if (strlen($d) === 12 && str_starts_with($d, '98')) $d = '0' . substr($d, 2);
  return $d;
}

$in  = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (!$in) $in = $_POST;   /* سازگاری با POST فرم‌-encoded (و تست‌پذیری آسان‌تر) */
$act = $in['act'] ?? ($_POST['act'] ?? '');

if ($act === 'login') {
  $raw      = trim((string)($in['num'] ?? ($in['identity'] ?? ($in['user'] ?? ''))));
  $pass     = (string)($in['pass'] ?? '');
  $slugHint = strtolower(trim((string)($in['slug'] ?? '')));
  /* v5.0.5: انتخاب از فهرست کافه‌ها (پاسخ choose مرحلهٔ قبل) — فقط همان کافه بررسی می‌شود */
  $pick     = !empty($in['pick']) && $slugHint !== '';

  if ($raw === '') p_json_out(false, 'شمارهٔ موبایل (نام کاربری) را وارد کنید');

  $tenantsList = p_tenants_load()['tenants'];

  if ($pick) {
    $tenantsList = array_values(array_filter($tenantsList, fn($t) => strtolower((string)($t['slug'] ?? '')) === $slugHint));
    if (!$tenantsList) p_json_out(false, 'کافهٔ انتخابی یافت نشد — دوباره وارد شوید');
  } elseif ($slugHint !== '') {
    /* کافهٔ مشخص‌شده (درخواست از داخل پنل همان کافه) اول بررسی می‌شود */
    usort($tenantsList, fn($a, $b) => ($b['slug'] ?? '') === $slugHint ? 1 : (($a['slug'] ?? '') === $slugHint ? -1 : 0));
  }

  $isNumeric = (bool)preg_match('/^\+?[\d۰-۹٠-٩\s\-]+$/', $raw);
  $found     = [];   /* v5.0.5: همهٔ حساب‌های منطبق — اگر بیش از یکی بود کاربر کافه را انتخاب می‌کند */
  $wrongPass = false;   /* حساب پیدا شد ولی رمز درست نبود */

  /* ═══ مسیر عددی: موبایل/پین پرسنل یا موبایل مدیر — همه با «نام کاربری + رمز» ═══ */
  if ($isNumeric) {
    $digits = login_digits($raw);
    if ($digits === '') p_json_out(false, 'شماره معتبر نیست');

    /* ۱) پرسنل (صندوق/آشپزخانه) — موبایل ثبت‌شده یا پین قدیمی + رمز */
    foreach ($tenantsList as $t) {
      try { $tDb = login_store((string)$t['slug'])->get(); } catch (Throwable $e) { continue; }
      foreach ($tDb['users'] ?? [] as $u) {
        $name   = (string)($u['name'] ?: 'پرسنل');
        $uPhone = login_digits((string)($u['phone'] ?? ''));
        $byPhone = ($uPhone !== '' && $uPhone === $digits);
        $byPin   = (!empty($u['pin']) && (string)$u['pin'] === $digits);
        if (!$byPhone && !$byPin) continue;

        if (!empty($u['ph'])) {
          /* کاربر رمز دارد (v5.0.2) — ورود فقط با رمز درست */
          if ($pass !== '' && password_verify($pass, $u['ph']))
            $found[] = ['slug' => (string)$t['slug'], 'cafe' => (string)($t['name'] ?? $t['slug']), 'role' => 'staff', 'user' => $name];
          $wrongPass = true;
          continue;
        }
        /* سازگاری کاربر قدیمی فقط-پین: پین در «نام کاربری» و همان پین (یا رمز خالی) */
        if ($pass === '' || $pass === (string)$u['pin'])
          $found[] = ['slug' => (string)$t['slug'], 'cafe' => (string)($t['name'] ?? $t['slug']), 'role' => 'staff', 'user' => $name];
        $wrongPass = true;
      }
    }

    /* ۲) مدیر کافه — موبایل ثبت‌شده هنگام ثبت‌نام + رمز پنل */
    foreach ($tenantsList as $t) {
      $tPhone = login_digits((string)($t['phone'] ?? ''));
      try { $tDb = login_store((string)$t['slug'])->get(); } catch (Throwable $e) { continue; }
      $aPhone = login_digits((string)($tDb['auth']['phone'] ?? ''));
      if ($digits !== '' && ($tPhone === $digits || ($aPhone !== '' && $aPhone === $digits))) {
        if (empty($tDb['auth']['ph']) || $pass === '' || !password_verify($pass, $tDb['auth']['ph'])) {
          $wrongPass = true;
          continue;
        }
        $found[] = ['slug' => (string)$t['slug'], 'cafe' => (string)($t['name'] ?? $t['slug']),
                    'role' => 'admin', 'user' => (string)($tDb['auth']['user'] ?? 'مدیر')];
      }
    }

    /* v5.0.5: یک حساب = ورود مستقیم مثل قبل؛ چند حساب = فهرست انتخاب کافه (بدون ساخت نشست) */
    if (count($found) === 1)
      login_finish($found[0]['slug'], $found[0]['user'], $found[0]['role']);
    if (count($found) > 1)
      p_json_out(true, null, ['choose' => $found]);

    if ($wrongPass) p_json_out(false, 'شمارهٔ موبایل یا رمز عبور نادرست است' . ((string)$pass === '' ? ' — رمز ورود را بنویسید' : ''));
    p_json_out(false, 'حسابی با این شماره یافت نشد — شمارهٔ موبایلی که با آن ثبت‌نام شده یا موبایل/پین پرسنلی را وارد کنید');
  }

  /* ═══ مسیر قدیمی (برای سازگاری): نام/آدرس کافه + رمز مدیر ═══ */
  $targetTenant = null;
  foreach ($tenantsList as $t) {
    if (strcasecmp((string)$t['slug'], $raw) === 0 || strcasecmp((string)$t['name'], $raw) === 0) {
      $targetTenant = $t;
      break;
    }
  }
  if (!$targetTenant) p_json_out(false, 'کافه یا حسابی با این مشخصات یافت نشد');
  $slug = (string)$targetTenant['slug'];
  try {
    $db = login_store($slug)->get();
  } catch (Throwable $e) {
    p_json_out(false, 'اطلاعات کافه در دسترس نیست');
  }
  if (empty($db['auth']['ph']) || !password_verify($pass, $db['auth']['ph'])) {
    p_json_out(false, 'رمز عبور مدیریت نادرست است');
  }
  login_finish($slug, (string)($db['auth']['user'] ?? 'مدیر'), 'admin');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به سامانه — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="style-landing.css">
<style>
/* v5.0.5: انتخاب کافه برای شماره‌های مشترک بین چند کافه */
#c-list{display:flex;flex-direction:column;gap:8px;margin:10px 0 12px}
.cbtn{display:flex;align-items:center;gap:10px;width:100%;text-align:right;padding:12px 14px;border:1.5px solid #d9e2d9;border-radius:12px;background:#fff;cursor:pointer;font-family:inherit;font-size:14px;transition:.15s}
.cbtn:hover{border-color:var(--acc);background:#f6fbf6}
.cbtn b{flex:1}
.cbtn small{color:#6b7a6b;font-weight:700;flex:none}
.cbtn>span{color:var(--acc);font-weight:900;flex:none}
.cbtn.back{justify-content:center;background:#f4f4f4;color:#555;border-color:#e2e2e2;font-weight:700}
.cbtn.back:hover{background:#ececec}
</style>
</head>
<body>
<div class="reg" style="max-width:420px;margin:50px auto">
  <div class="reg-card">
    <div style="font-weight:900;font-size:18px;color:var(--acc);margin-bottom:14px">🌿 GitiArts — ورود به کافه</div>
    <form id="lf" novalidate>
      <label id="row-num">نام کاربری <span style="font-weight:600;color:var(--ink3)">(شمارهٔ موبایل)</span>
        <input type="text" id="f-num" dir="ltr" inputmode="numeric" placeholder="09121234567" required autocomplete="username"></label>
      <label>رمز ورود
        <input type="password" id="f-pass" autocomplete="current-password"></label>
      <p class="hint" id="num-hint">همهٔ نقش‌ها — مدیر، صندوق و آشپزخانه — با شمارهٔ موبایل و رمز خود وارد می‌شوند. اگر شمارهٔ شما در چند کافه ثبت باشد، فهرست کافه‌ها نمایش داده می‌شود و انتخاب می‌کنید.</p>
      <p class="msg" id="l-msg"></p>
      <button id="l-btn" type="submit">ورود</button>
    </form>
    <div id="l-choose" style="display:none">
      <p class="hint" style="font-weight:800;color:var(--ink);margin-bottom:2px">این شماره در چند کافه ثبت است — به کدام وارد می‌شوید؟</p>
      <div id="c-list"></div>
      <button type="button" class="cbtn back" id="c-back">‹ بازگشت و اصلاح شماره/رمز</button>
    </div>
    <p class="hint" style="margin-top:12px;margin-bottom:0">رمز را فراموش کرده‌اید؟ از منوی کافه (<span dir="ltr">…/index.html</span>) ← «مدیریت» ← «رمز را فراموش کرده‌ام» با شمارهٔ موبایل ثبت‌شده، رمز تازه بسازید. پرسنل: موبایل و رمز را مدیر از بخش «کاربران صندوق و آشپزخانه» برایتان ثبت می‌کند.</p>
    <p class="foot">کافه خود را نساخته‌اید؟ <a href="index.html#register" style="color:var(--acc);font-weight:800">ثبت‌نام رایگان</a></p>
  </div>
</div>
<script>
'use strict';
const $ = s => document.querySelector(s);
$('#f-num').addEventListener('input', () => {
  const v = $('#f-num').value;
  const en = v.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
  $('#f-num').value = en.replace(/[^\d+]/g, '').slice(0, 13);
});

/* v5.0.5: ورود دومرحله‌ای وقتی شماره در چند کافه ثبت است */
let pickNum = '', pickPass = '';
function chooseList(list){
  pickNum = $('#f-num').value.trim(); pickPass = $('#f-pass').value;
  $('#lf').style.display = 'none';
  $('#l-choose').style.display = 'block';
  $('#c-list').innerHTML = list.map((c, i) =>
    `<button type="button" class="cbtn" data-i="${i}"><b>${String(c.cafe || c.slug)}</b>
     <small>${c.role === 'admin' ? 'مدیر کافه' : 'پرسنل'}</small><span>ورود ›</span></button>`).join('');
  document.querySelectorAll('#c-list .cbtn').forEach(b => {
    b.addEventListener('click', () => doLogin({ pick: 1, slug: list[+b.dataset.i].slug }));
  });
}
$('#c-back').addEventListener('click', () => {
  $('#l-choose').style.display = 'none';
  $('#lf').style.display = 'block';
  const m = $('#l-msg'); m.className = 'msg'; m.textContent = '';
});

async function doLogin(extra = {}) {
  const b = $('#l-btn'), m = $('#l-msg');
  const num  = extra.pick ? pickNum  : $('#f-num').value.trim();
  const pass = extra.pick ? pickPass : $('#f-pass').value;
  if (!num) {
    m.className = 'msg bad'; m.textContent = '⚠️ شمارهٔ موبایل (نام کاربری) را وارد کنید.';
    return;
  }
  b.disabled = true; b.textContent = 'در حال ورود…';
  m.className = 'msg'; m.textContent = '';
  try {
    let r;
    try {
      r = await fetch('login.php', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ act: 'login', num, pass, ...extra })
      });
    } catch(_) {   /* v5.0.4-test.4: خطای شبکه به فارسیِ قابل فهم */
      m.className = 'msg bad'; m.textContent = '⚠️ ارتباط با سرور برقرار نشد — اینترنت خود را بررسی کنید و دوباره تلاش کنید.';
      b.disabled = false; b.textContent = 'ورود';
      return;
    }
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch(_) { throw new Error('پاسخ سرور نامعتبر بود — چند لحظه بعد دوباره امتحان کنید'); }
    if (!j.ok) throw new Error(j.err || 'ورود ناموفق بود');
    if (j.data && j.data.choose) {   /* چند کافه — انتخاب */
      b.disabled = false; b.textContent = 'ورود';
      chooseList(j.data.choose);
      return;
    }
    m.className = 'msg ok'; m.textContent = '✅ خوش آمدید! در حال انتقال…';
    setTimeout(() => { location.href = j.data.url; }, 700);
  } catch(err) {
    m.className = 'msg bad'; m.textContent = '⚠️ ' + err.message;
    b.disabled = false; b.textContent = 'ورود';
  }
}
$('#lf').addEventListener('submit', e => { e.preventDefault(); doLogin(); });
</script>
</body>
</html>
