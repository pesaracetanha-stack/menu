'use strict';
/* ════════════════════════════════════════════════════════════
   ui-modals.js — همهٔ پنجره‌های مودال اپ
   ════════════════════════════════════════════════════════════ */

/* ── مودال آپشن آیتم (سمت مشتری) ── */
function openOptModal(id){
 const it=findItem(id);if(!it)return;
 const rows=(it.opts||[]).map(o=>`<label class="oprow"><input type="checkbox" value="${o.id}">
  <span style="flex:1">${esc(o.name)}</span><b>${o.price?'+'+faM(o.price)+' تومان':'رایگان'}</b></label>`).join('');
 const base=effPrice(it);
 /* v5.0.4-test.5: انتخاب تعداد در مودال گزینه‌ها — مشتری می‌تواند چند عدد با هم به سبد بگذارد */
 App.cst.optQ=1;
 ui.modal(`<p style="font-weight:800;margin-bottom:4px">${esc(it.name)}</p>
  <p class="dim" style="margin-bottom:10px">پایه: ${faM(base)} تومان — گزینه‌های دلخواه را انتخاب کنید</p>
  ${rows||'<p class="mini">این آیتم گزینه‌ای ندارد.</p>'}
  <div class="opqty"><span>${ic('cart',14)} تعداد</span>
   <div class="qstep"><button type="button" id="opt-q-dec" aria-label="کمتر">${ic('minus',13)}</button><b id="opt-q">۱</b><button type="button" id="opt-q-inc" aria-label="بیشتر">${ic('plus',13)}</button></div></div>
  <div class="sum" style="margin:12px 0"><div class="tot"><span>جمع انتخابی</span><b id="opt-total">${faM(base)} تومان</b></div></div>
  <div class="m-acts"><button class="btn" data-act="opt-apply" data-id="${id}">${ic('check',14)} افزودن به سبد</button>
  <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'انتخاب گزینه‌ها'});
 const upd=()=>{let t=base;
  $$('#modal-root .oprow input:checked').forEach(x=>{
   const o=(it.opts||[]).find(o=>o.id===x.value);if(o)t+=o.price;});
  t*=(App.cst.optQ||1);
  const el=$('#opt-total');if(el)el.textContent=faM(t)+' تومان';
  const qe=$('#opt-q');if(qe)qe.textContent=fa(App.cst.optQ||1);};
 $$('#modal-root .oprow input').forEach(x=>x.addEventListener('change',upd));
 const qInc=$('#opt-q-inc'),qDec=$('#opt-q-dec');
 if(qInc)qInc.addEventListener('click',()=>{App.cst.optQ=Math.min(99,(App.cst.optQ||1)+1);upd();});
 if(qDec)qDec.addEventListener('click',()=>{App.cst.optQ=Math.max(1,(App.cst.optQ||1)-1);upd();});
}

/* ── مدیریت آیتم (آپشن + قیمت خرید + موجودی + نظرات) ── */
function itemInfoModal(id){
 const it=findItem(id);if(!it)return;
 App.adm._it=id;
 const reviews=(Store.db.reviews||[]).filter(r=>r.itemId===id);
 ui.modal(`<p style="font-weight:800;margin-bottom:10px">${esc(it.name)} <span class="dim">· قیمت فروش ${faM(it.price)} تومان</span></p>
  <label class="f"><span>قیمت خرید / تمام‌شده (تومان — برای گزارش سود)</span><input data-input="it-cost" data-id="${it.id}" inputmode="numeric" value="${it.cost?moneyFmt(String(it.cost)):''}"></label>
  <label class="f"><span>موجودی انبار (خالی یا ۰=بی‌نهایت — با هر فروش کم می‌شود)</span><input data-input="it-stock" data-id="${it.id}" inputmode="numeric" placeholder="مثلاً ۱۲" value="${it.stock!=null?fa(it.stock):''}"></label>
  <p class="mini">رسیدن موجودی به صفر، تگ «ناموجود» را خودکار فعال می‌کند.</p>
  <h3 style="font-size:12.5px;font-weight:800;margin:14px 0 6px">${ic('plus',13)} گزینه‌های آیتم (آپشن — سایز، افزودنی…)</h3>
  ${(it.opts&&it.opts.length)?it.opts.map(o=>`<div class="lrow"><b>${esc(o.name)}</b><span class="tagpill">${o.price?'+'+faM(o.price)+' تومان':'رایگان'}</span>
   <button class="icobtn xs danger" data-act="op-del" data-id="${o.id}">${ic('trash',12)}</button></div>`).join(''):'<p class="mini">گزینه‌ای ثبت نشده.</p>'}
  <div class="promoline" style="margin-top:8px">
   <input id="op-name" placeholder="نام گزینه (مثلاً: سایز بزرگ)" style="flex:2">
   <input id="op-price" inputmode="numeric" placeholder="اضافهٔ قیمت (تومان)" style="flex:1">
   <button class="btn sm" data-act="op-add">${ic('plus',13)}</button></div>
  <h3 style="font-size:12.5px;font-weight:800;margin:14px 0 6px">${ic('star',13)} نظرات مشتریان (${fa(reviews.length)})</h3>
  ${reviews.length?reviews.map(r=>`<div class="lrow"><b><span style="color:#E3B94E">${'★'.repeat(r.stars)}</span> ${esc(r.txt)}</b>
   <span class="tagpill">${r.ok?'نمایش در منو':'در انتظار تأیید'}</span>
   <button class="icobtn xs" data-act="rv-ok" data-id="${r.id}">${ic(r.ok?'eye':'check',12)}</button>
   <button class="icobtn xs danger" data-act="rv-del" data-id="${r.id}">${ic('trash',12)}</button></div>`).join(''):'<p class="mini">هنوز نظری ثبت نشده است.</p>'}
  <div class="m-acts"><button class="btn ghost" data-act="close-modal">تمام</button></div>`,{title:'مدیریت آیتم'});
}

/* ── درصد تخفیف آیتم ── */
function openOffModal(it){
 ui.modal(`<p class="dim" style="margin-bottom:10px">تخفیف «${esc(it.name)}» — قیمت پایه ${faM(it.price)} تومان</p>
  <label class="f"><span>درصد تخفیف (۱ تا ۹۰ — هر عددی که بخواهید)</span><input id="off-inp" inputmode="numeric" value="${it.tags.off?fa(it.tags.off):''}" placeholder="مثلاً ۲۳"></label>
  <p class="mini" id="off-prev">—</p>
  <div class="m-acts"><button class="btn" data-act="tag-off-apply" data-item="${it.id}">${ic('check',14)} اعمال تخفیف</button>
  ${it.tags.off?`<button class="btn ghost" data-act="tag-off" data-item="${it.id}" data-v="0">حذف تخفیف</button>`:''}</div>
 `,{title:'درصد تخفیف'});
 const inp=$('#off-inp'),prev=$('#off-prev');
 const upd=()=>{const v=parseInt(en(inp.value),10)||0;
  prev.textContent=v?(v>=1&&v<=90)?`قیمت با تخفیف: ${faM(Math.max(1000,Math.round(it.price*(100-v)/100)))} تومان`:'درصد بین ۱ تا ۹۰ باشد':'—';};
 inp.addEventListener('input',upd);upd();
}

/* ── ثبت نظر مشتری ── */
function reviewModal(){
 const rv=App.cst.rv;if(!rv)return;
 const o=Store.db.orders.find(x=>x.id===rv.oid);if(!o)return;
 ui.modal(`<p class="dim" style="margin-bottom:8px">کدام آیتم این سفارش را نظر می‌دهید؟</p>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
   ${o.items.map(i=>`<button class="rvchip ${rv.itemId===i.id?'on':''}" data-act="rv-pick" data-id="${i.id}">${esc(i.name)}</button>`).join('')}</div>
  <p class="dim" style="margin-bottom:4px">امتیاز شما:</p>
  <div class="rvstars">${[1,2,3,4,5].map(v=>`<button class="${v<=rv.stars?'on':''}" data-act="rv-star" data-v="${v}">${starIc(v<=rv.stars,26)}</button>`).join('')}</div>
  <label class="f" style="margin-top:10px"><span>نظر شما</span><input id="rv-txt" maxlength="120" placeholder="نظرت رو بنویس..."></label>
  <div class="m-acts"><button class="btn" data-act="review-send">${ic('send',14)} ثبت نظر</button>
  <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'ثبت نظر'});
}

