'use strict';
/* ════════════════════════════════════════════════════════════
   app.js — راه‌اندازی و اجرای نهایی برنامه
   ════════════════════════════════════════════════════════════ */
const T_SLUG = (document.body.dataset.slug || location.pathname.match(/tenants\/([^\/]+)/)?.[1] || '');

/* v5.0.5-test.3: کلید نمای فعلی — هنگام رندر دوبارهٔ «همان نما» جای اسکرول حفظ می‌شود
   (آپلود تصویر در طراح منو، ذخیرهٔ خودکار، آکاردئون، پالس صندوق و…).
   تغییر نما (تب/گام ویزارد/فلو مشتری) مثل قبل به بالای صفحه برمی‌گردد. */
function renderViewKey(){
 if(App.role === 'wizard')   return 'wiz|' + App.wiz.step;
 if(App.role === 'login')    return 'login';
 if(App.role === 'admin')    return 'adm|' + App.adm.tab;
 if(App.role === 'kds')      return 'kds';
 if(App.role === 'cashier')  return 'csh|' + (App.csh ? App.csh.tab : '');
 if(App.role === 'customer') return 'cst|' + (App.cst.done ? 'done' : App.cst.tab + '|' + App.cst.flow);
 return 'home';
}
/* v5.0.5-test.3: در پنل مدیریت اسکرول اصلی داخل کانتینرهای داخلی است
   (پیش‌نمایش منو در طراح، سایدبار ابزارها، لیست سفارش‌های صندوق) —
   پس علاوه بر پنجره، جای اسکرول همهٔ کانتینرهای قابل‌اسکرول هم ثبت/برگردانده می‌شود. */
function snapScrolls(){
 const out = [];
 if (window.scrollY) out.push(['w', window.scrollY]);
 const els = document.querySelectorAll('*');
 for (let i = 0; i < els.length; i++) {
   const el = els[i];
   if (el.scrollTop > 0 && el.scrollHeight > el.clientHeight + 4) out.push([i, el.scrollTop]);
 }
 return out;
}
function restoreScrolls(snap){
 if(!snap || !snap.length) return;
 const els = document.querySelectorAll('*');
 for (const [k, v] of snap) {
   if (k === 'w') { window.scrollTo(0, v); continue; }
   const el = els[k];
   if (el && el.scrollHeight > el.clientHeight + 4) el.scrollTop = v;
 }
}
function render(){
 const r = $('#app');
 if(!r) return;
 const sy = window.scrollY;                 /* v5.0.5-test.3: جای اسکرول قبل از بازسازی گرفته می‌شود */
 const snap = snapScrolls();                /* اسکرول کانتینرهای داخلی */
 try{
  /* v5.0.4-test.5: متن تایپ‌شدهٔ «کد تخفیف» بین رندرهای زنده گم نشود */
  const pi = $('#promo-inp');
  if (pi) App.cst.promoDraft = pi.value;
  const isAdmin = sessionStorage.getItem('tenAdmin') === '1';

  // اگر کاربر ادمین نیست، همواره نمای مشتری را نمایش بده
  if(!isAdmin && App.role !== 'wizard'){
    App.role = 'customer';
    r.innerHTML = customerHTML();
  }
  // اگر ستاپ انجام نشده و ادمین است، ویزارد را نشان بده
  else if(isAdmin && Store.db && Store.db.setup && Store.db.setup.done === false) {
    App.role = 'wizard';
    r.innerHTML = wizardHTML();
  }
  else if(App.role === 'wizard')   r.innerHTML = wizardHTML();
  else if(App.role === 'login')    r.innerHTML = loginHTML();
  else if(!App.role)               r.innerHTML = homeHTML();
  else if(App.role === 'admin')    r.innerHTML = adminHTML();
  else if(App.role === 'customer') r.innerHTML = customerHTML();
  else if(App.role === 'kds')      r.innerHTML = kdsHTML();
  else {
    App.csh.lastPending = (Store.db.orders || []).filter(o => o.status === 'pending').length;
    r.innerHTML = cashierHTML();
  }
 }catch(err){
  console.error('render error:', err);
  r.innerHTML = `<div class="home"><div class="empty-big" style="padding-top:90px">${ic('info',36)}
   <p>نمایش این بخش با خطا مواجه شد</p><small>${esc(err.message)}</small>
   <p class="dim" style="margin-top:8px">معمولاً نوسازی صفحه مشکل را حل می‌کند.</p>
   <button class="btn sm" onclick="location.reload()">نوسازی صفحه (F5)</button></div></div>`;
 }
 /* v5.0.5-test.3: همان نما → بازگشت به جای قبلی اسکرول (فیکس پرش صفحه هنگام آپلود تصویر) */
 const vk = renderViewKey();
 if(render._vk === vk){
   window.scrollTo(0, sy);
   restoreScrolls(snap);
 }
 render._vk = vk;
 afterRender();
}

