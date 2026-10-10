'use strict';
/* ════════════════════════════════════════════════════════════
   ui-screens.js — خانه، ورود، ویزارد، پنل ادمین، طراح، میزها، تنظیمات، گزارش‌ها
   ════════════════════════════════════════════════════════════ */

/* ── خانه ── */
function homeHTML(){
 const roles=[['admin','مدیریت','ورود با نام کاربری و رمز — طراحی منو، میزها، تنظیمات، گزارش‌ها','sliders'],
  ['customer','مشتری','منو، جستجو، سفارش آنلاین، باشگاه مشتریان','user'],
  ['cashier','صندوق','شیفت، میزها و رزرو، چاپ رسید، فاکتور، تقسیم حساب','printer']];
 if(SET().kds)roles.push(['kds','آشپزخانه','صف زندهٔ آماده‌سازی با تایمر','chefhat']);
 const roleRow=(r,no)=>`<button class="role-row" data-act="role" data-role="${r[0]}">
  <span class="rr-no">${no}</span><span class="rr-ic">${ic(r[3],20)}</span>
  <span class="rr-tx"><b>${r[1]}</b><small>${r[2]}</small></span><span class="rr-ar">${ic('arrow',18)}</span></button>`;
 return `<div class="home">
  <header class="home-top"><div class="brandmark">${ic('coffee',21)}<span>سِرو</span><span class="ver">نسخهٔ دمو</span></div>
   <button class="btn ghost sm" data-act="reset-data">${ic('refresh',13)} بازنشانی</button></header>
  <div class="hero"><div>
   <p class="kicker">سامانهٔ منو و فروش — از کافهٔ کوچک تا فودکورت</p>
   <h1>از طراحی منو تا صدای دستگاه کارتخوان.</h1>
   <p class="lead">راه‌انداز گام‌به‌گام، حساب مدیر، نوع کسب‌وکار، لوگو، پالت و میزها را تنظیم می‌کند. اگر آشپزخانهٔ مجزا ندارید، صندوق همه‌چیز را یک‌جا مدیریت می‌کند.</p></div>
   <svg class="cupart" viewBox="0 0 200 210" aria-hidden="true">
    <path class="steam" d="M78 62c0-12 10-14 10-26"/><path class="steam st2" d="M100 58c0-14 11-16 11-30"/><path class="steam st3" d="M122 62c0-12 10-14 10-26"/>
    <path class="cup-line" d="M60 88h80l-8 74a16 16 0 0 1-16 14H84a16 16 0 0 1-16-14Z"/>
    <path class="cup-line" d="M140 98h9a17 17 0 0 1 0 34h-13"/><path class="cup-line" d="M52 196h96"/></svg></div>
  <nav class="roles">${roles.map((r,i)=>roleRow(r,fa(String(i+1).padStart(2,'0')))).join('')}</nav>
  <footer class="home-foot">داده‌ها در سرور ذخیره می‌شوند · طراحی و اجرا توسط HPR-GitiArts</footer></div>`;
}

/* ── ورود ── */
function loginHTML(){
 const s=App.login;
 const field=(lbl,inp)=>`<label class="f"><span>${lbl}</span>${inp}</label>`;
 if(s.mode==='forgot')return `<div class="loginwrap"><div class="login-card" style="text-align:center">
  ${ic('phone',30)}<h2>بازیابی رمز عبور</h2><p>شمارهٔ موبایلی که هنگام ساخت حساب ثبت کردید را وارد کنید.</p>
  ${field('موبایل',`<input data-input="fg-phone" inputmode="numeric" value="${esc(s.phone)}" placeholder="۰۹۱۲ · · · · · · ·">`)}
  ${s.err?`<p class="err" style="text-align:start">${s.err}</p>`:''}
  <button class="btn blk" data-act="forgot-check">${ic('check',15)} بررسی شماره</button>
  <button class="linkbtn" style="margin-top:12px" data-act="forgot-back">بازگشت به ورود</button></div></div>`;
 if(s.mode==='reset')return `<div class="loginwrap"><div class="login-card" style="text-align:center">
  ${ic('lock',30)}<h2>رمز جدید</h2><p>رمز تازه را برای حساب مدیر تنظیم کنید.</p>
  ${field('رمز عبور جدید',`<input data-input="rs-pass" type="password" value="${esc(s.pass)}">`)}
  ${field('تکرار رمز جدید',`<input data-input="rs-pass2" type="password" value="${esc(s.pass2)}">`)}
  ${s.err?`<p class="err" style="text-align:start">${s.err}</p>`:''}
  <button class="btn blk" data-act="do-reset">${ic('check',15)} ثبت رمز جدید</button>
  <button class="linkbtn" style="margin-top:12px" data-act="forgot-back">بازگشت به ورود</button></div></div>`;
 return `<div class="loginwrap"><div class="login-card" style="text-align:center">
  ${ic('lock',30)}<h2>ورود به پنل</h2><p>همهٔ نقش‌ها — مدیر، صندوق و آشپزخانه — با شمارهٔ موبایل و رمز خود وارد می‌شوند.</p>
  <div style="text-align:start">
   ${field('نام کاربری (شمارهٔ موبایل)',`<input data-input="li-user" inputmode="numeric" dir="ltr" value="${esc(s.user)}" placeholder="۰۹۱۲ · · · · · · ·" autocomplete="username">`)}
   ${field('رمز ورود',`<input data-input="li-pass" type="password" value="${esc(s.pass)}" autocomplete="current-password">`)}</div>
  ${s.err?`<p class="err" style="text-align:start">${s.err}</p>`:''}
  <button class="btn blk" data-act="do-login">${ic('check',15)} ورود</button>
  <div class="lk-row" style="margin-top:6px"><button class="linkbtn" data-act="forgot-open">رمز را فراموش کرده‌ام</button><span></span></div>
  <button class="linkbtn" data-act="goHome">بازگشت به صفحهٔ نقش‌ها</button></div></div>`;
}

/* ── ویزارد ── */
/* v5: گام‌های «اولین دسته» و «اولین محصول» حذف شد — منو بعداً در پنل ساخته می‌شود
   v5.0.1: پرسش «نام کافه» هم حذف شد — نام در ثبت‌نام وارد شده و از طراح منو قابل ویرایش است */
