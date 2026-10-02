'use strict';
/* ════════════════════════════════════════════════════════════
   menu-render.js — رندر منو (منبع واحد: طراح + مشتری)
   ════════════════════════════════════════════════════════════ */

function mnToolsHTML(){
 const q=App.cst.sq||'',f=App.cst.sf||'all';
 const chips=[['all','همه'],['off','تخفیف‌دار'],['popular','محبوب'],['chef','سرآشپز'],['daily','پیشنهاد روز'],['available','موجود']];
 return `<div class="mn-search">${ic('search',16)}<input data-input="mn-q" placeholder="جستجو در منو…" value="${esc(q)}"></div>
 <div class="mn-fchips">${chips.map(([k,l])=>`<button class="mnf ${f===k?'on':''}" data-act="mn-filter" data-f="${k}">${l}</button>`).join('')}</div>`;
}
const rvOf = id => { const rs=(Store.db.reviews||[]).filter(r=>r.itemId===id&&r.ok&&r.txt); return rs.length?rs[rs.length-1]:null; };
const rvqHTML = i => { const r=rvOf(i.id); return r?`<div class="rvq"><span class="rst">${'★'.repeat(r.stars)}</span><i>«${esc(r.txt)}» — ${esc(r.by)}</i></div>`:''; };

/* ── سازندهٔ HTML آیتم — سطح ماژول تا «بازسازی تک‌کارت» بدون رندر کل صفحه ممکن باشد ──
   v5.0.1: کلیک روی برچسب‌ها دیگر کل صفحه را رندر نمی‌کند (کارت باز می‌ماند)؛
   بستن حالت ویرایش برچسب‌ها فقط با دکمهٔ «تایید» خود کارت اتفاق می‌افتد. */
const designerEdit = () => window.App && App.role === 'admin' && !App.adm.live;

