'use strict';
/* ════════════════════════════════════════════════════════════
   core.js — هسته برنامه، ابزارها، آیکون‌ها و شیء سراسری App
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

/* ═══ آیکون‌های SVG ═══ */
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
 gift:'<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
 pin:'<path d="M20 10c0 5.5-8 12-8 12S4 15.5 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'
};

const ic = (n, s = 18) => `<svg class="ic" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[n]||''}</svg>`;
const starIc = (on, s = 15) => `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="${on?'currentColor':'none'}" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">${P.star}</svg>`;

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

const SEC_KEY={menu:'menu',tables:'tables',settings:'settings',promos:'promos',
               users:'users',reservations:'reservations',shift:'shift',palettes:'customPalettes',setup:'setup',notifs:'notifs'};
/* v5.0.4-test.4: نام فارسی بخش‌ها برای پیام خطای قابل فهم */
const SEC_FA={menu:'منو',tables:'میزها',settings:'تنظیمات',promos:'کدهای تخفیف',users:'کاربران',reservations:'رزروها',shift:'شیفت',palettes:'پالت رنگ',setup:'راه‌اندازی',notifs:'پیام‌ها'};

/* v5.0.5-test.1: پیکربندی باشگاه مشتریان — نرخ صدور، ارزش هر امتیاز و سقف مصرف
   همه از تنظیمات مدیر کافه خوانده می‌شود (پیش‌فرض: ۱۰٬۰۰۰ تومان = ۱ امتیاز،
   هر امتیاز ۱٬۰۰۰ تومان تخفیف، حداکثر نصف سبد) */
function PT(){
 const p=(Store.db&&Store.db.settings&&Store.db.settings.points)||{};
 return {on:p.on!==false,
  earn:Math.max(1000,parseInt(p.earn,10)||10000),
  value:Math.max(500,parseInt(p.value,10)||1000),
  maxPct:Math.max(5,Math.min(100,parseInt(p.maxPct,10)||50))};
}

/* شیء سراسری App */
window.App = {
 role:null,
 login:{mode:'login',user:'',pass:'',phone:'',pass2:'',err:''},
 wiz:{step:0,biz:{name:'',tag:''},tbl:{want:true,seats:'۴',kds:true}},
 adm:{tab:'home',range:'7d',live:false,selCat:'',grp:{count:'۴',seats:'۴',zone:'سالن اصلی'},
      tnew:{seats:'۴',zone:'سالن اصلی'},cq:'',   /* v5.0.4-test.5: میز تازه (ظرفیت/منطقه) + جستجوی مشتریان */
      tgClosed:{},   /* v5.0.1: کارت‌هایی که برچسب‌هایشان با «تایید» بسته شده */
      nu:{code:'',off:''},nuu:{name:'',phone:'',pass:'',pin:''},rz:{table:'',name:'',phone:'',time:'',seats:'۲'},shu:{user:'',pin:''},spN:'۲'},
      /* v5.0.5-test.1: tab پیش‌فرض = «خانه» — گزارش‌ها + میزها + تنظیمات در یک صفحه */
 cst:{tab:'menu',flow:'cart',tableNo:null,sq:'',sf:'all',rv:null,optQ:1,promoDraft:'',
      form:{name:'',phone:'',loc:'',usePts:false,method:'online',tip:0,tipFix:0,tipNote:'',promo:null,card:'6219 8530 1245 6078',err:''},   /* v5.0.4-test.6: loc = لوکیشن اختیاری · v5.0.5-test.1: مشتری فقط پرداخت آنلاین */
      lg:{phone:'',name:'',err:''},done:null,
      rs:{name:'',phone:'',date:'',time:'',persons:'۲',note:'',err:'',ok:null},   /* v5.0.5-test.1: فرم رزرو مشتری */
      nb:[]},   /* v5.0.5-test.1: پیام‌های مشتری (کانال عمومی) */
 csh:{tab:'orders',f:'open',sale:null,lastPending:null}
};