function wizSteps(){
 /* v5.0.2: گام «حساب مدیر» حذف شد — حساب مدیر (موبایل + رمز) در ثبت‌نام ساخته می‌شود */
 return [['venue','نوع کسب‌وکار'],['brand','شعار'],['logo','لوگو'],['palette','پالت'],['space','میز و آشپزخانه'],['done','پایان']];
}
function wizardHTML(){
 const st=wizSteps(),key=st[Math.min(App.wiz.step,st.length-1)][0];
 const dots=st.map((s,i)=>`<span class="wstep ${i===App.wiz.step?'on':i<App.wiz.step?'done':''}">${s[1]}</span>`).join('');
 const showPrev=key!=='venue';
 return `<div class="wiz">
  <div class="wiz-top">${ic('coffee',22)}<b>راه‌اندازی کسب‌وکار شما</b><span class="dim">گام ${fa(App.wiz.step+1)} از ${fa(st.length)}</span></div>
  <div class="wiz-steps">${dots}</div>
  <div class="wiz-grid">
   <div class="wiz-card">${wizStepHTML(key)}
    <div class="wiz-nav">${showPrev?`<button class="btn ghost" data-act="wiz-prev">قبلی</button>`:''}
     ${key==='done'?`<button class="btn" data-act="wiz-finish">${ic('check',15)} ورود به پنل مدیریت</button>`
       :`<button class="btn" data-act="wiz-next">بعدی ${ic('arrow',15)}</button>`}</div></div>
   ${showPrev?`<div class="wiz-prev-phone"><div class="phone"><div class="phone-inner">${menuFrameHTML(false)}</div></div>
     <p class="wiz-hint">پیش‌نمایش زنده — دقیقاً همان چیزی که مشتری خواهد دید.</p></div>`:''}
  </div></div>`;
}
function wizStepHTML(key){
 const a=App.wiz;
 if(key==='venue')return `<h2>نوع کسب‌وکار شما چیست؟</h2><p>این انتخاب دائمی است و بعداً از پنل قابل تغییر نیست — آیکون منو و قالب پیشنهادی بر همین اساس تنظیم می‌شود.</p>
  <div class="ven-grid">${Object.entries(VENUES).map(([k,x])=>`<button class="ven-btn ${M().venue===k?'on':''}" data-act="wiz-venue" data-v="${k}">${ic(x.ic,16)}<span>${x.n}</span></button>`).join('')}</div>`;
 if(key==='brand'){const b=a.biz;
  return `<h2>شعار کسب‌وکار</h2><p>نام کافه («<b>${esc(M().brand.name||'—')}</b>») در ثبت‌نام ثبت شده و دست نمی‌زنیم — هر وقت خواستید، در طراح منو روی نام در سرصفحهٔ پیش‌نمایش کلیک کنید و همان‌جا ویرایشش کنید. شعار در سرصفحهٔ منو، رسید چاپی و پیامک فاکتور دیده می‌شود.</p>
   <label class="f"><span>شعار (اختیاری — خالی بگذارید تا پیشنهاد «${venueOf().n}» بنشیند)</span><input data-input="wz-tag" value="${esc(b.tag)}" placeholder="${esc(venueOf().tag)}"></label>
   ${b.err?`<p class="err">${b.err}</p>`:''}`;}
 if(key==='logo'){const lg=M().brand.logo;
  return `<h2>لوگوی شما</h2><p>فایل PNG یا SVG با پس‌زمینهٔ شفاف بهترین نتیجه را می‌دهد — شفافیت فایل دقیقاً حفظ می‌شود (پس‌زمینهٔ شطرنجی = قسمت‌های ترنسپرنت).</p>
   <div style="display:flex;align-items:center;gap:14px">
    <span class="logo-prev" style="width:96px;height:96px;border-radius:20px">${lg?`<img src="${lg}" alt="لوگو">`:ic(venueIcon(),30)}</span>
    <div style="flex:1;display:flex;flex-direction:column;gap:8px">
     <button class="btn sm ${lg?'':'blk'}" data-act="wiz-logo">${ic('img',13)} ${lg?'تعویض لوگو':'انتخاب فایل لوگو'}</button>
     ${lg?`<button class="btn ghost sm" data-act="wiz-logo-del">${ic('trash',13)} حذف لوگو</button>`:''}</div></div>
   <p class="wiz-hint">اختیاری — بعداً هم از پنل مدیریت قابل تغییر است.</p>`;}
 if(key==='palette'){const th=M().theme;
  return `<h2>پالت رنگی منو</h2><p>پیش‌نمایش کنار صفحه زنده به‌روز می‌شود.</p>
   <div class="pal-grid">${PALETTES.map(p=>`<div class="pal ${th.paletteId===p.id?'on':''}" role="button" data-act="wiz-pal" data-id="${p.id}">
    <span class="pal-strip">${['bg','ink','sub','line','acc'].map(k=>`<i style="background:${p.colors[k]}"></i>`).join('')}</span><b>${esc(p.name)}</b></div>`).join('')}</div>
   <div class="cp-grid">${[['acc','تاکیدی'],['bg','زمینه'],['ink','متن'],['sub','ثانویه'],['line','خطوط']].map(([k,l])=>`<label class="cp"><input type="color" value="${th.colors[k]}" data-input="pal-color" data-k="${k}">${l}</label>`).join('')}</div>`;}
 if(key==='space'){const t=a.tbl;
  return `<h2>میز و آشپزخانه</h2><p>دو سؤال کوتاه دربارهٔ فضای شما — هر دو بعداً از تنظیمات قابل تغییرند.</p>
   <p style="font-size:13px;font-weight:800;margin-bottom:8px">۱) میز برای مشتریان دارید؟</p>
   <div class="row-btns" style="margin-bottom:14px">
    <button class="btn sm ${t.want?'':'ghost'}" data-act="wiz-tbl" data-w="1">${ic('table',13)} بله</button>
    <button class="btn sm ${t.want?'ghost':''}" data-act="wiz-tbl" data-w="0">فعلاً نه</button></div>
   ${t.want?`<label class="f"><span>ظرفیت میز اول (نفر)</span><input data-input="wz-seats" inputmode="numeric" value="${esc(t.seats)}"></label>`:''}
   <p style="font-size:13px;font-weight:800;margin:14px 0 8px">۲) آشپزخانه یا بخش آماده‌سازی مجزا دارید؟</p>
   <div class="row-btns">
    <button class="btn sm ${t.kds?'':'ghost'}" data-act="wiz-kds" data-w="1">${ic('chefhat',13)} بله</button>
    <button class="btn sm ${t.kds?'ghost':''}" data-act="wiz-kds" data-w="0">نه، همه‌چیز یک‌جاست</button></div>
   <p class="wiz-hint">با «نه»، نقش آشپزخانه از سیستم حذف می‌شود و صندوق خودش «تحویل شد» را ثبت می‌کند — مناسب کافه‌ها و رستوران‌های کوچک.</p>`;}
 const m=M(),palName=(PALETTES.find(p=>p.id===m.theme.paletteId)||{}).name||'دلخواه';
 const rows=[['نوع کسب‌وکار',venueOf().n],['نام',m.brand.name||'—'],['لوگو',m.brand.logo?'✓ آپلود شد':'—'],['پالت',esc(palName)],
  ['میز',Store.db.tables.length?`میز ${fa(Store.db.tables[0].no)} · ${fa(Store.db.tables[0].seats)} نفره`:'—'],
  ['آشپزخانهٔ مجزا',SET().kds?'بله':'نه — مدیریت یک‌جا در صندوق']];
 return `<h2>همه‌چیز آماده است!</h2><p>خلاصهٔ تنظیمات — همهٔ این‌ها بعداً قابل ویرایش‌اند.</p>
  ${rows.map(([k,v])=>`<div class="hrow"><b>${k}:</b><span>${v}</span></div>`).join('')}
  <p class="wiz-hint">منو را داخل پنل با دسته‌ها و محصولات خودتان می‌سازید؛ دکمهٔ «اعمال قالب منو» هم آیتم‌های نمونهٔ «${venueOf().n}» را یک‌جا می‌آورد.</p>`;
}

/* ── نوار بالا ──
   v5.0.5-test.1: زنگ پیام‌های سیستم برای همهٔ نقش‌ها — شمارندهٔ نخوانده + پنل پیام */
function myRoleKey(){
 if(App.role==='admin')return 'admin';
 if(App.role==='kds')return 'kitchen';
 if(App.role==='cashier')return 'cashier';
 return null;
}
function notifUnread(){
 const rk=myRoleKey();if(!rk)return 0;
 return (Store.db.notifs||[]).filter(n=>n.to===rk&&!n.read).length;
}
function notifBellHTML(){
 const rk=myRoleKey();if(!rk)return '';
 const n=notifUnread();
 return `<button class="icobtn bell ${n?'has':''}" data-act="notif-open" title="پیام‌های سیستم">${ic('send',17)}${n?`<i class="bell-b">${fa(Math.min(n,99))}</i>`:''}</button>`;
}
function appbar(roleName,sub,tabs='',right=''){
 /* v5.0.3: عنوان دکمهٔ بازگشت بر اساس نقش — مقصد واقعی در actions.goHome */
 const backT = App.role==='admin' ? 'بازگشت به پنل مدیریت'
  : (App.role==='kds'||App.role==='cashier') ? 'بازگشت به دسترسی‌های کافه' : 'بازگشت به نقش‌ها';
 return `<header class="appbar">
  <div class="ab-l"><button class="icobtn" data-act="goHome" title="${backT}">${ic('arrow',17)}</button>
   <div><b>${roleName}</b><small>${sub}</small></div></div>
  ${tabs?`<nav class="atabs">${tabs}</nav>`:''}
  <div class="ab-r">${notifBellHTML()}${right}</div></header>`;
}

/* ── مدیریت ──
   v5.0.5-test.1: «خانه» = صفحهٔ اصلی مدیریت — گزارش‌ها + میزها + تنظیمات همه در یک صفحه؛
   طراح منو و مشتریان تب جدا دارند. */