function mnTagEdit(i){
  const t=i.tags;
  const chip=(key,label,icn,col)=>`<button class="tagchip ${key==='off'?(t.off?'on':''):(t[key]?'on':'')}" style="--tc:${col}" data-act="tag-toggle" data-item="${i.id}" data-tag="${key}">${ic(icn,10)}${key==='off'&&t.off?'تخفیف ٪'+fa(t.off):label}</button>`;
  return `<div class="tagrow">${chip('popular','محبوب','flame','#A87412')}${chip('chef','سرآشپز','chefhat','#4F7A3D')}${chip('daily','پیشنهاد روز','sun','#5B7085')}${chip('off','تخفیف','percent','#A93B2A')}${chip('soldout','ناموجود','x','#837D70')}<button class="tagchip ok" data-act="tag-done" data-item="${i.id}" title="ثبت و بستن برچسب‌های این محصول">${ic('check',10)}تایید</button></div>`;
}
function mnTagShow(i){
  const t=i.tags;let s='';
  if(t.popular)s+=`<span class="tchip tp">${ic('flame',10)}محبوب</span>`;
  if(t.chef)s+=`<span class="tchip tc">${ic('chefhat',10)}سرآشپز</span>`;
  if(t.daily)s+=`<span class="tchip td">${ic('sun',10)}پیشنهاد روز</span>`;
  if(t.off)s+=`<span class="tchip to">${ic('percent',10)}٪${fa(t.off)} تخفیف</span>`;
  if(t.soldout)s+=`<span class="tchip ts">${ic('x',10)}ناموجود</span>`;
  return s?`<div class="tchips">${s}</div>`:'';
}
/* بعد از «تایید»: نمایش برچسب‌های فعال + دکمهٔ بازگشایی دوبارهٔ ویرایشگر همان کارت */
function mnTagClosed(i){
  return `<div class="tagrow"><button class="tagchip ghost" data-act="tag-open" data-item="${i.id}" title="ویرایش برچسب‌های این محصول">${ic('pen',10)}برچسب‌ها</button></div>${mnTagShow(i)}`;
}
function mnItemOps(id){
  return `<span class="iops">
  <button class="icobtn xs dragh" title="جابه‌جایی با کشیدن">${ic('list',12)}</button>
  <button class="icobtn xs" data-act="item-img" data-id="${id}">${ic('img',12)}</button>
  <button class="icobtn xs" data-act="item-info" data-id="${id}" title="آپشن، قیمت خرید، موجودی و نظرات">${ic('sliders',12)}</button>
  <button class="icobtn xs danger" data-act="item-del" data-id="${id}">${ic('trash',12)}</button></span>`;
}
function mnQtyCtrl(id, edit){
  const it=findItem(id);
  if(!edit&&it&&it.opts&&it.opts.length)return `<button class="qbtn" data-act="opt-open" data-id="${id}" aria-label="انتخاب گزینه‌ها">${ic('plus',15)}</button>`;
  if(it&&it.tags.soldout)return `<span class="sodp">ناموجود</span>`;
  const q=cartQty(id);
  const pl=Store.cart.find(c=>c.id===id&&!(c.opts&&c.opts.length));
  const k=pl?pl.k:'';
  return q===0?`<button class="qbtn" data-act="cart-add" data-id="${id}" aria-label="افزودن">${ic('plus',15)}</button>`
   :`<span class="qstep"><button data-act="cart-dec" data-k="${k}">${ic('minus',13)}</button><b>${fa(q)}</b><button data-act="cart-inc" data-k="${k}">${ic('plus',13)}</button></span>`;
}
function mnItemHTML(i, edit){
  const m=M(),T=m.theme;
  const grid=T.layout==='grid';
  const ce=(k,cat,item)=>edit?`contenteditable="true" data-ce="${k}"${cat?` data-cat="${cat}"`:''}${item?` data-item="${item}"`:''}`:'';
  const soldCls=(!edit&&i.tags.soldout)?'sold':'';
  const stockHint=(i.stock!=null&&!edit&&i.stock>0&&i.stock<=3)?`<span class="mini" style="color:#A93B2A">فقط ${fa(i.stock)} عدد باقی مانده</span>`:'';
  const optHint=(i.opts&&i.opts.length&&!edit)?`<span class="ophint">+ ${fa(i.opts.length)} گزینه</span>`:'';
  const closed=edit&&App.adm.tgClosed&&App.adm.tgClosed[i.id];
  const tags=edit?(closed?mnTagClosed(i):mnTagEdit(i)):mnTagShow(i);
  const priceHTML=editp=>{
   if(editp)return `<span ${ce('price',null,i.id)}>${faM(i.price)}</span><small>تومان</small>`;
   const ep=effPrice(i);
   return `${i.tags.off?`<s class="oldp">${faM(i.price)}</s>`:''}${faM(ep)}<small>تومان</small>`;
  };
  if(grid){
   const ph=`<div class="gimg">${i.img?`<img src="${i.img}" alt="${esc(i.name)}">`:`<span>${ic(edit?'img':venueIcon(),20)}</span>`}
    ${i.tags.off?`<span class="offbadge">٪${fa(i.tags.off)}−</span>`:''}
    ${edit?`<button class="gimg-btn" data-act="item-img" data-id="${i.id}">${ic(i.img?'refresh':'plus',13)}</button>${i.img?`<button class="gimg-btn x" data-act="item-img-del" data-id="${i.id}">${ic('x',12)}</button>`:''}`:''}</div>`;
   return `<article class="mgi ${soldCls}" id="it-${i.id}" data-id="${i.id}">${ph}<div class="mgi-b"><h3 ${ce('iname',null,i.id)}>${esc(i.name)}</h3><p ${ce('idesc',null,i.id)}>${esc(i.desc)}</p>${tags}${rvqHTML(i)}
    <div class="mgi-f"><span class="mn-price">${priceHTML(edit)}${optHint}</span>${edit?mnItemOps(i.id):`<span class="qslot" data-qid="${i.id}">${mnQtyCtrl(i.id,edit)}</span>`}</div></div></article>`;
  }
  return `<article class="mni ${soldCls}" id="it-${i.id}" data-id="${i.id}">
   <div class="mn-line"><h3 class="mn-iname" ${ce('iname',null,i.id)}>${esc(i.name)}</h3><span class="mn-lead"></span><span class="mn-price">${priceHTML(edit)} ${optHint}</span></div>
   <div class="mn-sub">${i.img?`<span class="thumb-w"><img class="lthumb" src="${i.img}" alt="">${edit?`<button class="gx" data-act="item-img-del" data-id="${i.id}">${ic('x',10)}</button>`:''}</span>`:''}
   <p class="mn-idesc" ${ce('idesc',null,i.id)}>${esc(i.desc)}</p>${edit?mnItemOps(i.id):`<span class="qslot" data-qid="${i.id}">${mnQtyCtrl(i.id,edit)}</span>`}</div>
   ${stockHint}${tags}${rvqHTML(i)}</article>`;
}
/* بازسازی فقطِ همین کارت محصول در پیش‌نمایش — بدون رندر کل صفحه (اسکرول و تایپ حفظ می‌شود) */
function refreshCard(id, edit){
  const it=findItem(id);if(!it)return;
  const el=document.getElementById('it-'+id);if(!el)return;
  const tpl=document.createElement('div');
  tpl.innerHTML=mnItemHTML(it, edit===undefined?designerEdit():!!edit);
  const fresh=tpl.firstElementChild;
  if(fresh)el.replaceWith(fresh);
}

