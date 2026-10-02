'use strict';
/* ════════════════════════════════════════════════════════════
   customer.js — نقش مشتری: منو، سبد، پرداخت، باشگاه
   ════════════════════════════════════════════════════════════ */

const activeOrder=()=>Store.db.orders.filter(o=>o.source==='customer'&&o.status!=='done').sort((a,b)=>b.ts-a.ts)[0]||null;
function customerHTML(){
 const t=App.cst.tab,a=activeOrder(),n=Store.cart.reduce((s,c)=>s+c.qty,0);
 const nb=(App.cst.nb||[]).length;
 const tabBtn=(id,icn,lab,badge)=>`<button class="tab ${t===id?'on':''}" data-act="ctab" data-tab="${id}">${ic(icn,20)}<span>${lab}</span>${badge?`<i class="tb-badge">${badge}</i>`:''}</button>`;
 return `<div class="cust-shell">
  <button class="icobtn exit-fab" data-act="goHome" title="بازگشت به نقش‌ها">${ic('logout',16)}</button>
  <div id="cst-top">
   ${App.cst.tableNo?`<div class="tbl-chip">${ic('table',15)} سفارش برای میز ${fa(App.cst.tableNo)}<button data-act="tbl-clear">حذف</button></div>`:''}
   ${a?activeStripHTML(a):''}</div>
  <div id="cst-view">${custView(t)}</div>
  <div id="cb-slot">${t==='menu'?cartBarHTML():''}</div>
  <nav class="tabbar"><div class="inner">
   ${tabBtn('menu','coffee','منو')}${tabBtn('cart','cart','سبد',n?fa(n):'')}${tabBtn('reserve','cal','رزرو')}${tabBtn('account','award','حساب من',nb?fa(nb):'')}</div></nav></div>`;
}
function activeStripHTML(o){
 const st=ST[o.status]||ST.pending;
 return `<button class="astrip" data-act="ctab" data-tab="account"><span class="dot ${o.status}"></span><span>سفارش <b>#${fa(o.num)}</b> · ${(o.ready&&o.status==='preparing'&&SET().kds)?'آماده!':st.t}</span>${ic('arrow',14)}</button>`;
}
function custView(t){
 if(t==='menu')return menuFrameHTML(false);
 if(t==='cart')return cartFlowHTML();
 if(t==='reserve')return reserveHTML();
 return accountHTML();
}
function cartBarHTML(){
 const n=Store.cart.reduce((s,c)=>s+c.qty,0);
 const sub=Store.cart.reduce((s,c)=>{const it=findItem(c.id);return s+(it?(effPrice(it)+optSum({item:it,opts:c.opts||[]}))*c.qty:0);},0);
 if(!n)return '';
 return `<button class="cartbar" data-act="goto-cart"><span class="cb-badge">${fa(n)}</span><span>مشاهدهٔ سبد</span><b>${faM(sub)} تومان</b></button>`;
}
function cartFlowHTML(){
 const f=App.cst.flow;
 if(f==='pay')return payHTML();
 if(f==='done'&&App.cst.done)return doneHTML(App.cst.done);
 return cartHTML();
}
function sumBoxHTML(t){
 return `<div class="sum" id="sum-box">
  ${t.promoAmt?`<div><span>کد تخفیف (${esc(App.cst.form.promo.code)}) — ٪${fa(App.cst.form.promo.off)}</span><b class="minus">−${faM(t.promoAmt)}</b></div>`:''}
  ${t.hhAmt?`<div><span>ساعت خوش (٪${fa(SET().hh.off)})</span><b class="minus">−${faM(t.hhAmt)}</b></div>`:''}
  ${t.ptsAmt?`<div><span>تخفیف امتیازها (${fa(t.ptsMax)} امتیاز)</span><b class="minus">−${faM(t.ptsAmt)}</b></div>`:''}
  ${t.vat?`<div><span>مالیات بر ارزش افزوده (٪${fa(SET().vatPct)})</span><b>+${faM(t.vat)}</b></div>`:''}
  ${t.tip?`<div><span>انعام${App.cst.form.tipFix>0?' (مبلغ دلخواه)':' (٪'+fa(App.cst.form.tip||0)+')'}</span><b>+${faM(t.tip)}</b></div>`:''}
  <div class="tot"><span>قابل پرداخت</span><b>${faM(t.total)} تومان</b></div></div>`;
}
function cartHTML(){
 const t=totals(),f=App.cst.form;
 if(!t.lines.length)return `<div class="cwrap"><div class="empty-big">${ic('cart',36)}<p>سبد خرید خالی است</p><small>از منو چیزی انتخاب کنید تا اینجا بیاید.</small><button class="btn sm" data-act="ctab" data-tab="menu">${ic('coffee',14)} رفتن به منو</button></div></div>`;
 return `<div class="cwrap"><h2>سبد خرید</h2>
  ${t.lines.map(l=>{const ep=effPrice(l.item)+optSum(l);const off=l.item.tags&&l.item.tags.off;const ol=(l.opts||[]).map(oid=>{const o=(l.item.opts||[]).find(x=>x&&x.id===oid);return o?o.name:null;}).filter(Boolean);
   return `<div class="cline">
   <div class="cl-t"><b>${esc(l.item.name)}</b><span>${off?`<s style="color:var(--ink3)">${faM(l.item.price)}</s> `:''}${faM(effPrice(l.item))} تومان${ol.length?` · ${esc(ol.join('، '))}`:''}</span></div>
   <div class="cl-b"><span class="qstep"><button data-act="cart-dec" data-k="${l.k}">${ic('minus',13)}</button><b>${fa(l.qty)}</b><button data-act="cart-inc" data-k="${l.k}">${ic('plus',13)}</button></span><b>${faM(ep*l.qty)}</b></div></div>`;}).join('')}
  <div class="co-form"><h3>${ic('user',14)} مشتری</h3>
   <label class="f"><span>نام و نام خانوادگی</span><input data-input="co-name" value="${esc(f.name)}" placeholder="برای شناخته‌شدن در باشگاه"></label>
   <label class="f"><span>شمارهٔ موبایل</span><input data-input="co-phone" inputmode="numeric" value="${esc(f.phone)}" placeholder="۰۹۱۲ · · · · · · ·"></label>
   <label class="f"><span>لوکیشن یا آدرس (اختیاری)</span><input data-input="co-loc" value="${esc(f.loc||'')}" placeholder="برای سفارش از راه دور — نشانی یا لینک نقشه"></label>
   <p class="ok-line" id="mb-line">${t.mb?`<b style="color:var(--ok)">${esc(t.mb.name)}</b>&nbsp;· عضو ${levelOf(t.mb.points).n} · ${fa(t.mb.points)} امتیاز`:''}</p>
   <div class="promoline">${f.promo
     ?`<span class="pchip">${ic('check',13)} کد ${esc(f.promo.code)} — ٪${fa(f.promo.off)}<button data-act="promo-clear">${ic('x',12)}</button></span>`
     :`<input id="promo-inp" placeholder="کد تخفیف دارید؟" value="${esc(App.cst.promoDraft||'')}"><button class="btn sm ghost" data-act="promo-apply">اعمال</button>`}</div>
   ${t.ptsMax?`<label class="usepts"><input type="checkbox" data-input="use-pts" ${f.usePts?'checked':''}> استفاده از ${fa(t.ptsMax)} امتیاز — ${faM(t.ptsMax*PT().value)} تومان تخفیف</label>`:''}
   ${f.err?`<p class="err">${f.err}</p>`:''}</div>
  ${sumBoxHTML(t)}
  <button class="btn blk" data-act="to-pay">ادامه و پرداخت ${ic('arrow',16)}</button></div>`;
}
/* پرداخت — v5.0.5-test.1: مشتری فقط «درگاه آنلاین» دارد؛ روش‌های نقدی/کارتخوان
   فقط در صندوق (فروش حضوری) انتخاب می‌شوند. انعام اختیاری سر جایش است. */