function afterRender(){
 try{
  const secs = $$('.mn-cat'), btns = $$('.mnt:not(.add)');
  if(secs.length && btns.length){
   const io = new IntersectionObserver(es => es.forEach(x => {
    if(x.isIntersecting) btns.forEach(b => b.classList.toggle('on', b.dataset.cat === x.target.dataset.cat));
   }), {rootMargin:'-12% 0px -72% 0px'});
   secs.forEach(s => io.observe(s));
  }
  if(typeof syncDots === 'function') syncDots();
  const p = $('#pts-up');
  if(p){
   const to = +p.dataset.v, st = performance.now();
   const f = t => {
    const k = Math.min(1, (t - st) / 900);
    p.textContent = fa(Math.round(to * k));
    if(k < 1) requestAnimationFrame(f);
   };
   requestAnimationFrame(f);
  }
 }catch(e){}
}

const ui = {
 toast(o){
  try{
   const t = document.createElement('div');
   t.className = 'toast';
   t.innerHTML = `<span>${o.msg}</span>` + (o.action ? `<button class="t-act">${o.action.label}</button>` : '');
   if(o.action) t.querySelector('.t-act').onclick = () => { o.action.fn(); t.remove(); };
   $('#toasts').appendChild(t);
   requestAnimationFrame(() => t.classList.add('in'));
   setTimeout(() => { t.classList.remove('in'); setTimeout(() => t.remove(), 300); }, o.ttl || 2600);
  }catch(e){}
 },
 modal(html, o = {}){
  const r = $('#modal-root');
  r.innerHTML = `<div class="ovl" data-act="close-modal"></div>
   <div class="sheet"><div class="sh-head"><h3>${o.title || ''}</h3><button class="icobtn" data-act="close-modal">${ic('x', 17)}</button></div>
   <div class="sh-body">${html}</div></div>`;
  r.classList.add('open');
  document.body.classList.add('locked');
 },
 closeModal(){
  const r = $('#modal-root');
  r.classList.remove('open');
  r.innerHTML = '';
  document.body.classList.remove('locked');
 }
};