function mnBodyHTML(edit){
 const m=M(),T=m.theme;
 const ce=(k,cat,item)=>edit?`contenteditable="true" data-ce="${k}"${cat?` data-cat="${cat}"`:''}${item?` data-item="${item}"`:''}`:'';
 const itemHTML=i=>mnItemHTML(i,edit);
 const grid=T.layout==='grid';
 const catHTML=(cat,list)=>`<section class="mn-cat" data-cat="${cat.id}" id="sec-${cat.id}">
  <div class="mn-cat-h"><h2 ${ce('catname',cat.id)}>${esc(cat.name)}</h2><span class="mn-n">${fa(list.length)}</span>
  ${edit?`<span class="iops"><button class="icobtn xs" data-act="cat-up" data-cat="${cat.id}">${ic('up',12)}</button><button class="icobtn xs" data-act="cat-down" data-cat="${cat.id}">${ic('down',12)}</button><button class="icobtn xs danger" data-act="cat-del" data-cat="${cat.id}">${ic('trash',12)}</button></span>`:''}</div>
  <div class="mn-items ${grid?'grid':''}"${edit?` data-draglist data-cat="${cat.id}"`:''}>${list.map(itemHTML).join('')}</div>
  ${edit?`<button class="additem" data-act="item-add" data-cat="${cat.id}">${ic('plus',13)} آیتم تازه در «${esc(cat.name)}»</button>`:''}</section>`;

 const q=(App.cst.sq||'').trim(),f=App.cst.sf||'all';
 const active=!edit&&(q||f!=='all');
 if(active){
  const ql=q.toLowerCase();
  const match=i=>{
   if(ql&&!(i.name.toLowerCase().includes(ql)||(i.desc||'').toLowerCase().includes(ql)))return false;
   if(f==='off'&&!(i.tags.off>0))return false;
   if(f==='popular'&&!i.tags.popular)return false;
   if(f==='chef'&&!i.tags.chef)return false;
   if(f==='daily'&&!i.tags.daily)return false;
   if(f==='available'&&i.tags.soldout)return false;
   return true;
  };
  const groups=m.cats.map(cat=>({cat,list:cat.items.filter(match)})).filter(g=>g.list.length);
  if(!groups.length)return `<div class="mn-nores">${ic('search',26)}<p style="margin-top:8px;font-weight:800">چیزی یافت نشد</p><small class="mini">عبارت دیگری امتحان کنید یا فیلتر را بردارید.</small></div>`;
  return groups.map(g=>catHTML(g.cat,g.list)).join('');
 }
 return `${m.cats.length?`<nav class="mn-tabs"><div class="mn-tabs-in">${m.cats.map(cat=>`<button class="mnt" data-act="jump-cat" data-cat="${cat.id}">${esc(cat.name)}</button>`).join('')}${edit?`<button class="mnt add" data-act="cat-add" title="دستهٔ تازه">${ic('plus',12)}</button>`:''}</div></nav>`:''}
 ${m.cats.map(cat=>catHTML(cat,cat.items)).join('')}
 ${m.cats.length?'':`<div class="mn-empty">${edit?'با دکمهٔ + در نوار دسته‌ها، اولین دسته را بسازید':'منو به‌زودی…'}</div>`}`;
}