function payHTML(){
 const t=totals(),f=App.cst.form,S=SET();
 return `<div class="cwrap"><h2>پرداخت آنلاین</h2>
  <div class="pay-amt" id="pay-amt"><span>مبلغ قابل پرداخت</span><b>${faM(t.total)} تومان</b></div>
  ${S.tipOn?`<div class="tips">
   <div class="tips-h"><span>${ic('gift',15)} انعام برای کارکنان کافه (کاملاً اختیاری)</span><small>کل مبلغ انعام به کارکنان می‌رسد — می‌توانید درصد یا مبلغ دلخواه بدهید و برایش توضیح بنویسید.</small></div>
   <div class="seg">${[0,5,10,15].map(v=>`<button class="${!(f.tipFix>0)&&(f.tip||0)===v?'on':''}" data-act="tip-set" data-v="${v}">${v?'٪'+fa(v):'بدون'}</button>`).join('')}</div>
   <div class="tip-fix"><span>یا مبلغ دلخواه:</span><input data-input="tip-fix" inputmode="numeric" maxlength="12" placeholder="مثلاً ۵۰٬۰۰۰" value="${f.tipFix>0?faM(f.tipFix):''}"><i>تومان</i></div>
   <label class="f tip-note"><span>توضیح انعام (اختیاری)</span><input data-input="tip-note" maxlength="140" placeholder="مثلاً: برای گارسون‌ها / برای آشپز" value="${esc(f.tipNote||'')}"></label>
  </div>`:''}
  <div class="gw"><div class="gw-head"><span class="gw-secure">${ic('check',13)} پرداخت امن (شبیه‌سازی)</span><span>پذیرنده: ${esc(M().brand.name)}</span></div>
   <div class="gw-body">
    <label class="f"><span>شمارهٔ کارت</span><input data-input="gw-card" inputmode="numeric" value="${esc(f.card)}" dir="ltr" style="text-align:center;letter-spacing:.12em;font-feature-settings:'tnum'"></label>
    <label class="f" style="margin-bottom:4px"><span>رمز پویا</span><input type="password" value="۱۰۰۰۱۲" readonly dir="ltr" style="text-align:center"></label></div>
   <button class="btn blk" data-act="pay-go" style="margin:0 14px;width:calc(100% - 28px)">پرداخت ${faM(t.total)} تومان</button>
   <p class="gw-note">کارت آزمایشی دمو — هیچ وجه واقعی جابه‌جا نمی‌شود. پس از پرداخت، سفارش فوراً به صندوق و آشپزخانه می‌رود.</p></div>
  <button class="btn ghost blk" style="margin-top:10px" data-act="back-cart">بازگشت به سبد</button></div>`;
}
function doneHTML(o){
 const eta=prepETA();
 return `<div class="done">
  <svg class="tick" viewBox="0 0 52 52"><circle class="t-c" cx="26" cy="26" r="24"/><path class="t-k" d="M15 27l7.5 7.5L37 19"/></svg>
  <h2>سفارش شما ثبت شد</h2>
  <p>کد سفارش <b>#${fa(o.num)}</b>${o.tableNo?' · میز '+fa(o.tableNo):''}<br>${o.method==='counter'?'کد را به صندوق بگویید و وجه را آنجا پرداخت کنید.':'وجه پرداخت شد — سفارش در حال آماده‌سازی است.'}
  ${o.subno?`<br>شمارهٔ اشتراک شما در باشگاه: <b>${fa(o.subno)}</b>`:''}
  ${eta?`<br>میانگین آماده‌سازی اینجا: <b>~${fa(eta)} دقیقه</b>`:''}</p>
  ${o.earned?`<div class="pts-gain">+<span id="pts-up" data-v="${o.earned}">۰</span> امتیاز در باشگاه مشتریان</div>`:''}
  <div class="done-acts">
   <button class="btn blk" data-act="back-menu">بازگشت به منو</button>
   <button class="btn ghost blk" data-act="ctab" data-tab="account">پیگیری سفارش</button></div></div>`;
}
function timelineHTML(o){
 const kds=SET().kds,eta=prepETA();
 const steps=kds
  ?[['ثبت',true],['پرداخت',o.status!=='pending'],['آماده‌سازی',o.status!=='pending'],['آماده',!!o.ready],['تحویل',o.status==='done']]
  :[['ثبت',true],['پرداخت',o.status!=='pending'],['تحویل',o.status==='done']];
 let actSet=false;
 const tl=`<div class="tl">${steps.map(([l,d])=>{
  let cls=d?'done':'';if(!d&&!actSet){cls='act';actSet=true;}
  return `<div class="tl-s ${cls}">${l}</div>`;}).join('')}</div>`;
 const etaLine=(kds&&eta&&o.status==='preparing'&&!o.ready)
  ?`<p class="etah">${ic('clock',13)} حدوداً ${fa(eta)} دقیقهٔ دیگر آماده می‌شود</p>`:'';
 return tl+etaLine;
}
/* ── v5.0.5-test.1: رزرو میز — درخواست مشتری → تأیید صندوق ── */
function reserveHTML(){
 const f=App.cst.rs,ok=f.ok;
 const dtfR=new Intl.DateTimeFormat('fa-IR',{weekday:'long',day:'numeric',month:'long'});
 const stT={pending:'در انتظار تأیید کافه',active:'تأیید شد ✓',cancel:'لغو شد',noshow:'عدم حضور'};
 const mine=(App.cst.nb_rs||[]).slice(0,6);
 return `<div class="cwrap"><h2>${ic('cal',20)} رزرو میز</h2>
  ${ok?`<div class="done" style="padding:10px 0 4px">
   <svg class="tick" viewBox="0 0 52 52"><circle class="t-c" cx="26" cy="26" r="24"/><path class="t-k" d="M15 27l7.5 7.5L37 19"/></svg>
   <h2>درخواست رزرو ثبت شد</h2>
   <p>${dtfR.format(ok.ts)} · ساعت ${faTime(ok.time)} · ${fa(ok.persons)} نفر<br>${stT[ok.status]||''}</p>
   <div class="done-acts"><button class="btn ghost blk" data-act="rs-again">ثبت رزرو دیگر</button></div></div>`
 :`<div class="co-form">
   <p class="mini" style="margin-bottom:10px">فرم را پر کنید — کافه رزرو شما را تأیید می‌کند و نتیجه در «حساب من» و پیام‌ها می‌آید.</p>
   <label class="f"><span>نام و نام خانوادگی</span><input data-input="rs-name" value="${esc(f.name)}" placeholder="نام رزروکننده"></label>
   <label class="f"><span>شمارهٔ موبایل</span><input data-input="rs-phone" inputmode="numeric" value="${esc(f.phone)}" placeholder="۰۹۱۲ · · · · · · ·"></label>
   <div style="display:flex;gap:10px">
    <label class="f" style="flex:1"><span>تاریخ</span><input data-input="rs-date" type="date" dir="ltr" min="${new Date().toISOString().slice(0,10)}" value="${esc(f.date)}"></label>
    <label class="f" style="flex:1"><span>ساعت</span><input data-input="rs-time" type="time" dir="ltr" value="${esc(f.time)}"></label></div>
   <label class="f"><span>تعداد نفر</span><input data-input="rs-persons" inputmode="numeric" value="${esc(f.persons)}"></label>
   <label class="f"><span>توضیح (اختیاری)</span><input data-input="rs-note" maxlength="160" value="${esc(f.note)}" placeholder="مثلاً: کنار پنجره / تولد"></label>
   ${f.err?`<p class="err">${esc(f.err)}</p>`:''}
   <button class="btn blk" data-act="rs-send">${ic('cal',16)} ثبت درخواست رزرو</button></div>`}
  ${mine.length?`<div class="acct-sec" style="margin-top:18px"><h3>${ic('clock',15)} رزروهای من</h3>
   ${mine.map(r=>`<div class="hrow"><b>${tf.format(r.ts)}</b><span>${fa(r.persons)} نفر · ${stT[r.status]||r.status}${r.tableNo?' · میز '+fa(r.tableNo):''}</span></div>`).join('')}</div>`:''}
 </div>`;
}