/* ═══ بالا آمدن اولیه ═══ */
(async function boot(){
 try{
  await Store.init();
 }catch(e){
  $('#app').innerHTML = `<div style="padding:46px 20px;text-align:center;font-family:Vazirmatn,Tahoma,sans-serif">
   <h2 style="font-size:19px;margin-bottom:8px">اتصال به سرور برقرار نشد</h2>
   <p style="color:#7D6C54;font-size:13.5px;line-height:2;margin-bottom:14px">اینترنت خود را بررسی کنید و دوباره تلاش کنید.<br>اگر تکرار شد، سرور موقتاً در دسترس نیست — چند دقیقه بعد امتحان کنید.</p>
   <button onclick="location.reload()" style="border:0;background:#B4531F;color:#fff;border-radius:12px;padding:10px 22px;font:inherit;font-weight:800;cursor:pointer">تلاش دوباره</button>
   <p dir="ltr" style="color:#A93B2A;font-size:10.5px;margin-top:14px;opacity:.75">${esc(e.message)}</p></div>`;
  return;
 }

 const isAdmin = sessionStorage.getItem('tenAdmin') === '1';

 const qs = new URLSearchParams(location.search);
 const qt = parseInt(qs.get('table'), 10);
 const rp = qs.get('role');
 /* v5.0.1: نقش از نام صفحه — cashier.html / kds.html همان اپ کامل با نقش آماده‌اند */
 const page = (location.pathname.match(/(cashier|kds)\.html$/) || [])[1] || null;

 if(qt && (Store.db.tables || []).some(t => t.no === qt)){
   App.role = 'customer';
   App.cst.tableNo = qt;
 } else if(!isAdmin) {
   App.role = 'customer';
 } else if(Store.db && Store.db.setup && !Store.db.setup.done) {
   App.role = 'wizard';
 } else if(page === 'cashier' || rp === 'cashier') {
   App.role = 'cashier';
 } else if((page === 'kds' || rp === 'kds') && SET().kds) {
   App.role = 'kds';
 } else {
   App.role = 'admin';
 }

 render();

 /* v5.0.5-test.3: لوگوی کافه → favicon خودکار در هر بار باز شدن صفحه */
 if(typeof applyFavicon === 'function') applyFavicon();

 // اتصال آپلود فایل با پیش‌نمایش در لحظه
 const ip = document.getElementById('imgpick');
 if(ip){
   ip.addEventListener('change', async e => {
     const file = e.target.files && e.target.files[0];
     if(!file) return;

     /* v5.0.5-test.5: قفل جای اسکرول — از لحظهٔ انتخاب فایل تا پایان ذخیره،
        صفحه در همان جای قبلی می‌ماند (حتی اگر مرورگر موبایل هنگام بستن
        پنجرهٔ انتخاب فایل اسکرول را صفر کند) */
     const sy0 = window.scrollY;
     const hold = () => { if (window.scrollY !== sy0) window.scrollTo(0, sy0); };

     // پیش‌نمایش فوری در مرورگر
     const reader = new FileReader();
     reader.onload = ev => {
       const previewUrl = ev.target.result;
       if(imgTarget === 'logo' || imgTarget === 'logo-wiz'){
         M().brand.logo = previewUrl;
       }
       render();
       hold();
     };
     reader.readAsDataURL(file);

     const isLogo = (imgTarget === 'logo' || imgTarget === 'logo-wiz');
     try {
       ui.toast({ msg: isLogo ? 'در حال ذخیره لوگو…' : 'در حال ذخیره تصویر…' });
       const res = await Api.upload(file);
       const url = res.url;
       if(imgTarget === 'logo' || imgTarget === 'logo-wiz'){
         M().brand.logo = url;
         Store.commit('menu');
       } else if(imgTarget === 'headimg'){
         M().theme.headImg = url;
         M().theme.headMode = 'image';
         Store.commit('menu');
       } else if(imgTarget === 'menubg'){
         M().theme.bgImg = url;
         M().theme.bgMode = 'image';
         Store.commit('menu');
       } else if(imgTarget && imgTarget.startsWith('bnr:')){
         const bId = imgTarget.split(':')[1];
         const b = (M().banners||[]).find(x => x.id === bId);
         if(b) b.img = url;
         Store.commit('menu');
       } else if(imgTarget){
         const it = findItem(imgTarget);
         if(it) it.img = url;
         Store.commit('menu');
       }
       /* v5.0.5-test.3: لوگوی تازه بلافاصله favicon هم می‌شود */
       if(typeof applyFavicon === 'function' && isLogo) applyFavicon();
       render();
       hold();
       ui.toast({ msg: isLogo ? 'لوگو با موفقیت تنظیم شد ✓' : 'تصویر با موفقیت ذخیره شد ✓' });
     } catch(err) {
       ui.toast({ msg: 'خطا در بارگذاری: ' + err.message });
     } finally {
       ip.value = '';
       hold();   /* آخرین تضمین: حتی بعد از ذخیرهٔ خودکار منو، جای اسکرول ثابت است */
     }
   });
 }

 /* v5.0.4: اتصال فایل اکسل منو — پاسخ پیش‌نمایش است و مودال تأیید باز می‌شود */
 const xp = document.getElementById('excelpick');
 if(xp){
   xp.addEventListener('change', async e => {
     const file = e.target.files && e.target.files[0];
     xp.value = '';
     if(!file) return;
     if(!/\.xlsx$/i.test(file.name)){
       ui.toast({ msg: 'فقط فایل اکسل با فرمت xlsx پذیرفته می‌شود', ttl: 6000 });
       return;
     }
     ui.toast({ msg: 'در حال خواندن فایل اکسل…' });
     try {
       const res = await Api.uploadExcel(file);
       App.adm.xls = res;
       excelImportModal();
     } catch(err) {
       ui.toast({ msg: 'ایمپورت ناموفق: ' + err.message, ttl: 10000 });
     }
   });
 }
})();

