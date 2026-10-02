'use strict';
/* ════════════════════════════════════════════════════════════
   staff.js — صندوق + آشپزخانه (KDS)
   ════════════════════════════════════════════════════════════ */

function cashierHTML(){
 const t=App.csh.tab;
 const tabs=`<button class="atab ${t==='orders'?'on':''}" data-act="csh-tab" data-tab="orders">${ic('package',15)} سفارش‌ها</button>
  <button class="atab ${t==='tables'?'on':''}" data-act="csh-tab" data-tab="tables">${ic('table',15)} میزها</button>
  <button class="atab ${t==='invoices'?'on':''}" data-act="csh-tab" data-tab="invoices">${ic('send',15)} فاکتورها</button>`;
 return appbar('صندوق',M().brand.name,tabs)+(t==='orders'?cashOrdersHTML():t==='tables'?cashTablesHTML():cashInvoicesHTML());
}
function shiftBarHTML(){
 const sh=Store.db.shift;
 if(!sh)return `<div class="shiftbar">${ic('clock',15)}<span>شیفت بسته است.</span>
  <button class="btn sm" data-act="shift-open" style="margin-inline-start:auto">${ic('check',13)} افتتاح شیفت</button></div>`;
 const sales=Store.db.orders.filter(o=>o.ts>=sh.ts).reduce((s,o)=>s+o.total,0);
 return `<div class="shiftbar">${ic('clock',15)}<span>شیفت: <b>${esc(sh.name)}</b> · از ${tf.format(sh.ts)}</span><span>فروش تا اینجا: <b>${faM(sales)}</b> تومان</span>
  <button class="btn ghost sm" data-act="shift-x" style="margin-inline-start:auto">${ic('receipt',13)} گزارش و بستن</button></div>`;
}
function shiftReport(){
 const sh=Store.db.shift;if(!sh)return;
 const os=Store.db.orders.filter(o=>o.ts>=sh.ts);
 const by={cash:0,card:0,online:0};os.forEach(o=>by[o.method]=(by[o.method]||0)+o.total);
 const vat=os.reduce((s,o)=>s+(o.vat||0),0),tip=os.reduce((s,o)=>s+(o.tip||0),0),tot=os.reduce((s,o)=>s+o.total,0);
 return `<p class="mini" style="margin-bottom:8px">گزارش شیفت <b>${esc(sh.name)}</b> — از ${tf.format(sh.ts)}</p>
  <div class="hrow"><b>تعداد سفارش:</b><span></span><b>${fa(os.length)}</b></div>
  <div class="hrow"><b>جمع کل پرداختی:</b><span></span><b>${faM(tot)}</b></div>
  ${['cash','card','online'].map(m=>`<div class="hrow"><b>${methodLabel(m)}:</b><span></span><b>${faM(by[m])}</b></div>`).join('')}
  <div class="hrow"><b>جمع مالیات:</b><span></span><b>${faM(vat)}</b></div>
  <div class="hrow"><b>جمع انعام:</b><span></span><b>${faM(tip)}</b></div>
  <p class="mini" style="margin-top:8px">«بستن شیفت» این اعداد را نهایی می‌کند و صندوق برای شیفت بعد آماده می‌شود.</p>`;
}
function cashOrdersHTML(){
 return `<div class="cwrap-c">${shiftBarHTML()}
  <div class="csh-tools">
   <div class="seg">
    <button class="${App.csh.f==='open'?'on':''}" data-act="csh-filter" data-f="open">جاری</button>
    <button class="${App.csh.f==='today'?'on':''}" data-act="csh-filter" data-f="today">امروز</button>
    <button class="${App.csh.f==='all'?'on':''}" data-act="csh-filter" data-f="all">همه</button></div>
   <button class="btn sm" data-act="walkin" style="margin-inline-start:auto">${ic('plus',14)} فروش حضوری</button></div>
  <div id="csh-orders">${ordersListHTML()}</div></div>`;
}
function ordersListHTML(){
 const f=App.csh.f;
 let os=[...Store.db.orders].sort((a,b)=>b.ts-a.ts);
 if(f==='open')os=os.filter(o=>o.status!=='done');
 if(f==='today')os=os.filter(o=>o.ts>=sod(Date.now()));
 os=os.slice(0,40);
 if(!os.length)return `<div class="empty-big">${ic('package',34)}<p>سفارشی در این فهرست نیست</p><small>سفارش‌های آنلاین مشتری‌ها همین‌جا، زنده ظاهر می‌شوند.</small></div>`;
 const kds=SET().kds;
 return os.map(o=>{
  const st=ST[o.status]||ST.pending;
  const pill=o.status==='preparing'?((o.ready&&kds)?`<span class="pill ok">آمادهٔ تحویل</span>`:`<span class="pill info">${kds?'در حال آماده‌سازی':'در حال انجام'}</span>`):`<span class="pill ${st.c}">${st.t}</span>`;
  return `<div class="ord ${o.status==='pending'?'urgn':''}">
   <div class="ord-h"><b class="ord-no">#${fa(o.num)}</b>
    <span class="ord-meta">${tf.format(o.ts)}${o.cashier?' · '+esc(o.cashier):''}${o.tableNo?' · میز '+fa(o.tableNo):''}${o.custNo?' · کارت '+fa(o.custNo):''}</span>${pill}</div>
   <ul class="ord-it">${o.items.map(i=>`<li><b>${fa(i.qty)}×</b>${esc(i.name)}${(i.opts&&i.opts.length)?` <small class="dim">(${esc(i.opts.map(x=>x.name).join('، '))})</small>`:''}</li>`).join('')}</ul>
   ${o.loc?`<div class="ord-loc">${ic('pin',12)} لوکیشن: ${esc(o.loc)}</div>`:''}
   ${o.tipNote?`<div class="ord-tip">${ic('gift',12)} انعام ${(o.tip?faM(o.tip):'')+' تومان'} · توضیح: ${esc(o.tipNote)}</div>`:''}
   <div class="ord-f"><div><b>${faM(o.total)}</b> <small>تومان · ${methodLabel(o.method)}</small>${o.method==='online'?` <span class="tagpill ok">${ic('check',11)} پرداخت آنلاین تأیید شد</span>`:''}</div>
   <div class="ord-ops">
    ${o.status==='pending'&&o.method==='counter'?`<button class="btn sm" data-act="take-pay" data-id="${o.id}">${ic('cash',14)} دریافت وجه</button>`:''}
    ${o.status==='preparing'?`<button class="btn sm" data-act="deliver" data-id="${o.id}">${ic('check',14)} تحویل شد</button>`:''}
    ${o.status!=='pending'?`<button class="icobtn" data-act="split-open" data-id="${o.id}" title="تقسیم حساب">${ic('split',16)}</button>`:''}
    <button class="icobtn" data-act="receipt" data-id="${o.id}" title="پرینت رسید">${ic('printer',16)}</button>
    <button class="icobtn" data-act="invoice" data-id="${o.id}" title="ارسال فاکتور">${ic('send',16)}</button></div></div></div>`;
 }).join('');
}
function cashTablesHTML(){
 const ts=Store.db.tables;
 return `<div class="cwrap-c">${sumRowHTML()}
  ${ts.length?`<div class="tbl-grid">${ts.map(tcardHTML).join('')}</div>`:`<p class="dim">هنوز میزی تعریف نشده.</p>`}
  ${resvListHTML(true)}</div>`;
}
function cashInvoicesHTML(){
 const inv=[...Store.db.invoices].sort((a,b)=>b.ts-a.ts);
 return `<div class="cwrap-c">
  <div class="csh-tools"><h2 style="font-size:18px;font-weight:900;flex:1">فاکتورهای ارسالی</h2>
   <button class="btn sm" data-act="invoice-new">${ic('send',14)} فاکتور جدید</button></div>
  ${inv.length?inv.map(v=>`<div class="hrow"><b>#${fa(v.num)}</b><span>${dtfD.format(v.ts)} · ${tf.format(v.ts)} · پیامک به ${v.phone}</span><b>${faM(v.amount)}</b></div>`).join(''):`<div class="empty-big">${ic('send',34)}<p>هنوز فاکتوری ارسال نشده</p></div>`}</div>`;
}
function openWalkin(){
 App.csh.sale={q:'',lines:[],method:'cash',phone:'',table:null};
 ui.modal(walkinBodyHTML(),{title:'فروش حضوری'});
}
function wkGridHTML(){
 const q=en(App.csh.sale.q).trim();
 const items=allItems().filter(i=>!q||i.name.includes(q)||String(i.price).includes(q));
 return items.map(i=>`<button class="wk-it" data-act="wk-add" data-id="${i.id}"><b>${esc(i.name)}</b><span>${faM(effPrice(i))}</span></button>`).join('')||'<p class="dim" style="grid-column:1/-1">چیزی یافت نشد.</p>';
}
function walkinBodyHTML(){
 const s=App.csh.sale;
 const total=s.lines.reduce((t,l)=>t+l.price*l.qty,0);
 const vat=SET().vatOn?Math.round(total*SET().vatPct/100):0;
 const freeT=Store.db.tables.filter(t=>t.status==='free'||t.id===s.table);
 return `<div class="wk">
  <div class="wk-search">${ic('search',16)}<input data-input="wk-q" placeholder="جستجوی نام یا قیمت…" value="${esc(s.q)}"></div>
  <div class="wk-grid">${wkGridHTML()}</div>
  <div class="wk-cart">${s.lines.length?s.lines.map(l=>`<div class="wk-line"><span>${esc(l.name)}</span>
   <span class="qstep"><button data-act="wk-dec" data-id="${l.id}">${ic('minus',12)}</button><b>${fa(l.qty)}</b><button data-act="wk-inc" data-id="${l.id}">${ic('plus',12)}</button></span>
   <b>${faM(l.price*l.qty)}</b></div>`).join(''):'<p class="dim">از فهرست بالا آیتم انتخاب کنید.</p>'}</div>
  <div><p class="dim" style="font-size:11px;font-weight:800;margin-bottom:6px">میز (اختیاری)</p><div class="wk-tbls">
   ${freeT.length?freeT.map(t=>`<button class="wtb ${s.table===t.id?'on':''}" data-act="wk-table" data-id="${t.id}">میز ${fa(t.no)}</button>`).join(''):'<span class="dim">میز خالی نداریم</span>'}</div></div>
  <input data-input="wk-phone" inputmode="numeric" placeholder="موبایل مشتری (اختیاری — ثبت امتیاز باشگاه)" value="${esc(s.phone)}">
  <div class="seg">
   <button class="${s.method==='cash'?'on':''}" data-act="wk-method" data-m="cash">${ic('cash',14)} نقدی</button>
   <button class="${s.method==='card'?'on':''}" data-act="wk-method" data-m="card">${ic('card',14)} کارتخوان</button></div>
  <div class="wk-total"><span>قابل پرداخت${vat?' (با مالیات)':''}</span><b>${faM(total+vat)} تومان</b></div>
  <button class="btn blk" data-act="wk-done" ${s.lines.length?'':'disabled'}>${ic('receipt',15)} ثبت فروش و صدور رسید</button></div>`;
}