/* ── ارسال فاکتور ── */
function invoiceModal(o){
 ui.modal(`<p class="dim" style="margin-bottom:10px">سفارش #${fa(o.num)} · ${faM(o.total)} تومان</p>
  <label class="f"><span>شمارهٔ موبایل گیرنده</span><input id="inv-phone" inputmode="numeric" value="${o.phone||''}" placeholder="۰۹۱۲ · · · · · · ·"></label>
  <div class="sms-prev"><small>پیش‌نمایش پیامک</small>${esc(M().brand.name)} | فاکتور سفارش #${fa(o.num)} — مبلغ ${faM(o.total)} تومان — ${dtfD.format(o.ts)}</div>
  <div class="m-acts"><button class="btn" data-act="send-inv" data-id="${o.id}">${ic('send',15)} ارسال پیامک فاکتور</button>
  <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'ارسال فاکتور'});
}

/* ── انتخاب مقصد بنر ── */
function destPicker(bid){
 const m=M(),b=m.banners.find(x=>x.id===bid);
 const cur=b&&b.link?b.link.id:null;
 ui.modal(`<div class="dpick">
  <button class="dp ${!cur?'on':''}" data-act="bnr-dest" data-bid="${bid}" data-id="">بدون مقصد — فقط نمایشی</button>
  ${m.cats.map(c=>`<button class="dp ${cur===c.id?'on':''}" data-act="bnr-dest" data-bid="${bid}" data-id="${c.id}">${esc(c.name)}${ic('arrow',14)}</button>`).join('')||'<p class="mini">هنوز دسته‌ای نساخته‌اید.</p>'}
 </div>`,{title:'انتخاب مقصد بنر'});
}

/* ── تقسیم حساب ── */
function updSplitPrev(o){
 const p=$('#sp-prev');if(!p)return;
 const n=Math.max(2,parseInt(en(App.adm.spN),10)||2);
 const amounts=splitAmounts(o.total,n);
 p.innerHTML='سهم هر نفر: '+amounts.map(a=>faM(a)).join(' + ')+' تومان';
}

/* ── v5.0.4: پیش‌نمایش ایمپورت اکسل — تأیید پیش از ذخیره ── */
function excelImportModal(){
 const xls=App.adm.xls;if(!xls)return;
 const st=xls.stats||{cats:0,items:0};
 const warns=(xls.warnings||[]).slice(0,8);
 const more=((xls.warnings||[]).length)>8?`<p class="mini">و ${(xls.warnings.length)-8} هشدار دیگر…</p>`:'';
 ui.modal(`<div style="display:flex;gap:10px;margin-bottom:10px">
   <div style="flex:1;background:var(--bg2,#F6F0E4);border-radius:10px;padding:10px;text-align:center"><b style="font-size:20px">${fa(st.cats)}</b><br><span class="mini">دسته</span></div>
   <div style="flex:1;background:var(--bg2,#F6F0E4);border-radius:10px;padding:10px;text-align:center"><b style="font-size:20px">${fa(st.items)}</b><br><span class="mini">محصول</span></div>
  </div>
  ${warns.length?`<div style="max-height:130px;overflow:auto;background:#FFF7E6;border:1px solid #EBD9AE;border-radius:10px;padding:8px 10px;margin-bottom:10px">
    ${warns.map(w=>`<p class="mini" style="margin:3px 0">⚠ ${esc(w)}</p>`).join('')}${more}</div>`:''}
  <p class="mini" style="margin-bottom:10px">شیت خوانده‌شده: «${esc(xls.sheet||'منو')}» — ${esc(xls.note||'هیچ چیز هنوز ذخیره نشده است')}</p>
  <p style="font-weight:800;margin-bottom:6px">منوی فعلی با این فایل چه شود؟</p>
  <div class="m-acts">
   <button class="btn" data-act="excel-apply" data-mode="replace">جایگزینی کامل منو</button>
   <button class="btn ghost" data-act="excel-apply" data-mode="append">افزودن به منوی فعلی</button>
   <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'درون‌ریزی اکسل — پیش‌نمایش'});
}