window.addEventListener('error', e => {
 const d = document.getElementById('errbox');
 if(d){
  d.style.display = 'block';
  d.innerHTML = '<b>یک مشکل جزئی در صفحه پیش آمد.</b> اگر بخشی درست کار نمی‌کند، صفحه را نوسازی کنید (F5 یا Ctrl+F5)؛ اگر تکرار شد با پشتیبانی در تماس باشید.' +
   '<span class="err-tech">جزئیات فنی برای پشتیبانی: ' + esc((e.message || '?') + ' @ line ' + (e.lineno || '?')) + '</span>';
 }
});

/* ═══ Polling تب‌ها ═══ */
setInterval(async () => {
 if(!window.Store || !Store.db || !window.App || App.role === 'admin' || App.role === 'wizard') return;
 try{
  const isAdmin = sessionStorage.getItem('tenAdmin') === '1';
  const s = isAdmin ? await Api.get('state') : await Api.get('menu');
  if(!s) return;
  if(isAdmin){
    const before = new Set((Store.db.orders || []).map(o => o.id));
    const sig = JSON.stringify((s.orders || []).map(o => [o.id, o.status, o.ready]));
    const changed = sig !== JSON.stringify((Store.db.orders || []).map(o => [o.id, o.status, o.ready]));
    /* v5.0.5-test.1: نتیجهٔ پرداخت آنلاین و سفارش تازه → زنگ + صدای زنگ در صندوق */
    const fresh = (App.role === 'cashier') ? (s.orders || []).filter(o => !before.has(o.id)) : [];
    Store.db = s;
    if(fresh.length){
      beep();
      fresh.forEach(o => ui.toast({ msg: '🔔 ' + (o.method === 'online' ? 'پرداخت آنلاین سفارش #' + fa(o.num) + ' انجام شد — ' + faM(o.total) + ' تومان' : 'سفارش جدید #' + fa(o.num)), ttl: 7000 }));
    }
    if(changed) Bus.emit('store');
    else if(App.role === 'cashier' || App.role === 'kds') render();
  } else {
    Store.db.menu = s.menu;
    Store.db.settings = s.settings;
    /* v5.0.5-test.1: پیام‌ها و رزروهای مشتری — تیک هر ۲ پالس (۱۲ ثانیه) */
    if(Store.cust && (App._nbTick = (App._nbTick || 0) + 1) % 2 === 0) custNotifSync();
  }
 }catch(_){}
}, 6000);

/* ═══ v5.0.5-test.1: کانال عمومی پیام مشتری (پیام‌های کافه + رزروهای من) ═══ */
async function custNotifSync(phone){
 try{
  const ph = en(String(phone || (Store.cust && Store.cust.phone) || App.cst.rs.phone || '')).replace(/\D/g, '');
  if(!/^09\d{9}$/.test(ph)) return;
  const r = await Api.get('notifs_pub', { phone: ph });
  if(!r) return;
  const prev = App._nbLast || '';
  App.cst.nb = r.notifs || [];
  App.cst.nb_rs = r.reservations || [];
  const top = (App.cst.nb[0] || {}).id || '';
  if(top && prev && top !== prev && App.role === 'customer') {
    const n0 = App.cst.nb[0];
    ui.toast({ msg: '🔔 ' + n0.title, ttl: 6000 });
  }
  App._nbLast = top;
  if(App.role === 'customer') render();
 }catch(_){}
}