function menuFrameHTML(edit){
 const m=M(),c=m.theme.colors,T=m.theme,brand=m.brand;
 m.cats.forEach(cat=>{if(!Array.isArray(cat.items))cat.items=[];cat.items.forEach(i=>{if(!i.tags)i.tags=defaultTags();});});
 const ceB=edit?`contenteditable="true" data-ce="brand"`:'';
 const ceT=edit?`contenteditable="true" data-ce="tagline"`:'';
 const ceA=edit?`contenteditable="true" data-ce="addr"`:'';

 const hbg=(T.headMode==='image'&&T.headImg)?'image':(T.headMode==='gradient'?'gradient':null);
 const hg1=T.headG1||shade(c.acc,-0.5), hg2=T.headG2||shade(c.acc,0.2);
 const headStyle=hbg==='gradient'?` id="mnhead" style="background:linear-gradient(150deg, ${hg1} 0%, ${hg2} 100%)"`:hbg==='image'?` style="background-image:url('${T.headImg}')"`:'';
 const headIcon=brand.logo?`<img class="mn-logo" src="${brand.logo}" alt="لوگو">`:ic(venueIcon(),26).replace('class="ic"','class="ic mn-ic"');
 const stInfo=schedStatus();
 const chip=stInfo?`<div class="sched-chip ${stInfo.open?'open':'closed'}"><span class="dot"></span>${esc(stInfo.label)}</div>`:'';
 const schedDet=`<details class="sched-d"><summary>${ic('clock',11)} ساعات کاری هفتگی</summary><div>
  ${(brand.sched||[]).map((r,i)=>`<div class="sched-row ${stInfo&&i===stInfo.idx?'today':''}"><span>${DAYS7[i]}${stInfo&&i===stInfo.idx?' · امروز':''}</span><b>${r.x?'تعطیل':faTime(r.o)+' – '+faTime(r.c)}</b></div>`).join('')}</div></details>`;
 const meta=(brand.address||edit)?`<div class="mn-meta"><span ${ceA}>${esc(brand.address)||(edit?'آدرس (اختیاری)':'')}</span></div>`:'';
 const head=`<header class="mn-head ${hbg?'hbg':''}"${headStyle}>
  ${hbg?'<span class="hbov2"></span>':''}
  ${headIcon}
  <h1 class="mn-name" ${ceB}>${esc(brand.name)||'—'}</h1>
  <p class="mn-tag" ${ceT}>${esc(brand.tagline)}</p>
  ${meta}${chip}${schedDet}
  <div class="mn-rule"><i></i><b></b><i></i></div></header>`;

 const banners=(m.banners||[]).filter(b=>edit||b.txt||b.img);
 const bnrHTML=b=>{
  const cat=b.link?findCat(b.link.id):null;
  let bstyle='';
  if(b.img)bstyle=`background-image:url('${b.img}')`;
  else if(b.g1&&b.g2)bstyle=`background:linear-gradient(135deg,${b.g1},${b.g2})`;
  const inner=`<div class="bnr" id="bnr-${b.id}"${bstyle?` style="${bstyle}"`:''}>
   ${b.img?'<span class="bov"></span>':''}
   <div class="bnr-in"><b contenteditable="${edit}" ${edit?`data-ce="btxt" data-bnr="${b.id}"`:''}>${esc(b.txt||'سرآغازهٔ تازه')}</b><span contenteditable="${edit}" ${edit?`data-ce="bsub" data-bnr="${b.id}"`:''}>${esc(b.sub||'')}</span></div>
   ${(!edit&&b.link)?`<span class="go">${ic('arrow',14)}</span>`:''}</div>
   ${edit?`<div class="bnr-ops">
    <button class="chipbtn" data-act="bnr-img" data-id="${b.id}">${ic('img',11)} ${b.img?'تعویض تصویر':'تصویر'}</button>
    <button class="chipbtn" data-act="bnr-link" data-id="${b.id}">${ic('arrow',11)} مقصد: ${cat?esc(cat.name):'—'}</button>
    ${!b.img?`<label class="chipbtn" title="رنگ شروع گرادیان">${ic('drop',11)}<input type="color" class="cpick" value="${b.g1||shade(c.acc,-0.35)}" data-input="bnr-g1" data-id="${b.id}"></label>
    <label class="chipbtn" title="رنگ پایان گرادیان"><input type="color" class="cpick" value="${b.g2||shade(c.acc,0.15)}" data-input="bnr-g2" data-id="${b.id}"></label>
    ${b.g1?`<button class="chipbtn" data-act="bnr-gclr" data-id="${b.id}">${ic('x',11)}</button>`:''}`:''}
    <button class="chipbtn" data-act="bnr-del" data-id="${b.id}">${ic('trash',11)}</button></div>`:''}`;
  return (!edit&&b.link)?`<div class="bnr-w" data-act="bnr-go" data-id="${b.id}" style="cursor:pointer">${inner}</div>`:`<div class="bnr-w">${inner}</div>`;
 };
 const bnrsHTML=(banners.length||edit)?`<div class="bnr-zone"><div class="bnrs" data-bi="0">${banners.map(bnrHTML).join('')}${edit?`<div class="bnr-empty"><button class="additem" data-act="bnr-add" style="border:0">${ic('plus',13)} بنر تبلیغاتی</button></div>`:''}</div>
  ${(!edit&&banners.length>1)?`<div class="bnr-dots">${banners.map((_,i)=>`<button class="bdot ${i===0?'on':''}" data-act="bnr-dot" data-i="${i}" aria-label="بنر ${fa(i+1)}"></button>`).join('')}</div>`:''}</div>`:'';

 let bgLayer='';
 const bg1=T.bgG1||c.bg, bg2=T.bgG2||mixHex(c.bg,c.acc,0.32);
 if(T.bgMode==='gradient')bgLayer=`<div class="mbg" id="mnbg" style="background:linear-gradient(160deg, ${bg1} 10%, ${bg2} 100%)"></div>`;
 else if(T.bgMode==='image'&&T.bgImg)bgLayer=`<div class="mbg"><img src="${T.bgImg}" alt=""><div class="mbg-ov" style="background:${c.bg};opacity:.82"></div></div>`;
 else if(T.bgMode==='video'&&T.bgVid)bgLayer=`<div class="mbg"><video src="${T.bgVid}" autoplay muted loop playsinline></video><div class="mbg-ov" style="background:${c.bg};opacity:.82"></div></div>`;

 return `<div class="mframe" style="--m-bg:${c.bg};--m-ink:${c.ink};--m-sub:${c.sub};--m-line:${c.line};--m-line2:${c.line};--acc:${c.acc}">
  ${bgLayer}<div class="mn-cont">
  ${head}
  ${bnrsHTML}
  ${edit?'':`<div class="mn-tools">${mnToolsHTML()}</div>`}
  <div id="mn-body">${mnBodyHTML(edit)}</div>
  <footer class="mn-foot">طراحی و اجرا توسط HPR-GitiArts</footer>
  </div></div>`;
}
