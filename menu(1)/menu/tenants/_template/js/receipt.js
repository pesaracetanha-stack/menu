'use strict';
/* ════════════════════════════════════════════════════════════
   receipt.js — رسید حرارتی + چاپ + تقسیم حساب + بوق
   ════════════════════════════════════════════════════════════ */

function splitAmounts(total,n){
 const base=Math.floor(total/n/1000)*1000;
 const arr=Array(n).fill(base);arr[n-1]=total-base*(n-1);return arr;
}
function receiptHTML(o,sp){
 let zig='M0 0 H280 ';for(let x=280;x>0;x-=10)zig+=`L${x-5} 10 L${x-10} 0 `;zig+='Z';
 const amount=sp?splitAmounts(o.total,sp.n)[sp.i-1]:o.total;
 return `<div class="rcpt">
  <div class="rc-head">${M().brand.logo?`<img class="rc-logo" src="${M().brand.logo}" alt="">`:''}
   <div class="rc-brand">${esc(M().brand.name)}</div>
   <div class="rc-sub">${esc(M().brand.address||'')} · ${esc(todayHoursTxt())}</div></div>
  <div class="rc-sep"></div>
  <div class="rc-row"><span>شماره سفارش</span><b>#${fa(o.num)}</b></div>
  <div class="rc-row"><span>تاریخ</span><b>${dtfF.format(o.ts)}</b></div>
  <div class="rc-row"><span>ساعت</span><b>${tf.format(o.ts)}</b></div>
  ${o.tableNo?`<div class="rc-row"><span>میز</span><b>${fa(o.tableNo)}</b></div>`:''}
  ${o.cashier?`<div class="rc-row"><span>صندوق‌دار</span><b>${esc(o.cashier)}</b></div>`:''}
  ${o.phone?`<div class="rc-row"><span>مشتری</span><b>${o.phone}</b></div>`:''}
  ${o.custNo?`<div class="rc-row"><span>شماره اشتراک</span><b>${fa(o.custNo)}</b></div>`:''}
  ${o.loc?`<div class="rc-row"><span>لوکیشن</span><b>${esc(String(o.loc).length>70?String(o.loc).slice(0,70)+'…':o.loc)}</b></div>`:''}
  <div class="rc-sep dash"></div>
  ${(o.items||[]).map(i=>`<div class="rc-it"><div>${fa(i.qty)} × ${esc(i.name)}</div><div>${faM((i.price+((i.opts||[]).reduce((s,x)=>s+(x.price||0),0)))*i.qty)}</div></div>${(i.opts||[]).map(x=>`<div class="rc-opt">+ ${esc(x.name)}</div>`).join('')}`).join('')}
  <div class="rc-sep dash"></div>
  <div class="rc-row"><span>جمع</span><b>${faM(o.sub)}</b></div>
  ${o.promoAmt?`<div class="rc-row"><span>کد تخفیف (${esc(o.promoCode||'')})</span><b>−${faM(o.promoAmt)}</b></div>`:''}
  ${o.hhAmt?`<div class="rc-row"><span>ساعت خوش</span><b>−${faM(o.hhAmt)}</b></div>`:''}
  ${o.discount?`<div class="rc-row"><span>تخفیف باشگاه</span><b>−${faM(o.discount)}</b></div>`:''}
  ${o.vat?`<div class="rc-row"><span>مالیات (٪${fa(SET().vatPct)})</span><b>${faM(o.vat)}</b></div>`:''}
  ${o.tip?`<div class="rc-row"><span>انعام</span><b>${faM(o.tip)}</b></div>`:''}
  ${o.tipNote?`<div class="rc-row"><span>توضیح انعام</span><b>${esc(o.tipNote)}</b></div>`:''}
  <div class="rc-row big"><span>${sp?`سهم ${fa(sp.i)} از ${fa(sp.n)}`:'پرداخت'}</span><b>${faM(amount)} تومان</b></div>
  <div class="rc-row"><span>روش پرداخت</span><b>${methodLabel(o.method)}</b></div>
  ${o.earned?`<div class="rc-row"><span>امتیاز کسب‌شده</span><b>${fa(o.earned)} امتیاز</b></div>`:''}
  <div class="rc-sep"></div>
  <div class="rc-thanks">${sp?'صورت‌حساب تقسیم‌شده — نوش جان!':'با تشکر از شما — نوش جان!'}</div>
  <div class="rc-bars">${Array.from({length:34},()=>`<i style="width:${1+(Math.random()*3|0)}px"></i>`).join('')}</div>
  <div class="rc-code">ORD-${o.num}</div></div>
  <svg class="rc-zig" viewBox="0 0 280 10" preserveAspectRatio="none"><path d="${zig}" fill="#fff"/></svg>`;
}
function receiptModal(o){
 ui.modal(`<div class="rc-wrap">${receiptHTML(o)}</div>
  <div class="rc-acts"><button class="btn" data-act="do-print" data-id="${o.id}">${ic('printer',15)} چاپ رسید</button>
  <button class="btn ghost" data-act="close-modal">بستن</button></div>`,{title:`رسید سفارش #${fa(o.num)}`});
}
function doPrint(o){$('#print-root').innerHTML=receiptHTML(o);window.print();}

let AC=null;
function beep(){try{
 AC=AC||new (window.AudioContext||window.webkitAudioContext)();
 const t=AC.currentTime,o=AC.createOscillator(),g=AC.createGain();
 o.type='sine';o.frequency.setValueAtTime(760,t);o.frequency.setValueAtTime(1020,t+.09);
 g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(.14,t+.02);g.gain.exponentialRampToValueAtTime(.0001,t+.3);
 o.connect(g).connect(AC.destination);o.start(t);o.stop(t+.32);
}catch(_){}}