window.Store = {
  db:null, cart:[], cust:null, _t:null, _dirty:{},
  async init(){
    try {
      this.db = await Api.get('state');
      sessionStorage.setItem('tenAdmin', '1');
    } catch(e) {
      sessionStorage.removeItem('tenAdmin');
      const pub = await Api.get('menu');
      this.db = {
        setup: pub.setup || {done: true},
        menu: pub.menu,
        settings: pub.settings,
        eta: pub.eta || 0,
        tables: [],
        orders: [],
        customers: [],
        reviews: [],
        promos: [],
        users: []
      };
      if(!App.role) App.role = 'customer';
    }
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

/* v5.0.4-test.5: میانگین زمان آماده‌سازی (دقیقه) — آینهٔ t_eta سمت سرور
   (۲۰ سفارش آخر که هم «شروع آشپزخانه kts» و هم «زمان آماده rdy» دارند)؛
   اگر سفارش‌ها در دسترس نباشد از eta محاسبه‌شدهٔ سرور (پاسخ عمومی منو) استفاده می‌شود؛
   بدون هیچ داده‌ای ۰ برمی‌گردد و رابط کاربری آن را پنهان می‌کند. فیکس خطای «prepETA is not defined» */
function prepETA(){
 try{
  const os=Store.db&&Store.db.orders;
  if(Array.isArray(os)&&os.length){
   const ds=os.filter(o=>o.kts>0&&o.rdy>0&&o.rdy>=o.kts).map(o=>o.rdy-o.kts).slice(-20);
   if(ds.length)return Math.max(1,Math.round(ds.reduce((a,b)=>a+b,0)/ds.length/60000));
  }
  return (Store.db&&Store.db.eta)||0;
 }catch(e){return 0;}
}

const M   = () => Store.db.menu;
const SET = () => Store.db.settings;

/* ═══ v5.0.5-test.3: لوگوی کافه → favicon خودکار ═══
   هر جا لوگو تنظیم/عوض/حذف شود صدا زده می‌شود + در بوت هر صفحه (index/cashier/kds).
   لوگو با canvas به PNG ۶۴×۶۴ تبدیل می‌شود تا در همهٔ مرورگرها (حتی Safari) نشان داده شود؛
   SVG مستقیم گذاشته می‌شود (مرورگرهای مدرن پشتیبانی می‌کنند). */
function applyFavicon(){
 try{
  const logo = (Store.db && Store.db.menu && Store.db.menu.brand && Store.db.menu.brand.logo) || '';
  let el = document.querySelector('link[rel="icon"]');
  if(!el){ el = document.createElement('link'); el.setAttribute('rel', 'icon'); document.head.appendChild(el); }
  if(!logo){ el.removeAttribute('href'); return; }
  const set = href => { try{ el.href = href; }catch(e){} };
  if(/\.svg(\?|$)/i.test(logo) || logo.slice(0, 15).toLowerCase().startsWith('data:image/svg')){ set(logo); return; }
  const img = new Image();
  img.onload = () => {
    try{
      const c = document.createElement('canvas'); c.width = c.height = 64;
      const x = c.getContext('2d');
      x.drawImage(img, 0, 0, 64, 64);
      set(c.toDataURL('image/png'));
    }catch(e){ set(logo); }
  };
  img.onerror = () => set(logo);
  img.src = logo;
 }catch(e){}
}
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
 const b=cartCalc(),S=SET(),f=App.cst.form,pt=PT();
 const promoAmt=f.promo?Math.round(b.sub*f.promo.off/100):0;
 const h=new Date().getHours();
 const hhAct=S.hh&&S.hh.on&&h>=S.hh.from&&h<S.hh.to;
 const hhAmt=hhAct?Math.round((b.sub-promoAmt)*S.hh.off/100):0;
 const remain=Math.max(0,b.sub-promoAmt-hhAmt);
 const ptsMax=(pt.on&&b.mb)?Math.min(b.mb.points,Math.floor(remain*pt.maxPct/100/pt.value)):0;
 const ptsAmt=(f.usePts?ptsMax:0)*pt.value;
 const disc=Math.min(b.sub,promoAmt+hhAmt+ptsAmt);
 const vat=S.vatOn?Math.round((b.sub-disc)*S.vatPct/100):0;
 /* v5.0.4-test.4: انعام در هر دو روش پرداخت — درصد (حداکثر ۳۰٪) یا مبلغ دلخواه (حداکثر تا سقف سفارش) */
 const base=Math.max(0,b.sub-disc);
 const tip=!S.tipOn?0:(f.tipFix>0?Math.min(Math.max(0,Math.round(f.tipFix)),base):Math.round(base*Math.max(0,Math.min(30,f.tip||0))/100));
 return {...b,promoAmt,hhAct,hhAmt,ptsMax,ptsAmt,disc,vat,tip,total:b.sub-disc+vat+tip};
}