/* ── آشپزخانه (KDS) ── */
function kdsHTML(){
 return appbar('آشپزخانه',M().brand.name,'')+
 `<div class="kd-wrap"><div id="kds-list">${kdsListHTML()}</div></div>`;
}
function kdsListHTML(){
 const prep=Store.db.orders.filter(o=>o.status==='preparing').sort((a,b)=>(a.kts||a.ts)-(b.kts||b.ts));
 const cook=prep.filter(o=>!o.ready),ready=prep.filter(o=>o.ready);
 if(!prep.length)return `<div class="empty-big">${ic('chefhat',34)}<p>سفارشی در آشپزخانه نیست</p><small>سفارش‌های پرداخت‌شده همین‌جا، زنده ظاهر می‌شوند.</small></div>`;
 const card=o=>{
  const late=!o.ready&&(Date.now()-(o.kts||o.ts))>10*60000;
  return `<div class="kd-card ${o.ready?'ready':''}">
   <div class="kd-top"><b class="kd-no">#${fa(o.num)}${o.tableNo?' · میز '+fa(o.tableNo):''}</b>
    <span class="kd-time ${late?'late':''}" data-id="${o.id}">${fmtDur(Date.now()-(o.kts||o.ts))}</span></div>
   <div class="kd-items">${o.items.map(i=>`<div><b>${fa(i.qty)}×</b> ${esc(i.name)}${(i.opts&&i.opts.length)?` <small>(${esc(i.opts.map(x=>x.name).join('، '))})</small>`:''}</div>`).join('')}</div>
   ${o.ready?`<p class="mini">منتظر تحویل توسط صندوق…</p>`
    :`<button class="btn sm blk" data-act="kds-ready" data-id="${o.id}">${ic('check',13)} آماده شد</button>`}</div>`;
 };
 return `${cook.length?`<div class="kd-h">${ic('flame',15)} در حال آماده‌سازی (${fa(cook.length)})</div><div class="kd-grid">${cook.map(card).join('')}</div>`:''}
  ${ready.length?`<div class="kd-h">${ic('check',15)} آماده — در انتظار تحویل (${fa(ready.length)})</div><div class="kd-grid">${ready.map(card).join('')}</div>`:''}`;
}