function adminHTML(){
 const t=App.adm.tab,v=venueOf();
 const tabs=`<button class="atab ${t==='home'?'on':''}" data-act="atab" data-tab="home">${ic('chart',15)} خانه</button>
  <button class="atab ${t==='design'?'on':''}" data-act="atab" data-tab="design">${ic('pen',15)} طراحی منو</button>
  <button class="atab ${t==='custs'?'on':''}" data-act="atab" data-tab="custs">${ic('award',15)} مشتریان</button>`;
 return appbar('مدیریت',v.n+' · '+M().brand.name,tabs,
   `${t==='design'?`<span id="saveBadge" class="save-badge">ذخیرهٔ خودکار</span>`:''}
    <button class="icobtn" data-act="logout-admin" title="خروج از حساب مدیر">${ic('logout',17)}</button>`)
  +(t==='design'?designerHTML():t==='custs'?customersHTML():homeDashHTML());
}
/* صفحهٔ اصلی مدیریت — v5.0.5-test.1: گزارش‌ها (پیش‌فرض) + میزها و رزرو + تنظیمات */
function homeDashHTML(){
 const jump=`<div class="hd-jump">
  <button class="jbtn" data-act="home-jump" data-id="hd-reports">${ic('chart',14)} گزارش‌ها</button>
  <button class="jbtn" data-act="home-jump" data-id="hd-tables">${ic('table',14)} میزها و رزرو</button>
  <button class="jbtn" data-act="home-jump" data-id="hd-settings">${ic('sliders',14)} تنظیمات</button></div>`;
 return `<div class="homedash">${jump}
  <div id="hd-reports">${reportsHTML()}</div>
  <div id="hd-tables">${adminTablesHTML()}</div>
  <div id="hd-settings">${settingsHTML()}</div></div>`;
}
function designerHTML(){
 const m=M(),th=m.theme,live=App.adm.live,v=venueOf(),sched=m.brand.sched;
 const palCard=(p,on,custom)=>`<div class="pal ${on?'on':''}" role="button" data-act="pal-set" data-id="${p.id}">
  <span class="pal-strip">${['bg','ink','sub','line','acc'].map(k=>`<i style="background:${p.colors[k]}"></i>`).join('')}</span>
  <b>${esc(p.name)}${custom?`<span class="paldel" data-act="pal-del" data-id="${p.id}">${ic('trash',11)}</span>`:''}</b></div>`;
 const sec=(icn,title,body)=>`<section class="tool-g ${App.adm.acc===title?'open':''}">
  <h4 data-act="acc-tgl" data-sec="${title}">${ic(icn,14)}<span>${title}</span><span class="tplus">${ic('plus',13)}</span></h4>
  <div class="tg-b">${body}</div></section>`;
 const selId=(m.cats.find(c=>c.id===App.adm.selCat)||m.cats[0]||{}).id||'';
 const selCatObj=m.cats.find(c=>c.id===selId);
 const hg1=th.headG1||shade(th.colors.acc,-0.5), hg2=th.headG2||shade(th.colors.acc,0.2);
 const bg1=th.bgG1||th.colors.bg, bg2=th.bgG2||mixHex(th.colors.bg,th.colors.acc,0.32);
 return `<div class="designer">
  <aside class="d-tools">
   <button class="excel-cta" data-act="excel-open" title="بازکردن بخش درون‌ریزی از اکسل">
    <span class="xc-ic">${ic('package',17)}</span>
    <span class="xc-tx"><b>بارگذاری منو از اکسل</b><small>منوی نمونه · قالب خالی · درون‌ریزی یک‌جا</small></span>
    <span class="xc-ch">${ic('down',15)}</span></button>
   ${sec('info','راهنمای ویرایش زنده',`<p>روی نام، شعار، بنرها، دسته‌ها، نام و قیمت آیتم‌ها در پیش‌نمایش کلیک کنید و همان‌جا تایپ کنید. + سرمه‌ای در نوار دسته‌ها = دستهٔ تازه. خط‌چین زیر هر دسته = محصول تازه. ${ic('list',12)} = جابه‌جایی با کشیدن؛ ${ic('sliders',12)} = آپشن، قیمت خرید، موجودی و نظرات.</p>`)}
   ${sec('list','دسته‌بندی‌ها',`<button class="btn ghost sm blk" data-act="cat-add">${ic('plus',13)} دستهٔ تازه</button>
    <div style="margin-top:8px">${m.cats.map(c=>`<div class="lrow"><b style="cursor:pointer" data-act="jump-cat" data-cat="${c.id}">${esc(c.name)}</b>
     <button class="icobtn xs" data-act="cat-up" data-cat="${c.id}">${ic('up',12)}</button>
     <button class="icobtn xs" data-act="cat-down" data-cat="${c.id}">${ic('down',12)}</button>
     <button class="icobtn xs danger" data-act="cat-del" data-cat="${c.id}">${ic('trash',12)}</button></div>`).join('')||'<p class="mini">هنوز دسته‌ای نیست.</p>'}</div>`)}
   ${sec('grid','محصولات',`${m.cats.length?`<label class="f" style="margin-bottom:6px"><span>دسته</span>
    <select data-input="adm-cat">${m.cats.map(c=>`<option value="${c.id}"${c.id===selId?' selected':''}>${esc(c.name)}</option>`).join('')}</select></label>
    <button class="btn ghost sm blk" data-act="item-add" data-cat="${selId}">${ic('plus',13)} محصول تازه در این دسته</button>
    <div style="margin-top:8px">${(selCatObj&&selCatObj.items||[]).map(i=>`<div class="lrow"><b style="cursor:pointer" data-act="jump-item" data-id="${i.id}">${esc(i.name)}</b>
     <button class="icobtn xs" data-act="item-up" data-id="${i.id}">${ic('up',12)}</button>
     <button class="icobtn xs" data-act="item-down" data-id="${i.id}">${ic('down',12)}</button>
     <button class="icobtn xs danger" data-act="item-del" data-id="${i.id}">${ic('trash',12)}</button></div>`).join('')||'<p class="mini">این دسته خالی است.</p>'}</div>`
    :'<p class="mini">اول یک دسته بسازید.</p>'}`)}
   ${sec('package','درون‌ریزی از اکسل',`<p>دسته‌ها و محصولات را با یک فایل اکسل یک‌جا وارد کنید. اول «منوی نمونه» را ببینید یا «قالب خالی» را بگیرید؛ بعد از بارگذاری، خلاصه را تأیید می‌کنید.</p>
    <div class="row-btns" style="flex-wrap:wrap">
     <button class="btn sm ghost" data-act="excel-sample">${ic('package',13)} منوی نمونه (اکسل)</button>
     <button class="btn sm ghost" data-act="excel-tpl">${ic('list',13)} قالب خالی</button>
     <button class="btn sm" data-act="excel-pick">${ic('send',13)} بارگذاری فایل اکسل</button></div>
    <p class="mini" style="margin-top:6px">فرمت فقط xlsx · حداکثر ۵MB و ۵۰۰ ردیف · شیت «راهنما» داخل هر دو فایل هست · تا تأیید شما چیزی ذخیره نمی‌شود</p>`)}
   ${sec(v.ic,'نوع کسب‌وکار و قالب',`<p>نوع کسب‌وکار («${v.n}») هنگام راه‌اندازی انتخاب شده و دیگر قابل تغییر نیست. فقط قالب آمادهٔ همین نوع قابل اعمال است.</p>
    <button class="btn ghost sm blk tpl-hint" data-act="template-apply">${ic('refresh',13)} اعمال قالب منوی «${v.n}»</button>`)}
   ${sec('img','لوگو',`<div class="logo-box"><span class="logo-prev">${m.brand.logo?`<img src="${m.brand.logo}" alt="لوگو">`:ic(v.ic,20)}</span>
     <div class="row-btns" style="flex:1">
      <button class="btn sm ${m.brand.logo?'':'ghost'}" data-act="logo-pick">${ic('img',13)} ${m.brand.logo?'تغییر لوگو':'آپلود لوگو'}</button>
      ${m.brand.logo?`<button class="btn ghost sm" data-act="logo-del">${ic('trash',13)}</button>`:''}</div></div>`)}
   ${sec('clock','ساعات کاری هفتگی',`<p>انتخاب مستقیم از ساعت سیستم (۲۴ ساعته) · «تعطیل» = روز غیرفعال</p>
    <div class="sched-edit">${DAYS7.map((d,i)=>{const r=sched[i];
     return `<div class="sc-day${r.x?' x':''}"><div class="sc-top"><b>${d}</b><button class="sc-x${r.x?' on':''}" data-act="sch-x" data-i="${i}">${r.x?'تعطیل ✓':'تعطیل'}</button></div>
     ${r.x?'<div class="mini" style="flex:1;text-align:center">—</div>':`<div class="sc-times"><input type="time" data-input="sch-o" data-i="${i}" value="${r.o}"><input type="time" data-input="sch-c" data-i="${i}" value="${r.c}"></div>`}</div>`;}).join('')}</div>`)}
   ${sec('img','بنر سرصفحه (هدر)',`<p>پشتِ لوگو و نام کسب‌وکار قرار می‌گیرد.</p>
    <div class="row-btns">
     <button class="btn sm ${th.headMode==='plain'?'':'ghost'}" data-act="head-mode" data-m="plain">بدون</button>
     <button class="btn sm ${th.headMode==='gradient'?'':'ghost'}" data-act="head-mode" data-m="gradient">${ic('drop',13)} گرادیان</button>
     <button class="btn sm ${th.headMode==='image'?'':'ghost'}" data-act="head-img">${ic('img',13)} تصویر</button></div>
    ${th.headMode==='gradient'?`<div class="grad-row">
     <label class="cp"><input type="color" value="${hg1}" data-input="head-g1">شروع</label>
     <label class="cp"><input type="color" value="${hg2}" data-input="head-g2">پایان</label></div>
    <p class="mini">رنگ‌ها زنده روی پیش‌نمایش اعمال می‌شوند. با تغییر پالت، گرادیان دوباره از پالت ساخته می‌شود.</p>`:''}
    ${th.headMode==='image'&&th.headImg?`<div class="promoline"><button class="btn ghost sm" data-act="head-img-del">${ic('trash',13)} حذف تصویر هدر</button></div>`:''}`)}
   ${sec('send','بنرهای تبلیغاتی',`<p>اسلایدر خودکار (هر ۳ ثانیه) زیر سرصفحه. متن را روی خود بنر تایپ کنید؛ دو انتخابگر رنگ گرادیان کنار دکمه‌های خود بنر در پیش‌نمایش است.</p>
    <button class="btn ghost sm blk" data-act="bnr-add">${ic('plus',13)} افزودن بنر تبلیغاتی</button>
    <p class="mini" style="margin-top:6px">تعداد فعلی: ${fa((m.banners||[]).length)}</p>`)}
   ${sec('grid','زمینهٔ منو',`<div class="row-btns" style="flex-wrap:wrap">
     <button class="btn sm ${th.bgMode==='color'?'':'ghost'}" data-act="mbg-mode" data-m="color">${ic('drop',13)} رنگ</button>
     <button class="btn sm ${th.bgMode==='gradient'?'':'ghost'}" data-act="mbg-grad">${ic('drop',13)} گرادیان</button>
     <button class="btn sm ${th.bgMode==='image'?'':'ghost'}" data-act="mbg-img">${ic('img',13)} تصویر</button>
     <button class="btn sm ${th.bgMode==='video'?'':'ghost'}" data-act="mbg-video">${ic('eye',13)} ویدیو</button></div>
    ${th.bgMode==='gradient'?`<div class="grad-row">
     <label class="cp"><input type="color" value="${bg1}" data-input="bg-g1">شروع</label>
     <label class="cp"><input type="color" value="${bg2}" data-input="bg-g2">پایان</label></div>
    <p class="mini">رنگ‌ها زنده اعمال می‌شوند؛ با تغییر پالت، گرادیان از پالتِ تازه ساخته می‌شود.</p>`:''}
    ${th.bgMode==='video'&&th.bgVid?`<div class="promoline"><button class="btn ghost sm" data-act="mbg-vid-del">${ic('trash',13)} حذف ویدیو</button></div>`:''}`)}
   ${sec('drop','پالت رنگ منو',`<div class="pal-grid">${PALETTES.map(p=>palCard(p,th.paletteId===p.id,false)).join('')}
     ${(m.customPalettes||[]).map(p=>palCard(p,th.paletteId===p.id,true)).join('')}</div>
    <div class="cp-grid">${[['acc','تاکیدی'],['bg','زمینه'],['ink','متن'],['sub','ثانویه'],['line','خطوط']].map(([k,l])=>`<label class="cp"><input type="color" value="${th.colors[k]}" data-input="pal-color" data-k="${k}">${l}</label>`).join('')}</div>
    <label class="f" style="margin-bottom:8px"><span>نام پالت سفارشی</span><input id="pal-name" placeholder="مثلاً: پاییز ۱۴۰۳"></label>
    <button class="btn sm blk" data-act="pal-save">${ic('plus',13)} ذخیرهٔ این ترکیب</button>`)}
   ${sec('grid','چیدمان',`<div class="row-btns">
     <button class="btn sm ${th.layout==='list'?'':'ghost'}" data-act="theme" data-k="layout" data-v="list">${ic('list',13)} فهرست</button>
     <button class="btn sm ${th.layout==='grid'?'':'ghost'}" data-act="theme" data-k="layout" data-v="grid">${ic('grid',13)} شبکه</button></div>`)}
  </aside>
  <div class="d-canvas">
   <div class="d-canvas-head"><span>${live?'پیش‌نمایش زنده — دیدِ مشتری':'پیش‌نمایش زنده — حالت ویرایش'}</span>
    <button class="btn ghost sm" data-act="toggle-live">${live?ic('pen',14)+' بازگشت به ویرایش':ic('eye',14)+' دیدِ مشتری'}</button></div>
   <div class="phone"><div class="phone-inner">${menuFrameHTML(!live)}</div></div></div></div>`;
}

