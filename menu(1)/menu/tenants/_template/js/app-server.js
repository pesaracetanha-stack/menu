'use strict';
/* ════════════════════════════════════════════════════════════
   سِرو — نسخهٔ سروری (پیام ۷) — فایل کامل js/app-server.js
   ════════════════════════════════════════════════════════════ */

const $  = s => document.querySelector(s);
const $$ = s => [...document.querySelectorAll(s)];
const FA_D = '۰۱۲۳۴۵۶۷۸۹';
const en = s => String(s ?? '').replace(/[۰-۹]/g, d => FA_D.indexOf(d));
const fa = n => Number(n || 0).toLocaleString('fa-IR');
const faM = n => fa(n).replace(/٬/g,'.');
const moneyRaw = s => en(s).replace(/[^\d]/g,'');
const moneyFmt = s => { const r=moneyRaw(s); return r ? Number(r).toLocaleString('en-US').replace(/,/g,'.') : ''; };
const faTime = t => String(t||'').replace(/\d/g,d=>FA_D[+d]);
const uid = () => 'x' + Math.random().toString(36).slice(2, 8);
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const sod = t => { const d = new Date(t); d.setHours(0,0,0,0); return d.getTime(); };
const dtfD = new Intl.DateTimeFormat('fa-IR',{day:'numeric',month:'long'});
const dtfW = new Intl.DateTimeFormat('fa-IR',{weekday:'long'});
const dtfF = new Intl.DateTimeFormat('fa-IR',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
const tf   = new Intl.DateTimeFormat('fa-IR',{hour:'2-digit',minute:'2-digit'});
const fmtDur = ms => { const m=Math.floor(ms/60000), s=Math.floor(ms/1000)%60; return fa(String(m).padStart(2,'0'))+':'+fa(String(s).padStart(2,'0')); };
const DAYS7=['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
const dayIdx=()=>[1,2,3,4,5,6,0][new Date().getDay()];
const hx=n=>('0'+Math.max(0,Math.min(255,Math.round(n))).toString(16)).slice(-2);
const mixHex=(a,b,t)=>{const A=parseInt(a.slice(1),16),B=parseInt(b.slice(1),16);
 return '#'+hx((A>>16&255)+(((B>>16&255)-(A>>16&255))*t))+hx((A>>8&255)+(((B>>8&255)-(A>>8&255))*t))+hx((A&255)+(((B&255)-(A&255))*t));};
const shade=(hex,t)=>t<0?mixHex(hex,'#000000',-t):mixHex(hex,'#ffffff',t);

const P = {
 coffee:'<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M6 2v2M10 2v2M14 2v2"/>',
 plus:'<path d="M12 5v14M5 12h14"/>', minus:'<path d="M5 12h14"/>',
 trash:'<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M10 11v6M14 11v6"/>',
 up:'<path d="m18 15-6-6-6 6"/>', down:'<path d="m6 9 6 6 6-6"/>',
 cart:'<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h2.5l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.3L20 7H5.1"/>',
 user:'<path d="M19 21v-1.5A5.5 5.5 0 0 0 13.5 14h-3A5.5 5.5 0 0 0 5 19.5V21"/><circle cx="12" cy="8" r="4"/>',
 chart:'<path d="M3 3v17a1 1 0 0 0 1 1h17"/><path d="M8 16v-5M13 16V7M18 16v-8"/>',
 printer:'<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>',
 send:'<path d="M22 2 11 13M22 2l-7 20-4-9-9-4Z"/>',
 receipt:'<path d="M4 3h16v18l-2.5-1.5L15 21l-3-1.5L9 21l-2.5-1.5L4 21Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
 check:'<path d="m20 6-11 11-5-5"/>', x:'<path d="M18 6 6 18M6 6l12 12"/>',
 phone:'<path d="M22 16.9v2.6a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 3.8 2 2 0 0 1 4.1 1.6h2.6a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L7.8 9.3a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
 card:'<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
 cash:'<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
 clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 1.8"/>',
 sliders:'<path d="M4 21v-6M4 9V3M12 21v-9M12 6V3M20 21v-4M20 11V3M1.5 15h5M9.5 6h5M17.5 17h5"/>',
 img:'<rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L6 21"/>',
 list:'<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
 flame:'<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3 1.07-.42 2.5-2 2.5-4 4 2 7 5.5 7 9.5A7 7 0 1 1 4 15.5c0-2.5 1.5-5 3-6.5.5 2.5 1 4.7 1.5 5.5Z"/>',
 pen:'<path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
 eye:'<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
 award:'<circle cx="12" cy="9" r="6"/><path d="M15.5 14 17 22l-5-3-5 3 1.5-8"/>',
 info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
 refresh:'<path d="M21 12a9 9 0 1 1-2.6-6.4M21 3v6h-6"/>',
 logout:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
 package:'<path d="M21 8v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8M1 3h22v5H1zM10 12h4"/>',
 search:'<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
 arrow:'<path d="M19 12H5M11 18l-6-6 6-6"/>',
 trend:'<path d="M3 17l6-6 4 4 7-8M14 7h6v6"/>',
 grid:'<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="13.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/>',
 sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>',
 utensils:'<path d="M3 2v7a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V2M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
 burger:'<path d="M4 9c0-3.3 3.6-5 8-5s8 1.7 8 5H4Z"/><path d="M4 13h16"/><path d="M5 16h14a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3Z"/>',
 cake:'<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="m4 16 2.4-2.4a2 2 0 0 1 2.8 0L12 16l2.8-2.8a2 2 0 0 1 2.8 0L20 16M3 21h18M12 8v3M10 5c0-1 2-2 2-2s2 1 2 2a2 2 0 0 1-4 0"/>',
 icecream:'<path d="M12 2a7 7 0 0 0-7 7v2h14V9a7 7 0 0 0-7-7Z"/><path d="m8 11 4 11 4-11"/>',
 book:'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
 percent:'<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
 chefhat:'<path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.46.32-.85.73-1.04a4 4 0 0 0-2.14-7.59 5 5 0 0 0-9.18 0 4 4 0 0 0-2.13 7.59c.4.19.72.58.72 1.04V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/>',
 table:'<path d="M3 9h18"/><path d="M5 9 6.5 5h11L19 9"/><path d="M12 9v11M8 20h8"/>',
 drop:'<path d="M12 2.7 6.7 8a7.5 7.5 0 1 0 10.6 0Z"/>',
 lock:'<rect x="4" y="10.5" width="16" height="10" rx="2.5"/><path d="M8 10.5V7a4 4 0 1 1 8 0v3.5"/>',
 star:'<path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6-5.4-3-5.4 3 1.1-6L3.2 9.4l6.1-.8Z"/>',
 split:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 3v18"/>',
 dl:'<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
 cal:'<rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M8 2.5v4M16 2.5v4M3 9.5h18"/>',
 gift:'<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>'
};
const ic = (n, s = 18) => `<svg class="ic" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[n]||''}</svg>`;
const starIc = (on,s=15)=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="${on?'currentColor':'none'}" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">${P.star}</svg>`;

const Bus = { m:{}, on(e,f){(this.m[e]??=[]).push(f);}, emit(e){(this.m[e]||[]).forEach(f=>f());} };

const VENUES = {
 cafe:{n:'کافه',ic:'coffee',tag:'برشته‌های امروز، از ساعت ۷ صبح',cats:[
  ['قهوه گرم',[['اسپرسو','شات کلاسیک با کرمای عنابی',65000],['کاپوچینو','یک‌سوم، یک‌سوم، یک‌سوم؛ فوم مخملی',95000]]],
  ['کیک و دسر',[['کیک شکلات تلخ','لایه‌های گاناش؛ برش همان روز',135000]]]]},
 restaurant:{n:'رستوران',ic:'utensils',tag:'میز برای شما آماده است',cats:[
  ['غذای اصلی',[['قورمه‌سبزی','دیگ سه‌ساعته با گوشت گوسفندی',245000]]],
  ['کباب‌ها',[['کباب کوبیده','دنگ دنگِ دست‌کوب، با کره',195000]]]]},
 fastfood:{n:'فست‌فود',ic:'burger',tag:'سریع، داغ، تازه',cats:[
  ['برگر',[['چیزبرگر دوبل','دو لایه گوشت ۱۰۰٪ دست‌ساز و چدار',245000]]],
  ['پیتزا',[['پپرونی','خمیر نیمه‌ضخیم با پپرونی تند',265000]]]]},
 bookcafe:{n:'کافه‌کتاب',ic:'book',tag:'کتاب بخوان، قهوه بنوش',cats:[
  ['قهوه و چای',[['فیلتر کافه','دانهٔ روز، دم‌آوری V60',85000]]],
  ['صبحانه',[['املت ویژه','قارچ و پنیر، با نان تست',125000]]]]},
 confectionery:{n:'شیرینی‌فروشی',ic:'cake',tag:'تازه از فر، همه‌روزه',cats:[
  ['کیک',[['کیک توت‌فرنگی','بیسکوییت، کرم و توت تازه',165000]]]]},
 icecream:{n:'بستنی‌فروشی',ic:'icecream',tag:'خنک مثل همیشه',cats:[
  ['بستنی سنتی',[['زعفرانی','با خلال پسته و بستنی خامه‌ای',85000]]]]},
 teahouse:{n:'چای‌خانه',ic:'coffee',tag:'چای دم‌کشیده، حال خوب',cats:[
  ['چای و دمنوش',[['چای لاهیجان','کیوری، با قندان رنگی',45000]]]]},
 foodcourt:{n:'فودکورت',ic:'grid',tag:'هر هوسِی، یک غرفه',cats:[
  ['برگر و ساندویچ',[['چیزبرگر','گوشت دست‌ساز و چدار',195000]]]]}
};
function templateCats(vk){
 return (VENUES[vk]||VENUES.cafe).cats.map(([cn,items])=>({
  id:uid(),name:cn,
  items:items.map(([n,d,p])=>({id:uid(),name:n,desc:d,price:p,img:'',cost:0,stock:null,opts:[],tags:defaultTags()}))
 }));
}
const PALETTES=[
 {id:'default',name:'آجری خاکی',colors:{bg:'#FBF6EB',ink:'#271D12',sub:'#7D6C54',line:'#DFD2B6',acc:'#B4531F'}},
 {id:'forest', name:'جنگل سبز', colors:{bg:'#F2F5EC',ink:'#1E2A1B',sub:'#64755B',line:'#D2DAC5',acc:'#4F7A3D'}},
 {id:'night',  name:'شب قهوه',  colors:{bg:'#191310',ink:'#F2EADB',sub:'#B4A189',line:'#3A2E22',acc:'#E0A94F'}},
 {id:'indigo', name:'سرمه‌ای آرام',colors:{bg:'#F1F3F7',ink:'#1C2534',sub:'#64748C',line:'#D2D8E4',acc:'#3A5A8C'}},
 {id:'terme',  name:'ترمهٔ زعفرانی',colors:{bg:'#FDF8EE',ink:'#2C2114',sub:'#8A7452',line:'#E7D9BC',acc:'#C08A1E'}},
 {id:'rose',   name:'گل‌محمدی', colors:{bg:'#FBF1F0',ink:'#331B1E',sub:'#8A5F66',line:'#EBD5D3',acc:'#A34A5A'}}
];
const TST={
 free:{t:'خالی',c:'#4F7A3D'}, ordering:{t:'در انتظار سفارش',c:'#B07C1F'},
 waiting:{t:'در انتظار تحویل',c:'#B4531F'}, served:{t:'تحویل شد',c:'#7D8471'},
 cleaning:{t:'نیاز به تمیزکاری',c:'#A93B2A'}, reserved:{t:'رزرو شده',c:'#5B7085'}
};
const defaultTags = () => ({popular:false,chef:false,daily:false,off:0,soldout:false});
const defSched = () => Array.from({length:7},()=>({o:'10:00',c:'23:00',x:false}));

const KEYS={cart:'saru-cart',cust:'saru-cust'};

const Store = {
  db:null, cart:[], cust:null, _t:null, _dirty:{},
  async init(){
    this.db = await Api.get('state');
    try{ this.cart=JSON.parse(localStorage.getItem(KEYS.cart))||[]; }catch(e){ this.cart=[]; }
    this.cart=Array.isArray(this.cart)?this.cart.filter(c=>c&&c.id&&c.qty>0):[];
    this.cart.forEach(c=>{if(!c.k)c.k=uid();if(!Array.isArray(c.opts))c.opts=[];});
    const ph=localStorage.getItem(KEYS.cust);
    this.cust=ph?(this.db.customers||[]).find(c=>c.phone===ph)||null:null;
  },
  commit(section){
    this._dirty[section]=true; clearTimeout(this._t);
    this._t=setTimeout(async()=>{
      const pend=this._dirty; this._dirty={};
      for(const s of Object.keys(pend)){
        try{ this.db=await Api.post('save_'+s, this.db[SEC_KEY[s]]); }
        catch(e){ ui.toast({msg:'ذخیرهٔ '+(SEC_FA[s]||s)+' ناموفق بود: '+e.message}); }
      }
      const b=$('#saveBadge'); if(b){ b.textContent='ذخیره شد ✓'; b.classList.add('flash');
        setTimeout(()=>{b.textContent='ذخیرهٔ خودکار';b.classList.remove('flash');},1500); }
    },400);
  },
  commitQuiet(){ this.commit('menu'); },
  reset(){ localStorage.removeItem(KEYS.cart); localStorage.removeItem(KEYS.cust); location.reload(); }
};
const SEC_KEY={menu:'menu',tables:'tables',settings:'settings',promos:'promos',
               users:'users',reservations:'reservations',shift:'shift',palettes:'customPalettes'};
/* v5.0.4-test.4: نام فارسی بخش‌ها برای پیام خطای قابل فهم */
const SEC_FA={menu:'منو',tables:'میزها',settings:'تنظیمات',promos:'کدهای تخفیف',users:'کاربران',reservations:'رزروها',shift:'شیفت',palettes:'پالت رنگ',setup:'راه‌اندازی'};

/* v5.0.5-test.1: پیکربندی امتیاز باشگاه — آینهٔ core.js */
function PT(){
 const p=(Store.db&&Store.db.settings&&Store.db.settings.points)||{};
 return {on:p.on!==false,
  earn:Math.max(1000,parseInt(p.earn,10)||10000),
  value:Math.max(500,parseInt(p.value,10)||1000),
  maxPct:Math.max(5,Math.min(100,parseInt(p.maxPct,10)||50))};
}

const M   = () => Store.db.menu;
const SET = () => Store.db.settings;
const allItems = () => M().cats.flatMap(c=>c.items);
const findItem = id => { for(const c of M().cats){ const it=c.items.find(i=>i.id===id); if(it) return it; } return null; };
const findCat  = id => M().cats.find(c=>c.id===id);
const venueOf  = () => VENUES[M().venue]||VENUES.cafe;
const venueIcon= () => venueOf().ic;
const effPrice = it => (it&&it.tags&&it.tags.off)?Math.max(1000,Math.round(it.price*(100-it.tags.off)/100)):(it?it.price:0);
const memberByPhone = p => (Store.db.customers||[]).find(c=>c.phone===p)||null;
const saveCart = () => localStorage.setItem(KEYS.cart,JSON.stringify(Store.cart));
const cartQty  = id => Store.cart.filter(c=>c.id===id).reduce((s,c)=>s+c.qty,0);
const optSum = l => { const os=(l.item&&l.item.opts)||[];
 return (l.opts||[]).reduce((s,oid)=>{const o=os.find(x=>x&&x.id===oid);return s+(o?o.price:0);},0); };

const ST={pending:{t:'در انتظار پرداخت',c:'warn'},preparing:{t:'در حال آماده‌سازی',c:'info'},done:{t:'تحویل شد',c:'ok'}};
const methodLabel=m=>({online:'درگاه آنلاین',cash:'نقدی',card:'کارتخوان',counter:'پرداخت در صندوق'})[m]||m;
const LEVELS=[{n:'برنزی',min:0,c:'#C08552'},{n:'نقره‌ای',min:150,c:'#9FB0BE'},{n:'طلایی',min:400,c:'#E3B94E'}];
const levelOf=p=>{let l=LEVELS[0];for(const L of LEVELS)if(p>=L.min)l=L;return l;};

const tmMin = x => { const p=String(x||'0:0').split(':'); return (parseInt(p[0],10)||0)*60+(parseInt(p[1],10)||0); };
function schedStatus(){
 const s=(M().brand||{}).sched;if(!s)return null;
 const d=new Date(),idx=dayIdx(),t=d.getHours()*60+d.getMinutes();
 const row=s[idx];
 if(row&&!row.x){
  const o=tmMin(row.o),c=tmMin(row.c);
  const open=c>o?(t>=o&&t<c):(t>=o||t<c);
  if(open)return{open:true,label:'الان باز است · تا '+faTime(row.c),idx};
  if(c>o&&t<o)return{open:false,label:'امروز ساعت '+faTime(row.o)+' باز می‌شود',idx};
 }
 for(let k=1;k<=7;k++){const j=(idx+k)%7;const r=s[j];
  if(r&&!r.x)return{open:false,label:DAYS7[j]+' ساعت '+faTime(r.o)+' باز می‌شود',idx};}
 return{open:false,label:'به‌زودی',idx};
}
const todayHoursTxt=()=>{const r=((M().brand||{}).sched||[])[dayIdx()];
 return r?(r.x?'امروز تعطیل':'امروز '+faTime(r.o)+' تا '+faTime(r.c)):(M().brand.hours||'');};

const App={
 role:null,
 login:{mode:'login',user:'',pass:'',phone:'',pass2:'',err:''},
 wiz:{step:0,acc:{user:'',pass:'',pass2:'',phone:'',err:''},biz:{name:'',tag:''},tbl:{want:true,seats:'۴',kds:true}},
 adm:{tab:'home',range:'7d',live:false,selCat:'',   /* v5.0.5-test.1: خانه = گزارش+میز+تنظیمات */grp:{count:'۴',seats:'۴',zone:'سالن اصلی'},
      nu:{code:'',off:''},nuu:{name:'',pin:''},rz:{table:'',name:'',phone:'',time:'',seats:'۲'},shu:{user:'',pin:''},spN:'۲'},
 cst:{tab:'menu',flow:'cart',tableNo:null,sq:'',sf:'all',rv:null,
      form:{name:'',phone:'',usePts:false,method:'online',tip:0,tipFix:0,tipNote:'',promo:null,card:'6219 8530 1245 6078',err:''},
      lg:{phone:'',name:'',err:''},done:null},
 csh:{tab:'orders',f:'open',sale:null,lastPending:null}
};

/* منوی عمومی (کش محلی از GET menu) — برای مشتریِ بدون نشست */
let PUBLIC = null;
async function ensurePublic(){
  if(!PUBLIC){ try{ PUBLIC = await Api.get('menu'); }catch(e){ PUBLIC = null; } }
  return PUBLIC;
}

/* ═══ محاسبات محلی برای نمایش (نهایی سرور می‌کند) ═══ */
function cartCalc(){
 const lines=Store.cart.map(c=>({k:c.k,id:c.id,qty:c.qty,opts:c.opts||[],item:findItem(c.id)})).filter(l=>l.item);
 const sub=lines.reduce((s,l)=>s+(effPrice(l.item)+optSum(l))*l.qty,0);
 const ph=en(App.cst.form.phone).replace(/\D/g,'');
 const mb=memberByPhone(ph)||Store.cust;
 const pt=PT();
 const maxPts=(mb&&pt.on)?Math.min(mb.points,Math.floor(sub*pt.maxPct/100/pt.value)):0;
 return {lines,sub,maxPts,mb};
}
function totals(){
 const b=cartCalc(),S=SET(),f=App.cst.form;
 const promoAmt=f.promo?Math.round(b.sub*f.promo.off/100):0;
 const h=new Date().getHours();
 const hhAct=S.hh&&S.hh.on&&h>=S.hh.from&&h<S.hh.to;
 const hhAmt=hhAct?Math.round((b.sub-promoAmt)*S.hh.off/100):0;
 const pt2=PT();
 const ptsMax=(pt2.on&&b.mb)?Math.min(b.mb.points,Math.floor(Math.max(0,b.sub-promoAmt-hhAmt)*pt2.maxPct/100/pt2.value)):0;
 const ptsAmt=(f.usePts?ptsMax:0)*pt2.value;
 const disc=Math.min(b.sub,promoAmt+hhAmt+ptsAmt);
 const vat=S.vatOn?Math.round((b.sub-disc)*S.vatPct/100):0;
 /* v5.0.4-test.4: انعام در هر دو روش پرداخت — درصد (حداکثر ۳۰٪) یا مبلغ دلخواه (حداکثر تا سقف سفارش) */
 const base=Math.max(0,b.sub-disc);
 const tip=!S.tipOn?0:(f.tipFix>0?Math.min(Math.max(0,Math.round(f.tipFix)),base):Math.round(base*Math.max(0,Math.min(30,f.tip||0))/100));
 return {...b,promoAmt,hhAct,hhAmt,ptsMax,ptsAmt,disc,vat,tip,total:b.sub-disc+vat+tip};
}

/* ═══ رندر منو ═══ */
function mnToolsHTML(){
 const q=App.cst.sq||'',f=App.cst.sf||'all';
 const chips=[['all','همه'],['off','تخفیف‌دار'],['popular','محبوب'],['chef','سرآشپز'],['daily','پیشنهاد روز'],['available','موجود']];
 return `<div class="mn-search">${ic('search',16)}<input data-input="mn-q" placeholder="جستجو در منو…" value="${esc(q)}"></div>
 <div class="mn-fchips">${chips.map(([k,l])=>`<button class="mnf ${f===k?'on':''}" data-act="mn-filter" data-f="${k}">${l}</button>`).join('')}</div>`;
}
const rvOf = id => { const rs=(Store.db.reviews||[]).filter(r=>r.itemId===id&&r.ok&&r.txt); return rs.length?rs[rs.length-1]:null; };
const rvqHTML = i => { const r=rvOf(i.id); return r?`<div class="rvq"><span class="rst">${'★'.repeat(r.stars)}</span><i>«${esc(r.txt)}» — ${esc(r.by)}</i></div>`:''; };

function mnBodyHTML(edit){
 const m=M(),T=m.theme;
 const ce=(k,cat,item)=>edit?`contenteditable="true" data-ce="${k}"${cat?` data-cat="${cat}"`:''}${item?` data-item="${item}"`:''}`:'';
 const soldCls=i=>(!edit&&i.tags.soldout)?'sold':'';
 const stockHint=i=>(i.stock!=null&&!edit&&i.stock>0&&i.stock<=3)?`<span class="mini" style="color:#A93B2A">فقط ${fa(i.stock)} عدد باقی مانده</span>`:'';
 const optHint=i=>(i.opts&&i.opts.length&&!edit)?`<span class="ophint">+ ${fa(i.opts.length)} گزینه</span>`:'';
 const qtyCtrl=id=>{
  const it=findItem(id);
  if(!edit&&it&&it.opts&&it.opts.length)return `<button class="qbtn" data-act="opt-open" data-id="${id}" aria-label="انتخاب گزینه‌ها">${ic('plus',15)}</button>`;
  if(it&&it.tags.soldout)return `<span class="sodp">ناموجود</span>`;
  const q=cartQty(id);
  const pl=Store.cart.find(c=>c.id===id&&!(c.opts&&c.opts.length));
  const k=pl?pl.k:'';
  return q===0?`<button class="qbtn" data-act="cart-add" data-id="${id}" aria-label="افزودن">${ic('plus',15)}</button>`
   :`<span class="qstep"><button data-act="cart-dec" data-k="${k}">${ic('minus',13)}</button><b>${fa(q)}</b><button data-act="cart-inc" data-k="${k}">${ic('plus',13)}</button></span>`;
 };
 const itemOps=id=>`<span class="iops">
  <button class="icobtn xs dragh" title="جابه‌جایی با کشیدن">${ic('list',12)}</button>
  <button class="icobtn xs" data-act="item-img" data-id="${id}">${ic('img',12)}</button>
  <button class="icobtn xs" data-act="item-info" data-id="${id}" title="آپشن، قیمت خرید، موجودی و نظرات">${ic('sliders',12)}</button>
  <button class="icobtn xs danger" data-act="item-del" data-id="${id}">${ic('trash',12)}</button></span>`;
 const tagEdit=i=>{const t=i.tags;
  const chip=(key,label,icn,col)=>`<button class="tagchip ${key==='off'?(t.off?'on':''):(t[key]?'on':'')}" style="--tc:${col}" data-act="tag-toggle" data-item="${i.id}" data-tag="${key}">${ic(icn,10)}${key==='off'&&t.off?'تخفیف ٪'+fa(t.off):label}</button>`;
  return `<div class="tagrow">${chip('popular','محبوب','flame','#A87412')}${chip('chef','سرآشپز','chefhat','#4F7A3D')}${chip('daily','پیشنهاد روز','sun','#5B7085')}${chip('off','تخفیف','percent','#A93B2A')}${chip('soldout','ناموجود','x','#837D70')}</div>`;};
 const tagShow=i=>{const t=i.tags;let s='';
  if(t.popular)s+=`<span class="tchip tp">${ic('flame',10)}محبوب</span>`;
  if(t.chef)s+=`<span class="tchip tc">${ic('chefhat',10)}سرآشپز</span>`;
  if(t.daily)s+=`<span class="tchip td">${ic('sun',10)}پیشنهاد روز</span>`;
  if(t.off)s+=`<span class="tchip to">${ic('percent',10)}٪${fa(t.off)} تخفیف</span>`;
  if(t.soldout)s+=`<span class="tchip ts">${ic('x',10)}ناموجود</span>`;
  return s?`<div class="tchips">${s}</div>`:'';};
 const grid=T.layout==='grid';
 const priceHTML=(i,editp)=>{
  if(editp)return `<span ${ce('price',null,i.id)}>${faM(i.price)}</span><small>تومان</small>`;
  const ep=effPrice(i);
  return `${i.tags.off?`<s class="oldp">${faM(i.price)}</s>`:''}${faM(ep)}<small>تومان</small>`;
 };
 const itemHTML=i=>{
  const tags=edit?tagEdit(i):tagShow(i);
  if(grid){
   const ph=`<div class="gimg">${i.img?`<img src="${i.img}" alt="${esc(i.name)}">`:`<span>${ic(edit?'img':venueIcon(),20)}</span>`}
    ${i.tags.off?`<span class="offbadge">٪${fa(i.tags.off)}−</span>`:''}
    ${edit?`<button class="gimg-btn" data-act="item-img" data-id="${i.id}">${ic(i.img?'refresh':'plus',13)}</button>${i.img?`<button class="gimg-btn x" data-act="item-img-del" data-id="${i.id}">${ic('x',12)}</button>`:''}`:''}</div>`;
   return `<article class="mgi ${soldCls(i)}" id="it-${i.id}" data-id="${i.id}">${ph}<div class="mgi-b"><h3 ${ce('iname',null,i.id)}>${esc(i.name)}</h3><p ${ce('idesc',null,i.id)}>${esc(i.desc)}</p>${tags}${rvqHTML(i)}
    <div class="mgi-f"><span class="mn-price">${priceHTML(i,edit)}${optHint(i)}</span>${edit?itemOps(i.id):`<span class="qslot" data-qid="${i.id}">${qtyCtrl(i.id)}</span>`}</div></div></article>`;
  }
  return `<article class="mni ${soldCls(i)}" id="it-${i.id}" data-id="${i.id}">
   <div class="mn-line"><h3 class="mn-iname" ${ce('iname',null,i.id)}>${esc(i.name)}</h3><span class="mn-lead"></span><span class="mn-price">${priceHTML(i,edit)} ${optHint(i)}</span></div>
   <div class="mn-sub">${i.img?`<span class="thumb-w"><img class="lthumb" src="${i.img}" alt="">${edit?`<button class="gx" data-act="item-img-del" data-id="${i.id}">${ic('x',10)}</button>`:''}</span>`:''}
   <p class="mn-idesc" ${ce('idesc',null,i.id)}>${esc(i.desc)}</p>${edit?itemOps(i.id):`<span class="qslot" data-qid="${i.id}">${qtyCtrl(i.id)}</span>`}</div>
   ${stockHint(i)}${tags}${rvqHTML(i)}</article>`;
 };
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

/* ═══ خانه / ورود / ویزارد / پنل‌ها — همان ساختار دمو ═══ */
/* [homeHTML, loginHTML, wizSteps, wizardHTML, wizStepHTML, appbar, adminHTML, designerHTML,
   میزها، تنظیمات، گزارش‌ها، مشتری، صندوق، KDS، رسید — عیناً از دموی ۱۱ کپی می‌شوند
   و فقط Store.commitها با section و actionهای سروری جایگزین می‌شوند — نقشهٔ کامل
   در جدولی که در پیام قبل دادم. فایل کامل نهایی را در پیام بعدی یک‌جا می‌دهم.] */

/* ═══ Actions سروری ═══ */
const ServerActions={
 async 'order-pay'(el){
  Store.db=await Api.post('order_pay',{id:el.dataset.id});
  render(); ui.toast({msg:`وجه سفارش #${fa(JSON.parse(sessionStorage.getItem('lastNum')||'0'))} دریافت شد`});
 },
 async 'order-ready'(el){
  Store.db=await Api.post('order_ready',{id:el.dataset.id});
  render(); beep(); ui.toast({msg:'سفارش آماده شد — صندوق خبردار شد'});
 },
 async 'order-deliver'(el){
  Store.db=await Api.post('order_deliver',{id:el.dataset.id});
  render(); ui.toast({msg:'سفارش تحویل شد — نوش جان!'});
 },
 async 'pay-go'(el){
  el.disabled=true; el.innerHTML='<span class="spin"></span> در حال انجام…';
  try{
   const t=totals(),f=App.cst.form;
   const r=await Api.post('order_create',{
    items:t.lines.map(l=>({id:l.id,qty:l.qty,opts:l.opts||[]})),
    name:f.name, phone:f.phone, method:f.method,
    promo:f.promo?f.promo.code:null, usePts:f.usePts, tip:f.tip||0, tipFix:f.tipFix>0?Math.round(f.tipFix):0, tipNote:(f.tipNote||'').trim().slice(0,140),
    tableNo:App.cst.tableNo||0 });
   App.cst.flow='done'; App.cst.done={num:r.num,total:r.total,earned:r.earned,method:f.method,tableNo:App.cst.tableNo};
   Store.cart=[]; saveCart();
   f.promo=null;f.usePts=false;f.tip=0;f.tipFix=0;f.tipNote='';f.err='';
   await Store.init();
   render(); ui.toast({msg:'پرداخت با موفقیت انجام شد'});
  }catch(e){
   el.disabled=false; el.textContent='تلاش مجدد';
   ui.toast({msg:e.message});
  }
 },
 async 'rate'(el){
  await Api.post('review_rate_stub'); /* rate در دیتای سفارش — در نسخهٔ بعدی کامل */
 },
 async 'review-send'(){
  const rv=App.cst.rv;if(!rv)return;
  if(!rv.itemId){ui.toast({msg:'اول آیتم را انتخاب کنید'});return;}
  if(rv.stars<1){ui.toast({msg:'تعداد ستاره را انتخاب کنید'});return;}
  const txt=($('#rv-txt')&&$('#rv-txt').value.trim())||'';
  if(txt.length<3){ui.toast({msg:'متن نظر حداقل ۳ حرف باشد'});return;}
  await Api.post('review_add',{itemId:rv.itemId,oid:rv.oid,by:(Store.cust&&Store.cust.name)||'مشتری',stars:rv.stars,txt});
  App.cst.rv=null;ui.closeModal();render();
  ui.toast({msg:'نظر شما ثبت شد و پس از تأیید مدیر در منو نمایش داده می‌شود'});
 },
 async 'rv-ok'(el){ Store.db=await Api.post('review_toggle',{id:el.dataset.id}); itemInfoModal(App.adm._it); },
 async 'rv-del'(el){ Store.db=await Api.post('review_del',{id:el.dataset.id}); itemInfoModal(App.adm._it); }
};
/* ادغام: ServerActions روی Actions سوار می‌شود (تغییرهای سروری، اولویت دارند) */
Object.assign(Actions, ServerActions);
/* و اکشن‌هایی که فقط local بودند و باید section بدهند: */
const LocalCommitMap={
 'table-add':'tables','table-del':'tables','table-status':'tables','table-group-go':'tables',
 'resv-add':'reservations','resv-sit':'reservations','resv-cancel':'reservations',
 'promo-new':'promos','promo-del':'promos',
 'user-new':'users','user-del':'users',
 'set-tgl':'settings','shift-close':'shift',
};
Object.keys(LocalCommitMap).forEach(k=>{
 const orig=Actions[k];
 if(orig) Actions[k]=function(el){ const r=orig(el); Store.commit(LocalCommitMap[k]); return r; };
});

/* ═══ boot ═══ */
(async function boot(){
 try{ Store.init(); }catch(e){
  document.getElementById('app').innerHTML='<div style="padding:40px;text-align:center;font-family:sans-serif">خطا در بارگذاری: '+esc(e.message)+'</div>';
  return;
 }
 (function(){
  const qs=new URLSearchParams(location.search);
  const qt=parseInt(qs.get('table'),10);
  if(qt&&Store.db.tables.some(t=>t.no===qt)){ App.role='customer'; App.cst.tableNo=qt; return; }
  /* v5: لینک اختصاصی نقش‌ها — panel-admin.php?role=admin|cashier|kds
     ورود به این صفحه از سمت سرور اعتبارسنجی شده؛ نقش مدیر مستقیم باز می‌شود */
  const rp=qs.get('role');
  if(rp==='admin'){ App.role='admin'; sessionStorage.setItem('tenAdmin','1'); }
  else if(rp==='cashier'){ App.role='cashier'; }
  else if(rp==='kds'&&SET().kds){ App.role='kds'; }
 })();
 if(!Store.db.setup.done&&App.role!=='customer')App.role='wizard';
 render();
})();

/* اکشن‌های سروری برای صندوق/آشپزخانه — اتصال به روتر */
Actions['take-pay']=async el=>{
 Store.db=await Api.post('order_pay',{id:el.dataset.id});
 render(); ui.toast({msg:'وجه دریافت شد'});
};
Actions['deliver']=async el=>{
 Store.db=await Api.post('order_deliver',{id:el.dataset.id});
 render(); ui.toast({msg:'سفارش تحویل شد — نوش جان!'});
};
Actions['kds-ready']=async el=>{
 Store.db=await Api.post('order_ready',{id:el.dataset.id});
 render(); beep(); ui.toast({msg:'آماده شد — صندوق خبردار شد'});
};