/* ═══ v5.0.5-test.5: پنل پیامکی — تنظیمات اتصال API هر کافه ═══ */
function smsFormBody(){
 const g=id=>$('#'+id);
 return {
  en:   g('sms-en') ? g('sms-en').checked : false,
  prov: g('sms-prov') ? g('sms-prov').value : 'smsir',
  auto: g('sms-auto') ? g('sms-auto').checked : true,
  key:  en(g('sms-key') ? g('sms-key').value : '').trim(),
  line: en(g('sms-line') ? g('sms-line').value : '').trim(),
  wel:    g('sms-wel') ? g('sms-wel').checked : false,
  welTxt: g('sms-weltxt') ? g('sms-weltxt').value : '',
  hookUrl:   g('sms-hookurl') ? g('sms-hookurl').value.trim() : '',
  hookMethod:g('sms-hookm') ? g('sms-hookm').value : 'POST',
  hookHd:    g('sms-hookhd') ? g('sms-hookhd').value : '',
  hookBody:  g('sms-hookbody') ? g('sms-hookbody').value : ''
 };
}
function smsCfgModal(){
 const s=(Store.db.sms&&Store.db.sms.cfg)?Store.db.sms.cfg:(Store.db.sms||{});
 const prov=s.prov||'smsir';
 ui.modal(`
  <label class="f chk"><input type="checkbox" id="sms-en" ${s.en?'checked':''}><span>اتصال پنل پیامکی فعال باشد</span></label>
  <label class="f"><span>سرویس پیامکی</span>
   <select id="sms-prov">
    <option value="smsir" ${prov==='smsir'?'selected':''}>SMS.ir — api.sms.ir</option>
    <option value="kavenegar" ${prov==='kavenegar'?'selected':''}>کاوه‌نگار</option>
    <option value="hook" ${prov==='hook'?'selected':''}>وب‌هوک سفارشی — ذخیرهٔ مخاطب در هر پنل دیگر</option>
   </select></label>
  <div id="sms-keybox">
   <label class="f"><span>کلید وب‌سرویس ${s.keym?'(فعلاً: '+esc(s.keym)+' — برای تغییر، مقدار تازه بنویسید)':'(از پنل سرویس پیامکی بگیرید)'}</span><input id="sms-key" dir="ltr" autocomplete="off" placeholder="کلید API"></label>
   <label class="f"><span>شمارهٔ خط ارسال (پیامک گروهی/خوش‌آمد — مثل 3000505)</span><input id="sms-line" dir="ltr" value="${esc(s.line||'')}"></label>
  </div>
  <div id="sms-hookbox" style="display:none">
   <label class="f"><span>آدرس وب‌هوک (اندپوینت ثبت مخاطب پنل شما)</span><input id="sms-hookurl" dir="ltr" value="${esc(s.hookUrl||'')}" placeholder="https://..."></label>
   <label class="f"><span>متد</span><select id="sms-hookm"><option value="POST" ${(s.hookMethod||'POST')==='POST'?'selected':''}>POST</option><option value="GET" ${s.hookMethod==='GET'?'selected':''}>GET</option></select></label>
   <label class="f"><span>هدرها — هر خط یکی (اختیاری؛ مثل Authorization: apikey xxx)</span><textarea id="sms-hookhd" dir="ltr" rows="2" style="width:100%">${esc(s.hookHd||'')}</textarea></label>
   <label class="f"><span>بدنهٔ درخواست — جای‌نگرد: {name} {phone} {subno} {points} {cafe}</span><textarea id="sms-hookbody" dir="ltr" rows="3" style="width:100%">${esc(s.hookBody||'')}</textarea></label>
  </div>
  <label class="f chk"><input type="checkbox" id="sms-auto" ${s.auto!==false?'checked':''}><span>همگام‌سازی خودکار عضوهای تازه (صف)</span></label>
  <div id="sms-welbox">
   <label class="f chk"><input type="checkbox" id="sms-wel" ${s.wel?'checked':''}><span>پیامک خوش‌آمد خودکار برای عضو تازه (از اعتبار پنل خودتان کم می‌شود)</span></label>
   <label class="f"><span>متن خوش‌آمد — جای‌نگرد: {name} {subno} {cafe} {points}</span><textarea id="sms-weltxt" rows="2" style="width:100%">${esc(s.welTxt||'')}</textarea></label>
  </div>
  <p class="mini">وب‌سرویس SMS.ir و کاوه‌نگار «افزودن مخاطب» ندارند؛ با این دو می‌توانید پیامک گروهی و خوش‌آمد خودکار بفرستید. برای «ذخیره» در پنل پیامکی‌تان، اگر پنل شما API مخاطب دارد (اکثر پنل‌ها دارند) درایور «وب‌هوک سفارشی» را انتخاب و آدرس و قالبش را وارد کنید — هر عضو به‌صورت خودکار آن‌جا ثبت می‌شود. خروجی اکسل هم برای همهٔ پنل‌ها هست.</p>
  <p class="mini" id="sms-test-out">${(s.st&&s.st.lastTest)?((s.st.lastTest.ok?'✅ ':'⚠️ ')+esc(s.st.lastTest.msg)):''}</p>
  <div class="m-acts">
   <button class="btn" data-act="sms-cfg-save">${ic('check',14)} ذخیره</button>
   <button class="btn ghost" data-act="sms-test">${ic('refresh',14)} ذخیره و تست اتصال</button>
   <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'پنل پیامکی — تنظیمات'});
 const upd=()=>{const p=$('#sms-prov').value;
  $('#sms-keybox').style.display=(p==='hook')?'none':'';
  $('#sms-hookbox').style.display=(p==='hook')?'':'none';
  $('#sms-welbox').style.display=(p==='hook')?'none':'';};
 $('#sms-prov').addEventListener('change',upd);upd();
}

/* ═══ v5.0.5-test.5: پیامک گروهی به اعضای باشگاه ═══ */
/* PAY_UI_V1 */
function payFormBody(){
  const g = (id) => document.getElementById(id);
  const merchant = g('pay-merchant') ? g('pay-merchant').value.trim() : '';
  const sandbox  = g('pay-sandbox')  ? g('pay-sandbox').checked : false;
  return {
    merchant: merchant,
    sandbox:  sandbox ? 1 : 0,
    en:       merchant !== '' ? 1 : 0
  };
}

function payCfgModal(){
  const p = (Store.db.payment) || {};
  const st = p.st && p.st.lastTest ? p.st.lastTest : null;
  const lastMsg = st ? (st.ok ? '✅ ' + esc(st.msg) : '⚠️ ' + esc(st.msg)) : '';
  const lastColor = st && st.ok ? '#4F7A3D' : '#B4531F';

  ui.modal(`
    <div style="background:#F2EADA;border-radius:10px;padding:12px;margin-bottom:12px;font-size:13px;line-height:1.9">
      <b>💳 درگاه پرداخت زرین‌پال</b><br>
      <span>برای دریافت پرداخت آنلاین از مشتریان، شما به یک «مرچنت کد» اختصاصی نیاز دارید که پول مستقیم به حساب بانکی خودتان واریز می‌شود.</span>
    </div>

    <div style="border:1px dashed #DFD2B6;border-radius:10px;padding:12px;margin-bottom:12px">
      <b style="font-size:13px;color:#B4531F">📋 مرحله ۱ — دریافت مرچنت کد</b>
      <ol style="margin:8px 0 8px 18px;font-size:13px;line-height:2">
        <li>روی دکمه «ورود به زرین‌پال» زیر کلیک کنید</li>
        <li>در سایت زرین‌پال ثبت‌نام کنید (با موبایل + کد ملی)</li>
        <li>مدارک هویتی و شماره شبا بانکی خود را آپلود کنید</li>
        <li>پس از تأیید (معمولاً ۱ تا ۳ روز کاری)، مرچنت کد ۳۶ کاراکتری به شما داده می‌شود</li>
      </ol>
      <a href="https://www.zarinpal.com/auth/register" target="_blank" rel="noopener" class="btn" style="display:inline-block;text-decoration:none;padding:8px 16px">🔗 ورود به زرین‌پال</a>
    </div>

    <div style="border:1px dashed #DFD2B6;border-radius:10px;padding:12px;margin-bottom:12px">
      <b style="font-size:13px;color:#B4531F">🔑 مرحله ۲ — وارد کردن مرچنت کد</b>
      <label class="f" style="margin-top:8px">
        <span>مرچنت کد ۳۶ کاراکتری ${p.merchantSet ? '(فعلاً: ' + esc(p.merchantMasked || '') + ' — برای تغییر، مقدار جدید بنویسید)' : ''}</span>
        <input id="pay-merchant" dir="ltr" autocomplete="off" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
      </label>
      <label class="f chk"><input type="checkbox" id="pay-sandbox" ${p.sandbox ? 'checked' : ''}><span>حالت تست (Sandbox) — بدون پرداخت واقعی</span></label>
    </div>

    <p class="mini" id="pay-test-out" style="color:${lastColor}">${lastMsg}</p>
    <p class="mini">🔒 مرچنت کد شما به صورت امن در دیتابیس کافه ذخیره می‌شود و هرگز به مرورگر کاربران نمایش داده نمی‌شود.</p>

    <div class="m-acts">
      <button class="btn" data-act="pay-cfg-save">${ic('check',14)} ذخیره</button>
      <button class="btn ghost" data-act="pay-test">${ic('refresh',14)} ذخیره و تست اتصال</button>
      <button class="btn ghost" data-act="close-modal">انصراف</button>
    </div>`,
    {title:'درگاه پرداخت — تنظیمات'}
  );
}
/* پایان PAY_UI_V1 */
function smsSendModal(){
 const members=(Store.db.customers||[]).filter(c=>/^09\d{9}$/.test(String(c.phone||'')));
 if(!members.length){ ui.toast({msg:'هنوز عضوی با شمارهٔ موبایل ثبت نشده است'}); return; }
 ui.modal(`<p class="mini">به ${fa(members.length)} عضو باشگاه پیامک می‌رود (سقف هر بار ۳۰۰ شماره) — هزینه از اعتبار پنل پیامکی خودتان کم می‌شود.</p>
  <label class="f"><span>متن پیامک (حداکثر ۶۰۰ نویسه)</span><textarea id="sms-bulk-txt" rows="4" maxlength="600" style="width:100%" placeholder="مثلاً: امروز فقط تا ساعت ۱۸ — ۲۰٪ تخفیف ویژهٔ اعضای باشگاه مشتریان"></textarea></label>
  <p class="mini"><span id="sms-bulk-cnt">۰</span> نویسه</p>
  <div class="m-acts"><button class="btn" data-act="sms-bulk-go">${ic('send',14)} ارسال</button>
  <button class="btn ghost" data-act="close-modal">انصراف</button></div>`,{title:'پیامک گروهی به اعضا'});
 const t=$('#sms-bulk-txt');
 t.addEventListener('input',()=>{const n=[...t.value].length;$('#sms-bulk-cnt').textContent=fa(n);});
}