/* ── میزها و رزرو ── */
function tableCounts(){
 const cnt={};Object.keys(TST).forEach(k=>cnt[k]=0);
 Store.db.tables.forEach(t=>{if(cnt[t.status]!=null)cnt[t.status]++;});
 return cnt;
}
function sumRowHTML(){
 const cnt=tableCounts();
 return `<div class="sumrow">${Object.entries(TST).map(([k,v])=>`<span class="sumchip"><span class="dot" style="background:${v.c}"></span>${v.t} <b>${fa(cnt[k])}</b></span>`).join('')}</div>`;
}
function tcardHTML(t){
 const st=TST[t.status]||TST.free;
 return `<button class="tcard" style="--st:${st.c}" ${(t.status==='waiting'||t.status==='ordering')?'data-pulse':''} data-act="table-edit" data-id="${t.id}">
  <b>${fa(t.no)}</b><small>${esc(t.zone)} · ${fa(t.seats)} نفره</small><span class="tst">${st.t}</span></button>`;
}
function todayReservations(){
 const t0=sod(Date.now()),t1=t0+7*864e5;
 return Store.db.reservations.filter(r=>r.ts>=t0&&r.ts<t1&&['pending','active'].includes(r.status)).sort((a,b)=>a.ts-b.ts);
}
const RESV_ST={pending:{t:'در انتظار تأیید',c:'warn'},active:{t:'تأیید شده',c:'info'},done:{t:'مهمان نشست',c:'ok'},cancel:{t:'لغو شد',c:'muted'},noshow:{t:'عدم حضور',c:'muted'}};
/* v5.0.5-test.1: مدیریت کامل رزرو — تأیید/نشستن/لغو/عدم حضور (صندوق و پنل مدیریت) */
function resvListHTML(manage){
 const rs=todayReservations();
 const pend=rs.filter(r=>r.status==='pending'),act=rs.filter(r=>r.status==='active');
 if(!rs.length)return `<p class="mini" style="margin-top:14px">رزروی برای امروز و ۶ روز آینده ثبت نشده.</p>`;
 const row=r=>{const st=RESV_ST[r.status]||RESV_ST.pending;
  const dtfR=new Intl.DateTimeFormat('fa-IR',{weekday:'short',day:'numeric',month:'long'});
  return `<div class="hrow"><span class="tagpill ${st.c==='ok'?'ok':''}">${st.t}</span><b>${tf.format(r.ts)} · ${dtfR.format(r.ts)}</b>
   <span>${esc(r.name)} · ${fa(r.persons)} نفر${r.phone?' · <span dir="ltr">'+esc(r.phone)+'</span>':''}${r.tableNo?' · میز '+fa(r.tableNo):''}${r.note?' · '+esc(r.note):''}</span>
   ${manage?`<span class="rsv-ops">
    ${r.status==='pending'?`<button class="btn ghost sm" data-act="resv-ok" data-id="${r.id}">${ic('check',12)} تأیید</button>`:''}
    ${r.status==='active'?`<button class="btn ghost sm" data-act="resv-sit" data-id="${r.id}">${ic('check',12)} نشست</button>`:''}
    <button class="btn ghost sm" data-act="resv-cancel" data-id="${r.id}">${ic('x',12)} لغو</button></span>`:''}
  </div>`;};
 return `<div style="margin-top:16px"><h3 style="font-size:13px;font-weight:800;margin-bottom:6px">${ic('cal',14)} رزروها</h3>
  ${pend.length?`<p class="mini" style="margin:4px 0">در انتظار تأیید (${fa(pend.length)})</p>${pend.map(row).join('')}`:''}
  ${act.length?`<p class="mini" style="margin:8px 0 4px">تأییدشده (${fa(act.length)})</p>${act.map(row).join('')}`:''}
  ${(!pend.length&&!act.length)?'<p class="mini">رزرو فعالی نیست — رزروهای لغوشده و گذشته در گزارش می‌آیند.</p>':''}</div>`;
}
function adminTablesHTML(){
 const ts=Store.db.tables;
 return `<div class="cwrap-c">
  <div class="r-head"><h2>میزها</h2><div style="display:flex;gap:8px;flex-wrap:wrap">
   <button class="btn ghost sm" data-act="resv-new">${ic('cal',13)} رزرو</button>
   <button class="btn ghost sm" data-act="qr-open">${ic('grid',13)} برگهٔ QR</button>
   <button class="btn ghost sm" data-act="table-group">${ic('plus',13)} گروهی</button>
   <button class="btn sm" data-act="table-add">${ic('plus',13)} میز تازه</button></div></div>
  ${sumRowHTML()}
  ${ts.length?`<div class="tbl-grid">${ts.map(tcardHTML).join('')}</div>`
   :`<div class="empty-big">${ic('table',34)}<p>هنوز میزی نساخته‌اید</p><button class="btn sm" data-act="table-add">${ic('plus',14)} اولین میز</button></div>`}
  ${resvListHTML(true)}</div>`;
}
function tableModal(t){
 const btns=Object.entries(TST).map(([k,v])=>`<button class="st-b ${t.status===k?'on':''}" style="--sc:${v.c}" data-act="table-status" data-id="${t.id}" data-st="${k}"><span class="dot" style="background:${v.c}"></span>${v.t}</button>`).join('');
 ui.modal(`<p style="font-weight:800">میز ${fa(t.no)} <span class="dim">· ${esc(t.zone)}</span></p>
  <div class="st-grid">${btns}</div>
  <label class="f"><span>ظرفیت (نفر)</span><input data-input="t-seats" data-id="${t.id}" inputmode="numeric" value="${fa(t.seats)}"></label>
  <div class="m-acts"><button class="btn danger" data-act="table-del" data-id="${t.id}">${ic('trash',14)} حذف میز</button><button class="btn ghost" data-act="close-modal">بستن</button></div>`,
  {title:`میز ${fa(t.no)}`});
}
function qrFor(no){
 const url=location.href.split(/[?#]/)[0].replace(/panel-admin\.php.*/,'')+'index.html?table='+no;
 try{
  if(typeof qrcode!=='function')return null;
  const q=qrcode(0,'M');q.addData(url);q.make();
  return {url,img:q.createDataURL(4,8)};
 }catch(e){return {url,img:null};}
}
function qrModal(){
 const cards=Store.db.tables.map(t=>{const q=qrFor(t.no);
  return `<div class="qcard">${q.img?`<img src="${q.img}" alt="QR میز ${fa(t.no)}">`:`<p class="mini" dir="ltr">${q.url}</p>`}<b>میز ${fa(t.no)}</b></div>`;}).join('');
 ui.modal(`<p class="mini" style="margin-bottom:10px">هر میز یک برگهٔ QR می‌گیرد؛ مشتری اسکن می‌کند و منو با میزِ از پیش انتخاب‌شده باز می‌شود.</p>
  <div class="qgrid">${cards||'<p class="mini">اول میز بسازید.</p>'}</div>
  <div class="m-acts"><button class="btn" data-act="qr-print" ${Store.db.tables.length?'':'disabled'}>${ic('printer',15)} چاپ برگهٔ QRها</button>
  <button class="btn ghost" data-act="close-modal">بستن</button></div>`,{title:'QR میزها'});
}
function qrPrint(){
 $('#print-root').innerHTML=Store.db.tables.map(t=>{const q=qrFor(t.no);
  return `<div class="qcard">${q.img?`<img src="${q.img}">`:`<p dir="ltr">${q.url}</p>`}<b>میز ${fa(t.no)}</b><div>منوی دیجیتال</div></div>`;}).join('');
 window.print();
}

/* ── تنظیمات ── */
function settingsHTML(){
 const S=SET();
 const lrow=(main,pill,del)=>`<div class="lrow"><b>${esc(main)}</b>${pill?`<span class="tagpill">${pill}</span>`:''}${del||''}</div>`;
 /* v5.0.5: هشدار رمز ضعیف — نتیجهٔ کش‌شدهٔ pw_audit سمت سرور */
 const A=(Store.db.auth||{}).pwaudit||null;
 const weak=[];
 if(A){
  if(A.admin&&A.admin.weak) weak.push(['رمز پنل مدیریت',A.admin.kind]);
  Object.keys(A.users||{}).forEach(id=>{
   const v=A.users[id];
   if(v&&v.weak){const u=(Store.db.users||[]).find(x=>String(x.id)===String(id));
    weak.push([u?('رمز «'+u.name+'»'):'رمز یک کاربر پرسنلی',v.kind]);}
  });
 }
 const pwHtml=!A
  ?`<p class="mini">هنوز بررسی نشده. با یک کلیک، رمزهای رایج جهان (مثل 12345678) و «موبایل = رمز» روی همهٔ حساب‌ها آزموده می‌شود؛ نتیجه کش می‌شود و تا تغییر رمز دوباره محاسبه نمی‌شود.</p>
    <button class="btn sm" data-act="pw-audit-run" id="pwauditbtn">${ic('check',13)} بررسی قدرت رمزها</button>`
  :weak.length===0
  ?`<div class="pwl ok">${ic('check',14)} همهٔ رمزها سالم‌اند — رمز رایج یا قابل حدسی پیدا نشد.</div>
    <button class="btn ghost sm" data-act="pw-audit-run" id="pwauditbtn">بررسی مجدد</button>`
  :weak.map(([n,k])=>`<div class="pwl bad">${ic('lock',14)} <b>${esc(n)}</b> ضعیف است — ${k==='phone'?'شبیه شمارهٔ موبایل است':'جزو رمزهای رایج جهان است'}. لطفاً عوضش کنید.</div>`).join('')
   +`<button class="btn ghost sm" data-act="pw-audit-run" id="pwauditbtn">بررسی مجدد</button>`;
 return `<div class="setwrap">
  <div class="r-head"><h2>تنظیمات</h2></div>
  <section class="setsec"><h3>${ic('lock',15)} امنیت رمزها</h3>${pwHtml}
   <p class="mini" style="margin-top:8px">قاعدهٔ رمز تازه: حداقل ۸ کاراکتر با حداقل یک حرف و یک عدد؛ رمزهای رایج پذیرفته نمی‌شود.</p></section>
  <section class="setsec"><h3>${ic('chefhat',15)} فضا و آشپزخانه</h3>
   <div class="setrow"><span>آشپزخانه / بخش آماده‌سازی مجزا</span><input type="checkbox" class="sw2" ${S.kds?'checked':''} data-act="set-tgl" data-k="kds"></div>
   <p class="mini">اگر خاموش باشد، نقش «آشپزخانه» از صفحهٔ نقش‌ها حذف می‌شود و صندوق مستقیماً «تحویل شد» را ثبت می‌کند — مناسب کافه‌های کوچک.</p></section>
  <section class="setsec"><h3>${ic('card',15)} مالیات و انعام</h3>
   <div class="setrow"><span>مالیات بر ارزش افزوده</span><span style="display:flex;gap:8px;align-items:center">
    <input data-input="vat-pct" inputmode="numeric" value="${fa(S.vatPct)}" title="درصد"> ٪
    <input type="checkbox" class="sw2" ${S.vatOn?'checked':''} data-act="set-tgl" data-k="vatOn"></span></div>
   <div class="setrow"><span>انعام اختیاری در پرداخت (درگاه و صندوق)</span><input type="checkbox" class="sw2" ${S.tipOn?'checked':''} data-act="set-tgl" data-k="tipOn"></div>
   <p class="mini">با روشن بودن، مشتری هنگام پرداخت می‌تواند درصد (۵/۱۰/۱۵٪) یا مبلغ دلخواه به‌عنوان انعام اضافه کند و برایش توضیح بنویسد — انعام در رسید سفارش و گزارش شیفت جداگانه نمایش داده می‌شود.</p>
   <p class="mini">مالیات روی مبلغ پس از تخفیف‌ها حساب و در رسید جداگانه می‌آید.</p></section>
  <section class="setsec"><h3>${ic('trend',15)} هدف فروش روزانه</h3>
   <div class="setrow"><span>مبلغ هدف (تومان — ۰ = بی‌هدف)</span><input data-input="tg-target" inputmode="numeric" value="${S.target?moneyFmt(String(S.target)):''}"></div>
   <p class="mini">اگر بزرگ‌تر از صفر باشد، نوار پیشرفتِ فروشِ امروز بالای گزارش‌ها نمایش داده می‌شود.</p></section>
  <section class="setsec"><h3>${ic('clock',15)} ساعت خوش (تخفیف زمانی)</h3>
   <div class="setrow"><span>فعال</span><input type="checkbox" class="sw2" ${S.hh.on?'checked':''} data-act="set-tgl" data-k="hh"></div>
   <div class="setrow"><span>بازهٔ ساعات</span><span style="display:flex;gap:8px;align-items:center">
    <input data-input="hh-from" inputmode="numeric" value="${fa(S.hh.from)}"> تا
    <input data-input="hh-to" inputmode="numeric" value="${fa(S.hh.to)}"></span></div>
   <div class="setrow"><span>درصد تخفیف</span><input data-input="hh-off" inputmode="numeric" value="${fa(S.hh.off)}"></div></section>
  <section class="setsec"><h3>${ic('award',15)} باشگاه مشتریان و امتیاز</h3>
   <div class="setrow"><span>امتیاز باشگاه فعال باشد</span><input type="checkbox" class="sw2" ${PT().on?'checked':''} data-act="set-tgl" data-k="pts"></div>
   <div class="setrow"><span>هر <b>چند تومان خرید = ۱ امتیاز؟</b></span><input data-input="pt-earn" inputmode="numeric" value="${fa(PT().earn)}"></div>
   <div class="setrow"><span>هر امتیاز هنگام خرید = <b>چند تومان تخفیف؟</b></span><input data-input="pt-value" inputmode="numeric" value="${fa(PT().value)}"></div>
   <div class="setrow"><span>سقف مصرف امتیاز (درصد مبلغ سبد)</span><input data-input="pt-max" inputmode="numeric" value="${fa(PT().maxPct)}"> ٪</div>
   <p class="mini">همهٔ این اعداد به‌دست شماست: نرخ صدور امتیاز بر اساس مبلغ خرید، ارزش تخفیفی هر امتیاز و حداکثر درصدی از سبد که مشتری می‌تواند با امتیاز بپردازد. تغییرها بلافاصله برای همه اعمال می‌شود.</p></section>
  <section class="setsec"><h3>${ic('gift',15)} کدهای تخفیف</h3>
   ${Store.db.promos.length?Store.db.promos.map(p=>lrow(p.code,'٪'+fa(p.off)+(p.on?'':' · خاموش'),`<button class="icobtn xs danger" data-act="promo-del" data-id="${p.id}">${ic('trash',12)}</button>`)).join(''):'<p class="mini">هنوز کدی نساخته‌اید.</p>'}
   <div class="promoline" style="margin-top:10px">
    <input data-input="pm-code" placeholder="کد (مثلاً WELCOME10)" value="${esc(App.adm.nu.code)}" style="flex:2">
    <input data-input="pm-off" inputmode="numeric" placeholder="٪" value="${esc(App.adm.nu.off)}" style="flex:1">
    <button class="btn sm" data-act="promo-new">${ic('plus',13)}</button></div>
   <p class="mini">مشتری کد را در سبد خرید وارد می‌کند.</p></section>
  <section class="setsec"><h3>${ic('card',15)} درگاه پرداخت آنلاین</h3>
   ${(()=>{
     const p = (Store.db.payment) || {};
     const isOn = p.en && p.merchantSet;
     const status = isOn
       ? '<span class="tagpill" style="background:#E8F4E3;color:#3D6F2B">متصل به ' + esc(p.provFa || 'زرین‌پال') + '</span>'
       : '<span class="tagpill">درگاه وصل نیست</span>';
     const st = p.st && p.st.lastTest;
     const lastMsg = st
       ? (st.ok ? '<p class="mini" style="color:#4F7A3D">✅ ' + esc(st.msg) + '</p>' : '<p class="mini" style="color:#B4531F">⚠️ ' + esc(st.msg) + '</p>')
       : '';
     return status +
       '<p class="mini">با اتصال درگاه پرداخت، مشتریان می‌توانند سفارش خود را آنلاین پرداخت کنند. پول مستقیماً به حساب بانکی خودتان واریز می‌شود.</p>' +
       lastMsg +
       '<button class="btn sm" data-act="pay-cfg">' + ic('sliders',13) + ' تنظیمات درگاه پرداخت</button>';
   })()}
  </section>
  <section class="setsec"><h3>${ic('user',15)} کاربران صندوق و آشپزخانه</h3>
   ${Store.db.users.length?Store.db.users.map(u=>lrow(esc(u.name),[u.phone?('موبایل '+fa(u.phone)):'',u.pin?'پین دارد':'',u.ph?'رمز دارد':''].filter(Boolean).join(' · ')||'بدون راه ورود',`<button class="icobtn xs danger" data-act="user-del" data-id="${u.id}">${ic('trash',12)}</button>`)).join(''):'<p class="mini">کاربری ثبت نشده — برای پرسنل، موبایل و رمز ثبت کنید تا مثل مدیر با «نام کاربری + رمز» وارد شوند.</p>'}
   <div class="promoline" style="margin-top:10px">
    <input data-input="us-name" placeholder="نام کاربر" value="${esc(App.adm.nuu.name)}" style="flex:1">
    <input data-input="us-phone" inputmode="numeric" dir="ltr" placeholder="موبایل ۰۹…" value="${esc(App.adm.nuu.phone)}" style="flex:1">
    <input data-input="us-pass" type="password" placeholder="رمز (حداقل ۸ — حرف + عدد)" value="${esc(App.adm.nuu.pass)}" style="flex:1">
    <input data-input="us-pin" inputmode="numeric" placeholder="پین ۴ رقمی (اختیاری)" value="${esc(App.adm.nuu.pin)}" style="flex:1">
    <button class="btn sm" data-act="user-new">${ic('plus',13)}</button></div>
   <p class="mini">v5.0.2 — ورود همهٔ نقش‌ها با «شمارهٔ موبایل + رمز» است؛ پین فقط میان‌بر قدیمی برای کاربرانی است که رمز ندارند. رمز پرسنل سمت سرور هش می‌شود و هرگز متنی ذخیره نمی‌شود؛ از v5.0.5 رمزهای کوتاه‌تر از ۸ یا رایج رد می‌شوند و رمزهای ضعیفِ فعلی در «امنیت رمزها» بالای همین صفحه هشدار داده می‌شوند.</p></section>
 </div>`;
}

/* ── گزارش‌ها ── */
function barsSVG(vals,labels){
 const W=680,H=190,pt=16,pb=26,pl=10,pr=10,n=vals.length||1;
 const maxV=Math.max(...vals,1),avg=vals.reduce((a,b)=>a+b,0)/n;
 const bw=(W-pl-pr)/n,iw=bw*.62,H0=H-pb,mx=Math.max(...vals);
 let out=`<line x1="${pl}" y1="${H0+.5}" x2="${W-pr}" y2="${H0+.5}" class="cbase"/>`;
 if(avg>0){const ay=H0-Math.round(avg/maxV*(H0-pt));out+=`<line x1="${pl}" x2="${W-pr}" y1="${ay}" y2="${ay}" class="cavg"/>`;}
 vals.forEach((v,i)=>{
  const bh=Math.max(v>0?3:1.5,Math.round(v/maxV*(H0-pt)));
  const x=pl+i*bw+(bw-iw)/2;
  out+=`<rect x="${x.toFixed(1)}" y="${H0-bh}" width="${iw.toFixed(1)}" height="${bh.toFixed(1)}" rx="3.5" class="cbar${v===mx&&v>0?' hi':''}"><title>${labels[i]||''} — ${faM(v)} تومان</title></rect>`;
  if(labels[i]!=null)out+=`<text x="${(x+iw/2).toFixed(1)}" y="${H-8}" class="clab">${labels[i]}</text>`;
 });
 return `<svg viewBox="0 0 ${W} ${H}" class="chart">${out}</svg>`;
}
function donutSVG(parts,total){
 const R=44,C=2*Math.PI*R;let off=0,segs='';
 parts.forEach(p=>{const len=total?p.v/total*C:0;
  segs+=`<circle r="${R}" cx="60" cy="60" fill="none" stroke="${p.c}" stroke-width="14" stroke-dasharray="${Math.max(len-2,0)} ${C-len+2}" stroke-dashoffset="${-off}"><title>${p.t}: ${fa(p.v)}</title></circle>`;off+=len;});
 return `<svg viewBox="0 0 120 120" class="donut"><g transform="rotate(-90 60 60)">${segs}</g>
  <text x="60" y="58" text-anchor="middle" class="dnum">${fa(total)}</text><text x="60" y="75" text-anchor="middle" class="dlab">سفارش</text></svg>`;
}
function reportData(){
 const r=App.adm.range,now=Date.now(),len=r==='today'?1:r==='7d'?7:30;
 const from=sod(now)-(len-1)*864e5,pFrom=from-len*864e5;
 const all=Store.db.orders;
 const cur=all.filter(o=>o.ts>=from),prev=all.filter(o=>o.ts>=pFrom&&o.ts<from);
 const sum=(a,f)=>a.reduce((s,o)=>s+(f?f(o):o.total),0);
 let trend;
 if(r==='today'){
  const hv=[],hl=[];
  for(let h=7;h<=23;h++){hv.push(cur.filter(o=>new Date(o.ts).getHours()===h).reduce((s,o)=>s+o.total,0));hl.push(fa(h));}
  trend={vals:hv,labels:hl};
 }else{
  const dv=[],dl=[];
  for(let i=len-1;i>=0;i--){const d0=sod(now)-i*864e5;
   dv.push(all.filter(o=>o.ts>=d0&&o.ts<d0+864e5).reduce((s,o)=>s+o.total,0));
   dl.push(r==='7d'?dtfW.format(d0):(i%5===0?dtfD.format(d0):null));}
  trend={vals:dv,labels:dl};
 }
 const hrs={vals:[],labels:[]};
 if(r!=='today'){for(let h=7;h<=23;h++){hrs.vals.push(cur.filter(o=>new Date(o.ts).getHours()===h).reduce((s,o)=>s+o.total,0));hrs.labels.push(fa(h));}}
 const mc={cash:0,card:0,online:0};cur.forEach(o=>{mc[o.method]=(mc[o.method]||0)+1;});
 const agg={};cur.forEach(o=>o.items.forEach(i=>{agg[i.name]=(agg[i.name]||0)+i.qty;}));
 const top=Object.entries(agg).map(([name,q])=>({name,q})).sort((a,b)=>b.q-a.q).slice(0,6);
 const rated=cur.filter(o=>o.rated>0);
 const hasCost=cur.some(o=>o.items.some(i=>(i.cost||0)>0));
 const profit=cur.reduce((s,o)=>s+(o.total-o.vat-o.tip)-o.items.reduce((x,i)=>x+(i.cost||0)*i.qty,0),0);
 return {cur,prev,rev:sum(cur),n:cur.length,trend,hrs,mc,top,hasCost,profit,
  pts:cur.reduce((s,o)=>s+(o.earned||0),0),
  rating:rated.length?Math.round(rated.reduce((s,o)=>s+o.rated,0)/rated.length*10)/10:0,
  recent:[...cur].sort((a,b)=>b.ts-a.ts).slice(0,6)};
}
function reportsHTML(){
 const r=App.adm.range,d=reportData(),S=SET();
 const delta=(a,b)=>b>0?Math.round((a-b)/b*100):null;
 const dr=delta(d.rev,d.prev.reduce((s,o)=>s+o.total,0));
 const avg=d.n?Math.round(d.rev/d.n):0;
 const dParts=[{t:'نقدی',v:d.mc.cash||0,c:'#9C8B6F'},{t:'کارتخوان',v:d.mc.card||0,c:'#B4531F'},{t:'درگاه آنلاین',v:d.mc.online||0,c:'#2B2118'}];
 const topMax=Math.max(...d.top.map(x=>x.q),1);
 const kpi=(l,v,unit,dl)=>`<div class="kpi"><small>${l}</small><b>${v}${unit?` <i>${unit}</i>`:''}</b>${dl||''}</div>`;
 const pct=x=>x==null?'':`<span class="dl ${x>=0?'up':'dn'}">${ic(x>=0?'trend':'down',12)} ${fa(Math.abs(x))}٪ نسبت به دورهٔ قبل</span>`;
 const todayRev=Store.db.orders.filter(o=>o.ts>=sod(Date.now())).reduce((s,o)=>s+o.total,0);
 const eta=prepETA();
 const tbar=S.target>0?`<div class="tbar-w"><small>هدف امروز: ${faM(Math.min(todayRev,S.target))} از ${faM(S.target)} تومان (${fa(Math.min(100,Math.round(todayRev/S.target*100)))}٪)</small>
  <div class="tbar"><i style="width:${Math.min(100,Math.round(todayRev/S.target*100))}%"></i></div></div>`:'';
 const chips=[
  d.rating?`<span class="rchip">${ic('star',13)} رضایت مشتری <b>${fa(d.rating)}</b> از ۵</span>`:'',
  d.hasCost?`<span class="rchip">${ic('trend',13)} سود تقریبی دوره <b>${faM(d.profit)}</b> تومان</span>`:'',
  eta?`<span class="rchip">${ic('clock',13)} میانگین آماده‌سازی <b>~${fa(eta)}</b> دقیقه</span>`:''
 ].filter(Boolean).join('');
 return `<div class="rwrap">
  <div class="r-head"><h2>گزارش فروش و عملکرد</h2>
   <div class="seg"><button class="${r==='today'?'on':''}" data-act="range" data-r="today">امروز</button>
   <button class="${r==='7d'?'on':''}" data-act="range" data-r="7d">۷ روز</button>
   <button class="${r==='30d'?'on':''}" data-act="range" data-r="30d">۳۰ روز</button></div></div>
  ${tbar}
  <div class="kpis">
   ${kpi('فروش دوره',faM(d.rev),'تومان',pct(dr))}
   ${kpi('تعداد سفارش',fa(d.n),'',pct(delta(d.n,d.prev.length)))}
   ${kpi('میانگین سبد',faM(avg),'تومان')}
   ${kpi('امتیاز صادرشده',fa(d.pts),'')}</div>
  ${chips?`<div class="rchips">${chips}</div>`:''}
  <div class="rgrid">
   <section class="rbox wide"><h3>${ic('trend',15)} روند فروش ${r==='today'?'ساعتی امروز':'روزانه'}</h3>
    ${barsSVG(d.trend.vals,d.trend.labels)}
    <div class="legend"><span class="lg"><i style="background:var(--accent)"></i>فروش</span><span class="lg">— — میانگین: <b>${faM(Math.round(d.trend.vals.reduce((a,b)=>a+b,0)/(d.trend.vals.length||1)))}</b> تومان</span></div></section>
   ${r!=='today'?`<section class="rbox"><h3>${ic('clock',15)} ساعات پرترافیک</h3>${barsSVG(d.hrs.vals,d.hrs.labels)}</section>`:''}
   <section class="rbox"><h3>${ic('cash',15)} روش پرداخت</h3>
    <div class="donut-wrap">${donutSVG(dParts,d.n)}
     <div class="legend" style="flex-direction:column;gap:8px">${dParts.map(p=>`<span class="lg"><i style="background:${p.c}"></i>${p.t} — <b>${fa(p.v)}</b></span>`).join('')}</div></div></section>
   <section class="rbox wide"><h3>${ic('flame',15)} پرفروش‌ترین آیتم‌ها</h3>
    ${d.top.length?d.top.map((x,i)=>`<div class="trow"><b class="tr-i">${fa(i+1)}</b><span class="tr-n">${esc(x.name)}</span><span class="tr-bar"><i style="width:${Math.round(x.q/topMax*100)}%"></i></span><b class="tr-v">${fa(x.q)}</b></div>`).join(''):'<p class="dim">هنوز سفارشی ثبت نشده.</p>'}</section>
   <section class="rbox wide"><h3>${ic('receipt',15)} سفارش‌های اخیر</h3>
    ${d.recent.map(o=>`<div class="rl"><b>#${fa(o.num)}</b><span>${tf.format(o.ts)} · ${o.items.reduce((s,i)=>s+i.qty,0)} قلم · ${methodLabel(o.method)}${o.tableNo?' · میز '+fa(o.tableNo):''}</span><b>${faM(o.total)}</b></div>`).join('')||'<p class="dim">—</p>'}</section>
  </div></div>`;
}

/* ── باشگاه مشتریان (v5.0.4-test.5) — فهرست اعضا + خروجی اکسل برای پنل پیامکی ──
   v5.0.4-test.6: شمارهٔ اشتراک (۱..۵ رقم) در هر ردیف + جستجو با شمارهٔ اشتراک + لوکیشن */
function custListHTML(){
 const q=en(App.adm.cq||'').trim().toLowerCase();
 const stats={};
 (Store.db.orders||[]).forEach(o=>{const p=String(o.phone||'');if(!p)return;
  const s=stats[p]||(stats[p]={n:0,sum:0});s.n++;s.sum+=(o.total||0);});
 let list=(Store.db.customers||[]).map(c=>({...c,_n:(stats[c.phone]||{n:0}).n,_sum:(stats[c.phone]||{sum:0}).sum}));
 if(q)list=list.filter(c=>String(c.name||'').toLowerCase().includes(q)||String(c.phone||'').includes(q)||String(c.sub_no||'').includes(q)||fa(c.sub_no||0).includes(q));
 list.sort((a,b)=>(b.points||0)-(a.points||0)||(b._n||0)-(a._n||0));
 if(!list.length)return `<div class="empty-big">${ic('award',30)}<p>عضوی پیدا نشد</p><small>${q?'برای این جستجو نتیجه‌ای نیست.':'با اولین سفارشی که مشتری شمارهٔ موبایلش را وارد کند، عضو باشگاه می‌شود.'}</small></div>`;
 const dtfC=new Intl.DateTimeFormat('fa-IR',{year:'numeric',month:'short',day:'numeric'});
 return `<div class="cust-l">${list.map(c=>{const lv=levelOf(c.points||0);
   const loc=String(c.loc||'');
   return `<div class="cust-r"><div class="cr-a"><span class="cr-no" title="شمارهٔ اشتراک">${fa(c.sub_no||0)}</span><b>${esc(c.name||'بی‌نام')}</b><span class="tagpill">${esc(lv.n)}</span></div>
    <span class="cr-p" dir="ltr">${esc(String(c.phone||''))}</span>
    <span class="mini">${c.since?dtfC.format(c.since):''}${c._n?` · ${fa(c._n)} سفارش · ${faM(c._sum)} تومان خرید`:'· هنوز سفارشی ثبت نشده'}${loc?`<br>${ic('pin',11)} ${esc(loc.length>46?loc.slice(0,46)+'…':loc)}`:''}</span>
    <b class="cr-pt">${fa(c.points||0)} <i>امتیاز</i></b></div>`;}).join('')}</div>`;
}
function customersHTML(){
 const cs=Store.db.customers||[];
 const pts=cs.reduce((s,c)=>s+(c.points||0),0);
 const kpiS=(l,v,u)=>`<div class="kpi"><small>${l}</small><b>${v}${u?` <i>${u}</i>`:''}</b></div>`;
 /* v5.0.5-test.5: کارت پنل پیامکی */
 const sm=(Store.db.sms&&Store.db.sms.cfg)?Store.db.sms.cfg:(Store.db.sms||{});
 const det=[];
 if(sm.st&&sm.st.lastTest) det.push((sm.st.lastTest.ok?'✅ ':'⚠️ ')+esc(sm.st.lastTest.msg));
 if(sm.st&&sm.st.lastSync) det.push('آخرین همگام‌سازی: '+tf.format(sm.st.lastSync));
 if(sm.st&&(sm.st.ok||sm.st.fail)) det.push('مجموع: '+fa(sm.st.ok||0)+' موفق · '+fa(sm.st.fail||0)+' ناموفق'+(sm.st.sent?' · '+fa(sm.st.sent)+' پیامک خوش‌آمد':''));
 const smsErr=(sm.st&&sm.st.errs&&sm.st.errs.length)
   ?`<details style="margin-top:6px"><summary class="mini" style="cursor:pointer">⚠ ${fa(sm.st.errs.length)} خطای اخیر</summary>
     ${sm.st.errs.map(e=>`<p class="mini" style="margin:2px 0" dir="ltr">${esc(e.m||'')}</p>`).join('')}</details>`:'';
 return `<div class="rwrap">
  <div class="r-head"><h2>باشگاه مشتریان</h2>
   <button class="btn sm" data-act="cust-xls">${ic('dl',14)} دانلود اکسل مشتریان</button></div>
  <div class="kpis k3">
   ${kpiS('اعضای باشگاه',fa(cs.length),'نفر')}
   ${kpiS('امتیازهای باقیماندهٔ اعضا',fa(pts),'')}
   ${kpiS('ارزش هر امتیاز هنگام خرید',faM(PT().value),'تومان')}</div>
  <section class="rbox" style="margin-bottom:14px"><h3>${ic('send',15)} اتصال مستقیم به پنل پیامکی (API)</h3>
   <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
    ${sm.en?`<span class="tagpill">متصل به ${esc(sm.provFa||'پنل پیامکی')}</span>`:`<span class="tagpill">پنل پیامکی وصل نیست</span>`}
    ${sm.en&&sm.wel?`<span class="tagpill">خوش‌آمد خودکار</span>`:''}
    ${(sm.q||0)>0?`<span class="tagpill">${fa(sm.q)} عضو در صف همگام‌سازی</span>`:''}</div>
   ${det.length?det.map(d=>`<p class="mini" style="margin:2px 0">${d}</p>`).join(''):`<p class="mini">با اتصال API، عضوهای تازه به‌صورت خودکار به پنل پیامکی شما می‌روند و پیامک گروهی و خوش‌آمد خودکار از همین‌جا ارسال می‌شود.</p>`}
   ${smsErr}
   <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
    <button class="btn sm" data-act="sms-cfg">${ic('sliders',13)} تنظیمات پنل پیامکی</button>
    <button class="btn sm" data-act="sms-sync">${ic('refresh',13)} همگام‌سازی</button>
    ${sm.en&&sm.prov==='hook'?`<button class="btn sm" data-act="sms-sync-all">${ic('package',13)} همگام‌سازی کامل</button>`:''}
    <button class="btn sm" data-act="sms-bulk">${ic('send',13)} پیامک گروهی</button></div></section>
  <section class="rbox" style="margin-bottom:14px"><h3>${ic('dl',15)} انتقال با خروجی اکسل (همهٔ پنل‌ها)</h3>
   <p class="mini">فایل اکسل، ستون‌های «شماره اشتراک · نام · موبایل · امتیاز · تاریخ عضویت · تعداد سفارش · مجموع خرید · لوکیشن/آدرس» را دارد و شماره‌ها به‌صورت متن ذخیره می‌شوند تا صفرِ اولشان حذف نشود — همین فایل را می‌توانید مستقیم در دفترچهٔ تلفن پنل‌های پیامکی (کاوه‌نگار، ملی‌پیامک، SMS.ir و…) ایمپورت کنید.</p>
   <p class="mini">هر عضو یک «شمارهٔ اشتراک» یکتای ۱ تا ۵ رقمی می‌گیرد (به‌ترتیب عضویت) — در کارت باشگاه مشتری، روی سفارش‌ها و رسیدها دیده می‌شود و برای سفارش تلفنی هم کافی است مشتری همان شماره را بگوید. لوکیشن/آدرس هم اختیاری است: مشتری هنگام سفارش از راه دور می‌نویسد و آخرین نسخه‌اش همین‌جا کنار پروندهٔ او می‌ماند.</p></section>
  <div class="mn-search" style="max-width:420px;margin-bottom:12px">${ic('search',16)}<input data-input="cust-q" placeholder="جستجو: نام، موبایل یا شمارهٔ اشتراک…" value="${esc(App.adm.cq||'')}"></div>
  <div id="cust-body">${custListHTML()}</div></div>`;
}