function accountHTML(){
 const c=Store.cust;
 if(!c){
  const s=App.cst.lg;
  return `<div class="acct"><div class="login">${ic('award',34)}
   <h2>باشگاه مشتریان</h2>
   <p>با شمارهٔ موبایل وارد شوید؛ اگر عضو نباشید همان‌جا عضو می‌شوید و ۵ امتیاز هدیهٔ عضویت می‌گیرید.</p>
   <label class="f"><span>موبایل</span><input data-input="lg-phone" inputmode="numeric" value="${esc(s.phone)}" placeholder="۰۹۱۲ · · · · · · ·"></label>
   <label class="f"><span>نام (فقط برای عضویت جدید)</span><input data-input="lg-name" value="${esc(s.name)}" placeholder="نام و نام خانوادگی"></label>
   ${s.err?`<p class="err">${s.err}</p>`:''}
   <button class="btn blk" data-act="login">ورود / عضویت</button></div></div>`;
 }
 const p=c.points,lvl=levelOf(p);
 const next=LEVELS.find(L=>L.min>p);
 const pct=next?Math.round((p-lvl.min)/(next.min-lvl.min)*100):100;
 const hist=Store.db.orders.filter(o=>o.phone===c.phone).sort((a,b)=>b.ts-a.ts).slice(0,12);
 const a=activeOrder();
 return `<div class="acct">
  ${a?`<div class="co-form" style="margin:12px 0 0"><h3>${ic('clock',14)} پیگیری سفارش #${fa(a.num)}</h3>${timelineHTML(a)}</div>`:''}
  ${(App.cst.nb||[]).length?`<div class="acct-sec" style="margin:14px 0 0"><h3>${ic('send',15)} پیام‌های کافه</h3>
   ${App.cst.nb.slice(0,10).map(n=>`<div class="hrow"><span>${esc(n.title)}${n.body?' · '+esc(n.body):''}</span><small class="dim">${tf.format(n.ts)}</small></div>`).join('')}</div>`:''}
  <div class="lcard" style="--lc:${lvl.c};margin-top:20px">
   <div class="lc-top"><span>${ic('award',17)} باشگاه مشتریان ${esc(M().brand.name)}</span><span class="lc-lvl">${lvl.n}</span></div>
   <div class="lc-mid"><div><small>امتیاز فعلی</small><b>${fa(p)}</b></div><div><small>اعتبار امتیازها</small><b>${faM(p*PT().value)}</b><small>تومان تخفیف</small></div></div>
   <div class="lc-prog"><i style="width:${pct}%"></i></div>
   <div class="lc-next">${next?`${fa(next.min-p)} امتیاز تا سطح «${next.n}»`:'بالاترین سطح — دستت طلا!'}</div>
   <div class="lc-no">${c.sub_no?`شمارهٔ اشتراک: <b>${fa(c.sub_no)}</b> · `:''}${c.phone} · ${esc(c.name)}</div></div>
  <div class="acct-sec"><h3>${ic('receipt',15)} تاریخچهٔ سفارش‌ها</h3>
   ${hist.length?hist.map(o=>{const st=ST[o.status]||ST.pending;return `<div class="hrow"><b>#${fa(o.num)}</b>
    <span>${dtfD.format(o.ts)} · ${o.items.reduce((s,i)=>s+i.qty,0)} قلم · ${st.t}</span>
    ${o.earned?`<i class="hpts">+${fa(o.earned)}</i>`:''}<b>${faM(o.total)}</b>
    ${o.status==='done'?(o.rated?`<span class="stars">${[1,2,3,4,5].map(v=>starIc(v<=o.rated,13)).join('')}</span>`
      :`<span class="stars">${[1,2,3,4,5].map(v=>`<button data-act="rate" data-id="${o.id}" data-v="${v}" title="${fa(v)} ستاره">${starIc(false,15)}</button>`).join('')}</span>`):''}
    ${o.status==='done'?`<button class="icobtn xs" data-act="review-open" data-id="${o.id}" title="ثبت نظر روی آیتم‌ها">${ic('pen',13)}</button>`:''}
    <button class="btn ghost sm" data-act="reorder" data-id="${o.id}">${ic('refresh',12)} سفارش دوباره</button></div>`;}).join(''):'<p class="dim">هنوز سفارشی ثبت نکرده‌اید.</p>'}</div>
  <p class="dim" style="margin-top:16px">هر ${faM(PT().earn)} تومان خرید = ۱ امتیاز · هر امتیاز = ${faM(PT().value)} تومان تخفیف (تا سقف ${fa(PT().maxPct)}٪ صورت‌حساب)</p>
  <div class="acct-acts"><button class="btn ghost" data-act="logout-cust">${ic('logout',15)} خروج از حساب</button></div></div>`;
}
