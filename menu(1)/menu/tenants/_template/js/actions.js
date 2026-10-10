'use strict';
/* ════════════════════════════════════════════════════════════
   actions.js — همهٔ Actionها و Inputs (نسخهٔ سروری، کامل و نهایی)
   ════════════════════════════════════════════════════════════ */

let imgTarget = null;
function mv(arr, id, d) {
  const i = arr.findIndex(x => x.id === id);
  const j = i + d;
  if (i < 0 || j < 0 || j >= arr.length) return;
  [arr[i], arr[j]] = [arr[j], arr[i]];
  Store.commit('menu');
}
function refreshMenuPreview() {
  const p = $('.phone-inner');
  if (p) p.innerHTML = menuFrameHTML(false);
}
function mnRefreshToolsBody() {
  const t = $('#mn-tools');
  if (t) t.innerHTML = mnToolsHTML();
  const b = $('#mn-body');
  if (b) b.innerHTML = mnBodyHTML(false);
}
function download(name, content, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type }));
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}
function killBgVideo() {
  const t = M().theme;
  if (t.bgVid) { t.bgVid = ''; }
}
function resetGrads() {
  const t = M().theme;
  t.headG1 = '';
  t.headG2 = '';
  t.bgG1 = '';
  t.bgG2 = '';
  (t.banners || []).forEach(b => { b.g1 = ''; b.g2 = ''; });
}

const Actions = {
  'close-modal'() { ui.closeModal(); },
  'goHome'() {
    /* v5.0.3: بازگشت هوشمند — دکمهٔ بازگشت هر صفحه، جای درست خودش می‌رود:
       صندوق/آشپزخانه → hub کافه (tenants/<slug>/hub.php)
       اپ مدیریت (طراحی منو/میزها/گزارش/تنظیمات) → پنل مدیریت (panel-admin.php)
       بقیه (مشتری/ورود) → صفحهٔ نقش‌ها — رفتار قبلی */
    if (App.role === 'kds' || App.role === 'cashier') {
      location.href = 'hub.php';
      return;
    }
    if (App.role === 'admin') {
      location.href = 'panel-admin.php';
      return;
    }
    App.role = null;
    App.login = { mode: 'login', user: '', pass: '', phone: '', pass2: '', err: '' };
    render();
  },
  'role'(el) {
    const r = el.dataset.role;
    if (r === 'admin' && sessionStorage.getItem('tenAdmin') !== '1') {
      App.role = 'login';
      App.login.mode = 'login';
      render();
      return;
    }
    App.role = r;
    App.cst.flow = 'cart';
    App.cst.done = null;
    render();
  },

  /* ورود به پنل — v5.0.2: همهٔ نقش‌ها با «نام کاربری (موبایل) + رمز» */
  async 'do-login'() {
    const s = App.login;
    if (!s.user.trim() || !s.pass) {
      s.err = 'شمارهٔ موبایل (نام کاربری) و رمز ورود را وارد کنید.';
      render();
      return;
    }
    try {
      const r = await fetch('../../login.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ act: 'login', num: s.user.trim(), identity: s.user.trim(), pass: s.pass, slug: T_SLUG })
      });
      const j = await r.json();
      if (!j.ok) {
        s.err = j.err || 'رمز عبور نادرست است';
        render();
        return;
      }
      if (j.data && j.data.role && j.data.role !== 'admin') {
        /* حساب پرسنلی است — این صفحه دروازهٔ پنل مدیریت است */
        fetch('../../tenants/' + T_SLUG + '/logout.php').catch(() => {});
        s.err = 'این حساب پرسنلی (صندوق/آشپزخانه) است — از لینک‌های ورود سریع کافه (hub) استفاده کنید.';
        s.pass = '';
        render();
        return;
      }
      sessionStorage.setItem('tenAdmin', '1');
      await Store.init();
      App.role = 'admin';
      render();
      ui.toast({ msg: 'خوش آمدید!' });
    } catch (e) {
      s.err = 'خطای ارتباط با سرور';
      render();
    }
  },

  'logout-admin'() {
    sessionStorage.removeItem('tenAdmin');
    fetch('../../tenants/' + T_SLUG + '/logout.php').finally(() => {
      App.role = null;
      render();
      ui.toast({ msg: 'از حساب مدیر خارج شدید' });
    });
  },

  /* بازیابی رمز مدیر — v5.0.1: اندپوینت reset_pass + اکشن‌های صفحهٔ فراموشی رمز
     (صفحه‌ها از قبل بودند ولی اکشن‌ها تعریف نشده بودند — فلوی مرده) */
  'forgot-open'() { App.login.mode = 'forgot'; App.login.err = ''; render(); },
  'forgot-back'() { App.login.mode = 'login'; App.login.err = ''; render(); },
  'forgot-check'() {
    const s = App.login;
    if (!en(s.phone).replace(/\D/g, '')) { s.err = 'شمارهٔ موبایلی که هنگام ثبت‌نام دادید را وارد کنید.'; render(); return; }
    s.mode = 'reset'; s.err = ''; render();
  },
  async 'do-reset'() {
    const s = App.login;
    const pw = s.pass || '';
    if (pw.length < 8 || !/[A-Za-z\u0600-\u06FF]/.test(pw) || !/\d/.test(pw)) {
      s.err = 'رمز حداقل ۸ کاراکتر با حداقل یک حرف و یک عدد باشد.';
      render(); return;
    }
    if (pw !== s.pass2) { s.err = 'تکرار رمز با رمز یکسان نیست.'; render(); return; }
    try {
      await Api.post('reset_pass', { phone: en(s.phone).replace(/\D/g, ''), pass: pw });
      App.login = { mode: 'login', user: en(s.phone).replace(/\D/g, ''), pass: '', phone: s.phone, pass2: '', err: '' };
      render();
      ui.toast({ msg: 'رمز جدید ثبت شد — با همان شماره و رمز تازه وارد شوید' });
    } catch (e) {
      s.err = e.message || 'بازیابی رمز ناموفق بود';
      render();
    }
  },

  /* ویزارد راه‌اندازی */
  'wiz-prev'() { if (App.wiz.step > 0) { App.wiz.step--; render(); } },
  'wiz-venue'(el) { M().venue = el.dataset.v; Store.commit('menu'); render(); },
  'wiz-logo'() { imgTarget = 'logo-wiz'; $('#imgpick').click(); },
  'wiz-logo-del'() { M().brand.logo = ''; Store.commit('menu'); if(typeof applyFavicon === 'function') applyFavicon(); render(); },
  'wiz-pal'(el) {
    const p = PALETTES.find(x => x.id === el.dataset.id);
    if (p) {
      M().theme.colors = { ...p.colors };
      M().theme.paletteId = p.id;
      resetGrads();
      Store.commit('menu');
    }
    render();
  },
  'wiz-tbl'(el) { App.wiz.tbl.want = el.dataset.w === '1'; render(); },
  'wiz-kds'(el) { App.wiz.tbl.kds = el.dataset.w === '1'; render(); },
  'wiz-next'() {
    const st = wizSteps();
    const key = st[Math.min(App.wiz.step, st.length - 1)][0];
    const w = App.wiz;
    if (key === 'brand') {
      /* v5.0.1: نام کافه در ثبت‌نام وارد شده — ویزارد فقط شعار را می‌پرسد.
         نام از طراح منو (کلیک روی نام در پیش‌نمایش) قابل ویرایش است. */
      M().brand.tagline = w.biz.tag.trim() || venueOf().tag;
      w.biz.err = '';
      Store.commit('menu');
    }
    /* v5: گام‌های دسته/محصول از ویزارد حذف شد — منو در پنل ساخته می‌شود */
    if (key === 'space') {
      const t = w.tbl;
      if (t.want && !Store.db.tables.length) {
        const seats = parseInt(en(t.seats), 10) || 4;
        Store.db.tables.push({ id: uid(), no: 1, seats, zone: 'سالن اصلی', status: 'free' });
      }
      SET().kds = !!t.kds;
      Store.commit('tables');
      Store.commit('settings');
    }
    App.wiz.step++;
    render();
  },
  
/* ثبت نهایی ویزارد - استفاده از save_settings که برای سرور معتبر است */
  async 'wiz-finish'() {
    const m = M();
    if (!m.brand.name) m.brand.name = 'کسب‌وکار من';
    if (!m.brand.tagline) m.brand.tagline = venueOf().tag;

    // ۱. تنظیم وضعیت اتمام در جاوااسکریپت
    Store.db.setup = { done: true, at: Date.now() };
    if (!Store.db.settings) Store.db.settings = {};
    Store.db.settings.setup_done = true;

    try {
      // ۲. ذخیره منو
      await Api.post('save_menu', M());

      // ۳. ذخیره وضعیت اتمام با اکشن معتبر save_settings
      await Api.post('save_settings', Store.db.settings);

      // ۴. ثبت «setup.done» سمت سرور — بدون این، هر رفرش دوباره ویزارد را نشان می‌داد
      const finDb = await Api.post('save_setup', Store.db.setup);
      Store.db = finDb || Store.db;          /* پاسخ سرور حالا setup.done=true دارد */
      Store.db.setup = { done: true, at: Store.db.setup?.at || Date.now() };  /* بیمهٔ محلی */

      // ۵. رفتن به پنل مدیریت — لینک اختصاصی تأییدشدهٔ سمت سرور
      sessionStorage.setItem('tenAdmin', '1');
      location.href = 'panel-admin.php?role=admin';
    } catch (e) {
      render();
      ui.toast({ msg: 'خطا در ذخیره‌سازی نهایی: ' + e.message });
    }
  },
  
  'reset-data'() {
    ui.modal(`<p style="line-height:2.1">در نسخهٔ سروری، بازنشانی داده‌ها فقط از پشتیبان JSON ممکن است (از پنل کافه‌دار، بخش پشتیبان).</p>
      <div class="m-acts"><button class="btn ghost" data-act="close-modal">متوجه شدم</button></div>`, { title: 'بازنشانی' });
  },

  'atab'(el) {
    App.adm.tab = el.dataset.tab;
    render();   /* v5.0.5-test.3: تغییر تب = تغییر نمای رندر → خودکار به بالای صفحه */
    /* v5.0.5: اولین باز شدن «تنظیمات» → بررسی رمزهای ضعیف خودکار (یک‌بار) — حالا بخشی از خانه */
    if ((App.adm.tab === 'home' || App.adm.tab === 'settings') && !(Store.db.auth || {}).pwaudit && !App._pwa) {
      App._pwa = true;
      const b = $('#pwauditbtn');
      if (b) Actions['pw-audit-run'](b);
    }
    /* v5.0.5-test.5: پنل پیامکی — ورود به باشگاه مشتریان، صف خودکار عضوهای تازه را تخلیه می‌کند */
    if (App.adm.tab === 'custs') {
      const sm = Store.db.sms || {};
      if (sm.en && sm.auto !== false && (sm.q || 0) > 0 && !App._smsDrain) {
        App._smsDrain = true;
        Api.post('sms_sync', { auto: 1 }).catch(() => {})
          .then(() => Api.get('sms_state'))
          .then(st => { Store.db.sms = st.cfg; if (App.adm.tab === 'custs') render(); })
          .catch(() => {})
          .finally(() => { setTimeout(() => { App._smsDrain = false; }, 20000); });
      }
    }
  },
  'range'(el) {
    App.adm.range = el.dataset.r;
    if (App.adm.tab === 'home') { const w = $('#hd-reports'); if (w) { w.innerHTML = reportsHTML(); return; } }
    render();
  },
  /* v5.0.5-test.1: پرش به بخش‌های صفحهٔ اصلی مدیریت */
  'home-jump'(el) {
    const t = document.getElementById(el.dataset.id);
    if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
  },
  /* ═══ v5.0.5-test.1: پیام‌های سیستم — پنل، خواندن، ارسال دستی مدیر ═══ */
  'notif-open'() {
    const rk = myRoleKey();
    const list = (Store.db.notifs || []).filter(n => n.to === rk).slice(-30).reverse();
    const row = n => `<div class="nrow ${n.read ? '' : 'un'}"><b>${esc(n.title)}</b>${n.body ? `<p>${esc(n.body)}</p>` : ''}<small>${tf.format(n.ts)} · ${dtfD.format(n.ts)}</small></div>`;
    const isAdm = App.role === 'admin';
    ui.modal(`<div class="nlist">${list.length ? list.map(row).join('') : '<p class="dim" style="padding:14px 0">پیامی برای این بخش نیست.</p>'}</div>
      <div class="m-acts" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
       <button class="btn ghost sm" data-act="notif-read-all">${ic('check',13)} همه خوانده شد</button>
       ${isAdm ? `<button class="btn sm" data-act="notif-compose">${ic('send',13)} ارسال پیام به نقش‌ها</button>` : ''}</div>`,
      { title: 'پیام‌های سیستم' });
  },
  'notif-read-all'() {
    Api.post('notifs_read', { to: myRoleKey() }).then(r => {
      Store.db = r.state || r;
      ui.closeModal();
      render();
      ui.toast({ msg: 'همهٔ پیام‌ها خوانده شد ✓' });
    }).catch(e => ui.toast({ msg: e.message }));
  },
  'notif-compose'() {
    const a = App.adm.nb;
    ui.modal(`<p class="mini" style="margin-bottom:8px">این پیام برای نقش انتخابی (در صندوق و آشپزخانه و پنل مدیریت همین کافه) نمایش داده می‌شود.</p>
      <label class="f"><span>مقصد</span><select data-input="nb-to">
        <option value="all"${a.to === 'all' ? ' selected' : ''}>همهٔ نقش‌ها</option>
        <option value="cashier"${a.to === 'cashier' ? ' selected' : ''}>صندوق</option>
        <option value="kitchen"${a.to === 'kitchen' ? ' selected' : ''}>آشپزخانه</option>
        <option value="admin"${a.to === 'admin' ? ' selected' : ''}>مدیریت</option></select></label>
      <label class="f"><span>عنوان پیام</span><input data-input="nb-title" maxlength="80" value="${esc(a.title)}"></label>
      <label class="f"><span>متن پیام (اختیاری)</span><input data-input="nb-body" maxlength="200" value="${esc(a.body)}"></label>
      <div class="m-acts"><button class="btn" data-act="notif-send">${ic('send',13)} ارسال پیام</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`,
      { title: 'ارسال پیام به نقش‌ها' });
  },
  'notif-send'() {
    const a = App.adm.nb;
    if (a.title.trim().length < 2) { ui.toast({ msg: 'متن پیام را بنویسید' }); return; }
    Api.post('notify_send', { to: a.to, title: a.title.trim(), body: a.body.trim() }).then(db => {
      Store.db = db;
      App.adm.nb = { to: a.to, title: '', body: '' };
      ui.closeModal();
      render();
      ui.toast({ msg: 'پیام برای نقش‌ها ارسال شد ✓' });
    }).catch(e => ui.toast({ msg: e.message }));
  },
  'acc-tgl'(el) {
    const s = el.dataset.sec;
    App.adm.acc = App.adm.acc === s ? '' : s;
    render();
  },
  /* ═══ v5.0.5: بررسی رمزهای ضعیف — فقط مدیر؛ نتیجه در auth.pwaudit کش می‌شود ═══ */
  'pw-audit-run'(el) {
    if (el) { el.disabled = true; el.textContent = 'در حال بررسی…'; }
    Api.post('pw_audit', { force: true }).then(r => {
      Store.db.auth = Store.db.auth || {};
      Store.db.auth.pwaudit = { ts: r.ts, admin: r.admin, users: r.users };
      App._pwa = false;
      render();
      const weak = (r.admin && r.admin.weak) || Object.keys(r.users || {}).some(id => r.users[id] && r.users[id].weak);
      ui.toast({ msg: weak ? '⚠️ رمز ضعیف پیدا شد — بخش «امنیت رمزها» را ببینید' : '✅ بررسی کامل شد — رمز ضعیفی پیدا نشد' });
    }).catch(e => {
      App._pwa = false;
      if (el) { el.disabled = false; el.textContent = 'بررسی قدرت رمزها'; }
      ui.toast({ msg: e.message });
    });
  },

  'theme'(el) { M().theme[el.dataset.k] = el.dataset.v; Store.commit('menu'); render(); },
  'toggle-live'() { App.adm.live = !App.adm.live; render(); },
  'template-apply'() {
    const v = venueOf();
    App._tpl = { cats: M().cats, tagline: M().brand.tagline };
    ui.modal(`<p style="line-height:2.2">منو با قالب «${v.n}» جایگزین می‌شود (${fa(v.cats.length)} دستهٔ آماده). امکان بازگرداندن با یک کلیک هست.</p>
      <div class="m-acts"><button class="btn" data-act="template-go">جایگزین کن</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`, { title: 'اعمال قالب منو' });
  },

  /* ═══ v5.0.4: درون‌ریزی اکسل ═══ */
  'excel-open'() { /* v5.0.4-test.2: دکمهٔ همیشه‌نمایان بالای ستون راست — بازکردن + پرش به بخش */
    App.adm.acc = 'درون‌ریزی از اکسل';
    render();
    const box = $('.tool-g.open');
    if (box) {
      box.scrollIntoView({ behavior: 'smooth', block: 'center' });
      box.classList.add('flash');
      setTimeout(() => box.classList.remove('flash'), 1700);
    }
  },
  'excel-pick'() { $('#excelpick').click(); },
  'excel-sample'() { location.href = 'api/excel.php?get=sample'; },
  'excel-tpl'() { location.href = 'api/excel.php?get=template'; },
  'excel-apply'(el) {
    const xls = App.adm.xls;
    if (!xls || !Array.isArray(xls.cats) || !xls.cats.length) { ui.toast({ msg: 'پیش‌نمایش منقضی شده — فایل را دوباره بارگذاری کنید' }); return; }
    const mode = el.dataset.mode === 'append' ? 'append' : 'replace';
    const btns = $$('.m-acts .btn');
    btns.forEach(b => { b.disabled = true; });
    Api.post('excel_apply', { cats: xls.cats, mode }).then(db => {
      Store.db = db;
      App.adm.xls = null;
      ui.closeModal();
      render();
      const st = (xls.stats && xls.stats.items) || 0;
      ui.toast({ msg: mode === 'replace'
        ? `منو از فایل اکسل جایگزین شد ✓ (${fa(xls.stats.cats)} دسته، ${fa(st)} محصول)`
        : `آیتم‌های اکسل به منو اضافه شدند ✓ (${fa(st)} محصول)`, ttl: 8000 });
    }).catch(e => {
      btns.forEach(b => { b.disabled = false; });
      ui.toast({ msg: 'ذخیرهٔ اکسل ناموفق: ' + e.message, ttl: 10000 });
    });
  },
  'template-go'() {
    const m = M(), prev = App._tpl;
    if (!prev) return;
    m.cats = templateCats(m.venue);
    m.brand.tagline = venueOf().tag;
    Store.commit('menu');
    ui.closeModal();
    render();
    ui.toast({
      msg: 'منو با قالب «' + venueOf().n + '» بازسازی شد',
      ttl: 8000,
      action: {
        label: 'بازگردان',
        fn() {
          m.cats = prev.cats;
          m.brand.tagline = prev.tagline;
          Store.commit('menu');
          render();
        }
      }
    });
  },

  'logo-pick'() { imgTarget = 'logo'; $('#imgpick').click(); },
  'logo-del'() { M().brand.logo = ''; Store.commit('menu'); if(typeof applyFavicon === 'function') applyFavicon(); render(); ui.toast({ msg: 'لوگو حذف شد' }); },

  'sch-x'(el) {
    const i = +el.dataset.i;
    const s = M().brand.sched;
    if (!s || !s[i]) return;
    s[i].x = !s[i].x;
    Store.commit('menu');
    render();
  },

  'head-mode'(el) { M().theme.headMode = el.dataset.m; Store.commit('menu'); render(); },
  'head-img'() { imgTarget = 'headimg'; $('#imgpick').click(); },
  'head-img-del'() { M().theme.headImg = ''; M().theme.headMode = 'plain'; Store.commit('menu'); render(); },
  'bnr-add'() {
    M().banners.push({ id: uid(), txt: 'سرآغازهٔ تبلیغاتی', sub: 'مثلاً: تا پایان هفته با ۲۰٪ تخفیف', img: '', g1: '', g2: '', link: null });
    Store.commit('menu');
    render();
  },
  'bnr-img'(el) { imgTarget = 'bnr:' + el.dataset.id; $('#imgpick').click(); },
  'bnr-del'(el) {
    const arr = M().banners;
    const i = arr.findIndex(x => x.id === el.dataset.id);
    if (i < 0) return;
    const [b] = arr.splice(i, 1);
    Store.commit('menu');
    render();
    ui.toast({
      msg: 'بنر حذف شد',
      ttl: 6000,
      action: { label: 'بازگردان', fn() { arr.splice(i, 0, b); Store.commit('menu'); render(); } }
    });
  },
  'bnr-link'(el) { destPicker(el.dataset.id); },
  'bnr-dest'(el) {
    const b = M().banners.find(x => x.id === el.dataset.bid);
    if (b) b.link = el.dataset.id ? { type: 'cat', id: el.dataset.id } : null;
    Store.commit('menu');
    ui.closeModal();
    render();
  },
  'bnr-gclr'(el) {
    const b = M().banners.find(x => x.id === el.dataset.id);
    if (b) { b.g1 = ''; b.g2 = ''; Store.commit('menu'); render(); }
  },
  'bnr-go'(el) {
    const b = M().banners.find(x => x.id === el.dataset.id);
    if (b && b.link) {
      const s = document.getElementById('sec-' + b.link.id);
      if (s) s.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  },
  'bnr-dot'(el) {
    const zone = el.closest('.bnr-zone');
    const c = zone && zone.querySelector('.bnrs');
    if (!c) return;
    const cards = [...c.querySelectorAll(':scope > .bnr-w')];
    const i = +el.dataset.i || 0;
    c.dataset.bi = i;
    if (cards[i]) cards[i].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    syncDots();
  },
  'mbg-mode'(el) { M().theme.bgMode = el.dataset.m; Store.commit('menu'); render(); },
  'mbg-grad'(el) { M().theme.bgMode = 'gradient'; Store.commit('menu'); render(); },
  'mbg-img'() { imgTarget = 'menubg'; $('#imgpick').click(); },
  'mbg-video'() { imgTarget = 'menubg-vid'; $('#imgpick').click(); },
  'mbg-vid-del'() { killBgVideo(); M().theme.bgMode = 'color'; Store.commit('menu'); render(); },

  'pal-set'(el) {
    const id = el.dataset.id, m = M();
    const p = PALETTES.find(x => x.id === id) || (m.customPalettes || []).find(x => x.id === id);
    if (p) { m.theme.colors = { ...p.colors }; resetGrads(); }
    m.theme.paletteId = id;
    Store.commit('menu');
    render();
  },
  'pal-save'() {
    const m = M();
    if (!Array.isArray(m.customPalettes)) m.customPalettes = [];
    const nameEl = $('#pal-name');
    const name = (nameEl && nameEl.value.trim()) || 'پالت من';
    const p = { id: uid(), name, colors: { ...m.theme.colors } };
    m.customPalettes.push(p);
    m.theme.paletteId = p.id;
    Store.commit('palettes');
    render();
    ui.toast({ msg: 'پالت «' + name + '» ذخیره شد' });
  },
  'pal-del'(el) {
    const m = M();
    const arr = m.customPalettes || [];
    const i = arr.findIndex(x => x.id === el.dataset.id);
    if (i < 0) return;
    const [p] = arr.splice(i, 1);
    if (m.theme.paletteId === p.id) m.theme.paletteId = 'custom';
    Store.commit('palettes');
    render();
  },

  'jump-cat'(el) {
    const s = document.getElementById('sec-' + el.dataset.cat);
    if (s) s.scrollIntoView({ behavior: 'smooth', block: 'start' });
  },
  'jump-item'(el) {
    const s = document.getElementById('it-' + el.dataset.id);
    if (s) s.scrollIntoView({ behavior: 'smooth', block: 'center' });
  },
  'cat-add'() {
    const c = { id: uid(), name: 'دستهٔ تازه', items: [] };
    M().cats.push(c);
    Store.commit('menu');
    render();
    const t = document.querySelector(`[data-ce="catname"][data-cat="${c.id}"]`);
    if (t) t.focus();
  },
  'cat-up'(el) { mv(M().cats, el.dataset.cat, -1); },
  'cat-down'(el) { mv(M().cats, el.dataset.cat, 1); },
  'cat-del'(el) {
    const arr = M().cats, i = arr.findIndex(c => c.id === el.dataset.cat);
    if (i < 0) return;
    const [c] = arr.splice(i, 1);
    Store.commit('menu');
    ui.toast({
      msg: `دستهٔ «${c.name}» حذف شد`,
      ttl: 6000,
      action: { label: 'بازگردان', fn() { arr.splice(i, 0, c); Store.commit('menu'); render(); } }
    });
  },
  'item-add'(el) {
    const c = findCat(el.dataset.cat);
    if (!c) return;
    const it = { id: uid(), name: 'آیتم تازه', desc: 'توضیح کوتاه', price: 80000, img: '', cost: 0, stock: null, opts: [], tags: defaultTags() };
    c.items.push(it);
    Store.commit('menu');
    render();
    const t = document.querySelector(`[data-ce="iname"][data-item="${it.id}"]`);
    if (t) {
      t.focus();
      if (document.execCommand) document.getSelection().selectAllChildren(t);
    }
  },
  'item-up'(el) {
    const c = M().cats.find(c => c.items.some(x => x.id === el.dataset.id));
    if (c) mv(c.items, el.dataset.id, -1);
  },
  'item-down'(el) {
    const c = M().cats.find(c => c.items.some(x => x.id === el.dataset.id));
    if (c) mv(c.items, el.dataset.id, 1);
  },
  'item-del'(el) {
    const id = el.dataset.id;
    for (const c of M().cats) {
      const i = c.items.findIndex(x => x.id === id);
      if (i > -1) {
        const [it] = c.items.splice(i, 1);
        Store.commit('menu');
        ui.toast({
          msg: `«${it.name}» حذف شد`,
          ttl: 6000,
          action: { label: 'بازگردان', fn() { c.items.splice(i, 0, it); Store.commit('menu'); render(); } }
        });
        break;
      }
    }
  },
  'item-img'(el) { imgTarget = el.dataset.id; $('#imgpick').click(); },
  'item-img-del'(el) {
    const it = findItem(el.dataset.id);
    if (it) { it.img = ''; Store.commit('menu'); }
  },
  'item-info'(el) { itemInfoModal(el.dataset.id); },
  'op-add'() {
    const id = App.adm._it;
    const it = findItem(id);
    if (!it) return;
    const nm = ($('#op-name') && $('#op-name').value.trim()) || '';
    const pr = parseInt(moneyRaw($('#op-price') ? $('#op-price').value : ''), 10) || 0;
    if (nm.length < 2) { ui.toast({ msg: 'نام گزینه را وارد کنید' }); return; }
    if (!Array.isArray(it.opts)) it.opts = [];
    it.opts.push({ id: uid(), name: nm, price: pr });
    Store.commit('menu');
    itemInfoModal(id);
    ui.toast({ msg: `گزینهٔ «${nm}» اضافه شد` });
  },
  'op-del'(el) {
    const it = findItem(App.adm._it);
    if (!it) return;
    it.opts = (it.opts || []).filter(o => o.id !== el.dataset.id);
    Store.commit('menu');
    itemInfoModal(it.id);
  },
  'rv-ok'(el) {
    Api.post('review_toggle', { id: el.dataset.id }).then(db => {
      Store.db = db;
      itemInfoModal(App.adm._it);
    });
  },
  'rv-del'(el) {
    Api.post('review_del', { id: el.dataset.id }).then(db => {
      Store.db = db;
      itemInfoModal(App.adm._it);
    });
  },

  'tag-toggle'(el) {
    /* v5.0.1: فقط چیپ همین کارت درجا عوض می‌شود — بدون رندر کل صفحه (کارت باز می‌ماند،
       اسکرول و تایپِ درجا به‌هم نمی‌ریزد). بستن کارت فقط با دکمهٔ «تایید». */
    const it = findItem(el.dataset.item);
    if (!it) return;
    const tg = el.dataset.tag;
    if (!it.tags) it.tags = defaultTags();
    if (tg === 'off') { openOffModal(it); return; }
    it.tags[tg] = !it.tags[tg];
    el.classList.toggle('on', !!it.tags[tg]);
    Store.commit('menu');
  },
  'tag-done'(el) {
    /* دکمهٔ «تایید» روی کارت محصول — فقط با این دکمه کارت بسته می‌شود */
    const id = el.dataset.item;
    const it = findItem(id);
    if (!it) return;
    if (!App.adm.tgClosed) App.adm.tgClosed = {};
    App.adm.tgClosed[id] = true;
    Store.commit('menu');
    refreshCard(id, true);
    ui.toast({ msg: `برچسب‌های «${it.name}» ذخیره شد ✓` });
  },
  'tag-open'(el) {
    const id = el.dataset.item;
    if (!findItem(id)) return;
    if (App.adm.tgClosed) delete App.adm.tgClosed[id];
    refreshCard(id, true);
  },
  'tag-off'(el) {
    const it = findItem(el.dataset.item);
    if (!it) return;
    it.tags.off = +el.dataset.v || 0;
    Store.commit('menu');
    ui.closeModal();
    refreshCard(it.id, true);
  },
  'tag-off-apply'(el) {
    const it = findItem(el.dataset.item);
    if (!it) return;
    const v = parseInt(en($('#off-inp').value), 10) || 0;
    if (v < 1 || v > 90) { ui.toast({ msg: 'درصد تخفیف بین ۱ تا ۹۰ باشد' }); return; }
    it.tags.off = v;
    Store.commit('menu');
    ui.closeModal();
    refreshCard(it.id, true);
    ui.toast({ msg: `تخفیف ٪${fa(v)} روی «${it.name}» اعمال شد` });
  },

  /* میزها */
  'table-edit'(el) {
    const t = Store.db.tables.find(x => x.id === el.dataset.id);
    if (t) tableModal(t);
  },
  'table-status'(el) {
    const t = Store.db.tables.find(x => x.id === el.dataset.id);
    if (!t) return;
    t.status = TST[el.dataset.st] ? el.dataset.st : 'free';
    Store.commit('tables');
    ui.closeModal();
    render();
  },
  'table-del'(el) {
    const arr = Store.db.tables;
    const i = arr.findIndex(x => x.id === el.dataset.id);
    if (i < 0) return;
    const [t] = arr.splice(i, 1);
    Store.commit('tables');
    ui.closeModal();
    render();
    ui.toast({
      msg: `میز ${fa(t.no)} حذف شد`,
      ttl: 6000,
      action: { label: 'بازگردان', fn() { arr.splice(i, 0, t); Store.commit('tables'); render(); } }
    });
  },
  /* v5.0.4-test.5: ساخت میز اول ظرفیت را می‌پرسد (مودال ظرفیت + منطقه) */
  'table-add'() {
    const ts = Store.db.tables;
    const no = ts.reduce((m, t) => Math.max(m, t.no), 0) + 1;
    const g = App.adm.tnew;
    ui.modal(`<p class="mini" style="margin-bottom:8px">میز ${fa(no)} ساخته می‌شود — ظرفیت و منطقه را تنظیم کنید.</p>
      <label class="f"><span>ظرفیت میز (نفر)</span><input data-input="tn-seats" inputmode="numeric" value="${esc(g.seats)}"></label>
      <label class="f"><span>منطقه (سالن، تراس، پشت‌بام…)</span><input data-input="tn-zone" value="${esc(g.zone)}"></label>
      <div class="m-acts"><button class="btn" data-act="table-add-go" data-no="${no}">${ic('check',13)} ساختن میز</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`, { title: 'میز تازه' });
  },
  'table-add-go'(el) {
    const g = App.adm.tnew;
    const seats = Math.max(1, Math.min(40, parseInt(en(g.seats), 10) || 4));
    const zone = g.zone.trim() || 'سالن اصلی';
    const no = parseInt(el.dataset.no, 10) || Store.db.tables.reduce((m, t) => Math.max(m, t.no), 0) + 1;
    Store.db.tables.push({ id: uid(), no, seats, zone, status: 'free' });
    Store.commit('tables');
    ui.closeModal();
    render();
    ui.toast({ msg: `میز ${fa(no)} (${fa(seats)} نفره — ${zone}) اضافه شد` });
  },
  'table-group'() {
    const g = App.adm.grp;
    ui.modal(`<label class="f"><span>تعداد میز</span><input data-input="tg-count" inputmode="numeric" value="${esc(g.count)}"></label>
      <label class="f"><span>ظرفیت هر میز (نفر)</span><input data-input="tg-seats" inputmode="numeric" value="${esc(g.seats)}"></label>
      <label class="f"><span>منطقه</span><input data-input="tg-zone" value="${esc(g.zone)}"></label>
      <div class="m-acts"><button class="btn" data-act="table-group-go">افزودن</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`, { title: 'افزودن گروهی میز' });
  },
  'table-group-go'() {
    const g = App.adm.grp;
    const n = parseInt(en(g.count), 10) || 0;
    const seats = parseInt(en(g.seats), 10) || 4;
    const zone = g.zone.trim() || 'سالن';
    if (n < 1 || n > 24) { ui.toast({ msg: 'تعداد بین ۱ تا ۲۴' }); return; }
    const ts = Store.db.tables;
    let no = ts.reduce((m, t) => Math.max(m, t.no), 0);
    const first = no + 1;
    for (let i = 0; i < n; i++) ts.push({ id: uid(), no: ++no, seats, zone, status: 'free' });
    Store.commit('tables');
    ui.closeModal();
    render();
    ui.toast({ msg: `${fa(n)} میز (${fa(first)} تا ${fa(no)}) به «${zone}» اضافه شد` });
  },
  'qr-open'() { qrModal(); },
  'qr-print'() { ui.closeModal(); qrPrint(); },
  'resv-new'() {
    const free = Store.db.tables.filter(t => t.status === 'free');
    if (!free.length) { ui.toast({ msg: 'میز خالی برای رزرو نیست' }); return; }
    const a = App.adm.rz;
    a.table = free[0].id;
    ui.modal(`<label class="f"><span>میز</span><select data-input="rz-table">${free.map(t => `<option value="${t.id}">میز ${fa(t.no)} · ${esc(t.zone)} · ${fa(t.seats)} نفره</option>`).join('')}</select></label>
      <label class="f"><span>نام مشتری</span><input data-input="rz-name" value="${esc(a.name)}"></label>
      <label class="f"><span>موبایل</span><input data-input="rz-phone" inputmode="numeric" value="${esc(a.phone)}"></label>
      <div style="display:flex;gap:10px">
        <label class="f" style="flex:1"><span>ساعت (امروز)</span><input data-input="rz-time" type="time" value="${esc(a.time)}"></label>
        <label class="f" style="flex:1"><span>تعداد نفر</span><input data-input="rz-seats" inputmode="numeric" value="${esc(a.seats)}"></label></div>
      <div class="m-acts"><button class="btn" data-act="resv-add">ثبت رزرو</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`, { title: 'رزرو میز' });
  },
  /* v5.0.5-test.1: رزرو — ساخت دستی صندوق (رزرو و تأیید یک‌جا) با پیام به مشتری */
  'resv-add'() {
    const a = App.adm.rz;
    const t = Store.db.tables.find(x => x.id === a.table);
    if (!t || !a.name.trim()) { ui.toast({ msg: 'نام مشتری را وارد کنید' }); return; }
    const btns = $$('.m-acts .btn');
    btns.forEach(b => { b.disabled = true; });
    Api.post('reserve_create', {
      name: a.name.trim(),
      phone: en(a.phone).replace(/\D/g, ''),
      time: a.time || '19:00',
      persons: parseInt(en(a.seats), 10) || 2
    }).then(r => Api.post('reserve_set', { id: r.id, st: 'active', tableId: t.id })).then(db => {
      Store.db = db;
      App.adm.rz = { table: '', name: '', phone: '', time: '', seats: '۲' };
      ui.closeModal();
      render();
      ui.toast({ msg: `رزرو میز ${fa(t.no)} ثبت و تأیید شد — پیام به مشتری رفت` });
    }).catch(e => {
      btns.forEach(b => { b.disabled = false; });
      ui.toast({ msg: e.message });
    });
  },
  /* تأیید رزروِ در انتظار — تخصیص خودکار اولین میز خالی + پیام به مشتری */
  'resv-ok'(el) {
    Api.post('reserve_set', { id: el.dataset.id, st: 'active' }).then(db => {
      Store.db = db; ui.closeModal(); render();
      ui.toast({ msg: 'رزرو تأیید شد — پیام به مشتری رفت ✓' });
    }).catch(e => ui.toast({ msg: e.message }));
  },
  'resv-sit'(el) {
    Api.post('reserve_set', { id: el.dataset.id, st: 'done' }).then(db => {
      Store.db = db; ui.closeModal(); render();
      ui.toast({ msg: 'میز به مهمان تحویل شد — نوش جان!' });
    }).catch(e => ui.toast({ msg: e.message }));
  },
  'resv-cancel'(el) {
    Api.post('reserve_set', { id: el.dataset.id, st: 'cancel' }).then(db => {
      Store.db = db; ui.closeModal(); render();
      ui.toast({ msg: 'رزرو لغو شد — میز آزاد شد' });
    }).catch(e => ui.toast({ msg: e.message }));
  },

  /* شیفت */
  'shift-open'() {
    App.adm.shu = { user: '', pin: '' };
    const users = Store.db.users;
    ui.modal(`<label class="f"><span>صندوق‌دار</span><select data-input="sh-user">
      <option value="">— انتخاب کنید —</option>
      ${users.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}
      <option value="__free">نام دلخواه…</option></select></label>
      <label class="f" id="sh-free-w" style="display:none"><span>نام دلخواه</span><input data-input="sh-free"></label>
      <div class="m-acts"><button class="btn" data-act="shift-do">افتتاح شیفت</button><button class="btn ghost" data-act="close-modal">انصراف</button></div>`,
      { title: 'افتتاح شیفت' });
    const sel = $('[data-input="sh-user"]');
    if (sel) sel.addEventListener('change', () => {
      const w = $('#sh-free-w');
      if (w) w.style.display = sel.value === '__free' ? 'block' : 'none';
    });
  },
  'shift-do'() {
    const s = App.adm.shu;
    const sel = Store.db.users.find(u => u.id === s.user);
    let name = '';
    if (s.user === '__free') name = (s.free || '').trim();
    else if (sel) name = sel.name;
    if (!name) { ui.toast({ msg: 'نام صندوق‌دار را مشخص کنید' }); return; }
    if (sel && sel.pin && en(s.pin) !== sel.pin) { ui.toast({ msg: 'PIN اشتباه است' }); return; }
    Store.db.shift = { name, ts: Date.now() };
    Store.commit('shift');
    ui.closeModal();
    render();
    ui.toast({ msg: `شیفت ${name} افتتاح شد — فروش موفق!` });
  },
  'shift-x'() {
    if (!Store.db.shift) return;
    ui.modal(`${shiftReport()}
      <div class="m-acts"><button class="btn danger" data-act="shift-close">بستن شیفت</button><button class="btn ghost" data-act="close-modal">ادامهٔ شیفت</button></div>`,
      { title: 'گزارش شیفت (X/Z)' });
  },
  'shift-close'() {
    Store.db.shift = null;
    Store.commit('shift');
    ui.closeModal();
    render();
    ui.toast({ msg: 'شیفت بسته شد' });
  },

  /* جستجو و فیلتر */
  'mn-filter'(el) { App.cst.sf = el.dataset.f; mnRefreshToolsBody(); },

  /* آپشن */
  'opt-open'(el) { openOptModal(el.dataset.id); },
  'opt-apply'(el) {
    const id = el.dataset.id;
    const it = findItem(id);
    if (!it) return;
    /* v5.0.4-test.5: تعداد انتخابی در مودال گزینه‌ها (۱ تا ۹۹) */
    const q = Math.max(1, Math.min(99, App.cst.optQ || 1));
    const sel = $$('#modal-root .oprow input:checked').map(x => x.value);
    const key = sel.slice().sort().join('|');
    let l = Store.cart.find(c => c.id === id && (c.opts || []).slice().sort().join('|') === key);
    if (l) l.qty += q;
    else Store.cart.push({ k: uid(), id, qty: q, opts: sel.slice() });
    App.cst.optQ = 1;
    saveCart();
    ui.closeModal();
    refreshItemCtrl(id);
    ui.toast({ msg: q > 1 ? `${fa(q)} عدد به سبد اضافه شد` : 'به سبد اضافه شد' });
  },

  /* ═══ باشگاه مشتریان (v5.0.4-test.5) ═══ */
  'cust-xls'() {
    ui.toast({ msg: 'در حال ساخت فایل اکسل مشتریان…' });
    window.location.href = 'api/excel.php?get=customers';
  },

  /* ═══ v5.0.5-test.5: پنل پیامکی — اتصال API، همگام‌سازی، پیامک گروهی ═══ */
  'sms-cfg'() { smsCfgModal(); },
  /* PAY_UI_V1 */
  'pay-cfg'() { payCfgModal(); },
  'pay-cfg-save'() {
    const b = payFormBody();
    if (!b) return;
    Api.post('payment_cfg_save', b).then(db => {
      Store.db.payment = db.cfg;
      render();
      ui.toast({ msg: db.msg || 'ذخیره شد' });
    }).catch(e => ui.toast({ msg: e.message || 'خطا در ذخیره' }));
  },
  'pay-test'() {
    const b = payFormBody();
    if (!b) return;
    Api.post('payment_cfg_save', b)
      .then(db => { Store.db.payment = db.cfg; return Api.post('payment_test'); })
      .then(r => Api.get('payment_state').then(st => {
        Store.db.payment = st.cfg;
        const o = document.getElementById('pay-test-out');
        if (o) {
          const t = st.cfg.st && st.cfg.st.lastTest;
          o.textContent = t ? (t.ok ? '✅ ' + t.msg : '⚠️ ' + t.msg) : '';
          o.style.color = t && t.ok ? '#4F7A3D' : '#B4531F';
        }
        render();   /* FIX_RENDER_V1 */
        ui.toast({ msg: r.msg || 'تست انجام شد' });
      }))
      .catch(e => ui.toast({ msg: e.message || 'خطا در تست اتصال' }));
  },
  /* پایان PAY_UI_V1 */
  'sms-cfg-save'(el) {
    const b = smsFormBody();
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال ذخیره…';
    Api.post('sms_cfg_save', b).then(db => {
      Store.db.sms = db.cfg;
      ui.closeModal();
      render();
      ui.toast({ msg: 'تنظیمات پنل پیامکی ذخیره شد' });
    }).catch(e => {
      el.disabled = false;
      el.innerHTML = ic('check', 14) + ' ذخیره';
      ui.toast({ msg: e.message });
    });
  },
  'sms-test'(el) {
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال تست…';
    Api.post('sms_cfg_save', smsFormBody())   /* اول ذخیره، بعد تست با همان تنظیمات */
      .then(db => { Store.db.sms = db.cfg; return Api.post('sms_test'); })
      .then(r => Api.get('sms_state').then(st => { Store.db.sms = st.cfg; return r; }))
      .then(r => {
        const o = $('#sms-test-out');
        if (o) o.textContent = (r.ok ? '✅ ' : '⚠️ ') + r.msg;
        el.disabled = false;
        el.innerHTML = ic('refresh', 14) + ' ذخیره و تست اتصال';
        render();
      })
      .catch(e => {
        const o = $('#sms-test-out');
        if (o) o.textContent = '⚠️ ' + e.message;
        el.disabled = false;
        el.innerHTML = ic('refresh', 14) + ' ذخیره و تست اتصال';
      });
  },
  'sms-sync'(el) {
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> همگام‌سازی…';
    Api.post('sms_sync', {}).then(r => {
      ui.toast({ msg: r.msg });
      return Api.get('sms_state');
    }).then(st => { Store.db.sms = st.cfg; render(); })
      .catch(e => { el.disabled = false; el.innerHTML = ic('refresh', 13) + ' همگام‌سازی'; ui.toast({ msg: e.message }); });
  },
  async 'sms-sync-all'(el) {
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> همگام‌سازی کامل…';
    let r = null;
    try {
      for (let i = 0; i < 12; i++) {          /* هر بار حداکثر ۴۰ عضو — تا خالی شدن صف */
        r = await Api.post('sms_sync', { all: 1 });
        if (!(r.left > 0)) break;
      }
      const st = await Api.get('sms_state');
      Store.db.sms = st.cfg;
      ui.toast({ msg: (r && r.msg) || 'همگام‌سازی کامل انجام شد' });
    } catch (e) { ui.toast({ msg: e.message }); }
    render();
  },
  'sms-bulk'() { smsSendModal(); },
  'sms-bulk-go'(el) {
    const t = $('#sms-bulk-txt').value.trim();
    if (!t) { ui.toast({ msg: 'متن پیامک را بنویسید' }); return; }
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال ارسال…';
    Api.post('sms_bulk_send', { txt: t, all: 1 }).then(r => {
      ui.closeModal();
      ui.toast({ msg: r.msg });
    }).catch(e => {
      el.disabled = false;
      el.innerHTML = ic('send', 14) + ' ارسال';
      ui.toast({ msg: e.message });
    });
  },

  /* سبد مشتری */
  'tbl-clear'() { App.cst.tableNo = null; render(); },
  'promo-apply'() {
    const inp = $('#promo-inp');
    const code = en(inp ? inp.value : '').trim().toUpperCase();
    const p = Store.db.promos.find(x => x.on && x.code === code);
    if (!p) { ui.toast({ msg: 'کد تخفیف معتبر نیست' }); return; }
    App.cst.form.promo = { code: p.code, off: p.off };
    render();
    ui.toast({ msg: `کد ${p.code} اعمال شد — ٪${fa(p.off)} تخفیف` });
  },
  'promo-clear'() { App.cst.form.promo = null; render(); },
  'tip-set'(el) { App.cst.form.tip = +el.dataset.v || 0; App.cst.form.tipFix = 0; render(); },
  'cart-add'(el, fly) {
    const id = el.dataset.id;
    const it = findItem(id);
    if (it && it.tags && it.tags.soldout) { ui.toast({ msg: 'این آیتم فعلاً ناموجود است' }); return; }
    let l = Store.cart.find(c => c.id === id && !(c.opts && c.opts.length));
    l ? l.qty++ : Store.cart.push({ k: uid(), id, qty: 1, opts: [] });
    saveCart();
    refreshItemCtrl(id);
    if (!fly) flyToCart(el);
  },
  /* v5.0.4-test.5: به‌روزرسانی زنده — در تب سبد کل صفحه رندر می‌شود تا تعداد و جمع‌ها «در لحظه» تغییر کنند؛
     در تب منو همان کنترلِ همان محصول + نوار سبد زنده به‌روز می‌شود */
  'cart-inc'(el) {
    const l = Store.cart.find(c => c.k === el.dataset.k);
    if (!l) return;
    l.qty++; saveCart();
    if (App.cst.tab === 'cart' && App.cst.flow === 'cart') render(); else refreshItemCtrl(l.id);
  },
  'cart-dec'(el) {
    const l = Store.cart.find(c => c.k === el.dataset.k);
    if (!l) return;
    l.qty > 1 ? l.qty-- : Store.cart = Store.cart.filter(c => c !== l);
    saveCart();
    if (App.cst.tab === 'cart' && App.cst.flow === 'cart') render(); else refreshItemCtrl(l.id);
  },
  'goto-cart'() { App.cst.tab = 'cart'; App.cst.flow = 'cart'; render(); },
  'ctab'(el) { App.cst.tab = el.dataset.tab; render(); },
  'to-pay'() {
    const f = App.cst.form;
    const ph = en(f.phone).replace(/\D/g, '');
    if (!f.name.trim()) { f.err = 'نام خود را وارد کنید.'; render(); return; }
    if (!/^09\d{9}$/.test(ph)) { f.err = 'شمارهٔ موبایل معتبر نیست — مثل ۰۹۱۲۱۲۳۴۵۶۷'; render(); return; }
    f.phone = ph;
    f.err = '';
    App.cst.flow = 'pay';
    render();
  },
  'back-cart'() { App.cst.flow = 'cart'; render(); },
  /* v5.0.5-test.1: انتخاب روش پرداخت از مشتری حذف شد — فقط درگاه آنلاین؛ نقدی/کارتخوان فقط صندوق */
  'back-menu'() { App.cst.flow = 'cart'; App.cst.done = null; App.cst.tab = 'menu'; render(); },
  'reorder'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    let n = 0;
    o.items.forEach(i => {
      const it = findItem(i.id);
      if (it && !it.tags.soldout) {
        const l = Store.cart.find(c => c.id === it.id && !(c.opts && c.opts.length));
        l ? l.qty += i.qty : Store.cart.push({ k: uid(), id: it.id, qty: i.qty, opts: [] });
        n += i.qty;
      }
    });
    saveCart();
    App.cst.tab = 'menu';
    render();
    ui.toast({ msg: n ? `${fa(n)} قلم به سبد اضافه شد` : 'آیتم‌های این سفارش دیگر موجود نیستند' });
  },

  /* ثبت نهایی سفارش مشتری */
  async 'pay-go'(el) {
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال ثبت…';
    try {
      const t = totals(), f = App.cst.form;
      const r = await Api.post('order_create', {
        items: t.lines.map(l => ({ id: l.id, qty: l.qty, opts: l.opts || [] })),
        name: f.name,
        phone: f.phone,
        loc: (f.loc || '').trim().slice(0, 300),
        method: 'online',   /* v5.0.5-test.1: مشتری فقط پرداخت آنلاین */
        promo: f.promo ? f.promo.code : null,
        usePts: f.usePts,
        tip: f.tip || 0,
        tipFix: f.tipFix > 0 ? Math.round(f.tipFix) : 0,
        tipNote: (f.tipNote || '').trim().slice(0, 140),
        tableNo: App.cst.tableNo || 0
      });
      App.cst.flow = 'done';
      App.cst.done = { num: r.num, total: r.total, earned: r.earned, method: f.method, tableNo: App.cst.tableNo, subno: r.subno || 0 };
      Store.cart = [];
      saveCart();
      f.promo = null;
      f.usePts = false;
      f.tip = 0;
      f.tipFix = 0;
      f.tipNote = '';
      f.err = '';
      await Store.init();
      render();
      ui.toast({ msg: 'پرداخت با موفقیت انجام شد' });
    } catch (e) {
      el.disabled = false;
      el.textContent = 'تلاش مجدد';
      ui.toast({ msg: e.message });
    }
  },

  /* ورود/عضویت مشتری باشگاه */
  'login'() {
    const s = App.cst.lg;
    const ph = en(s.phone).replace(/\D/g, '');
    if (!/^09\d{9}$/.test(ph)) { s.err = 'شمارهٔ موبایل معتبر نیست.'; render(); return; }
    let c = memberByPhone(ph);
    if (!c) {
      if (!s.name.trim()) { s.err = 'عضو نیستید — برای عضویت، نامتان را بنویسید.'; render(); return; }
      c = { phone: ph, name: s.name.trim(), points: 0, since: Date.now() };
      Store.db.customers.push(c);
      ui.toast({ msg: 'به باشگاه مشتریان خوش آمدید' });
    }
    Store.cust = c;
    localStorage.setItem(KEYS.cust, ph);
    App.cst.lg = { phone: '', name: '', err: '' };
    render();
  },
  'logout-cust'() { Store.cust = null; localStorage.removeItem(KEYS.cust); render(); },

  /* ═══ v5.0.5-test.1: رزرو میز از سمت مشتری ═══ */
  'rs-send'(el) {
    const f = App.cst.rs;
    const name = f.name.trim();
    const phone = en(f.phone).replace(/\D/g, '');
    if (name.length < 2) { f.err = 'نام خود را وارد کنید.'; render(); return; }
    if (!/^09\d{9}$/.test(phone)) { f.err = 'شمارهٔ موبایل معتبر نیست — مثل ۰۹۱۲۱۲۳۴۵۶۷'; render(); return; }
    if (!f.time) { f.err = 'ساعت رزرو را انتخاب کنید.'; render(); return; }
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال ثبت…';
    Api.post('reserve_create', {
      name, phone,
      date: f.date || '',
      time: f.time,
      persons: parseInt(en(f.persons), 10) || 2,
      note: f.note.trim()
    }).then(r => {
      f.ok = { ...r, time: f.time, persons: parseInt(en(f.persons), 10) || 2 };
      f.err = '';
      custNotifSync(phone);   /* تازه‌سازی فهرست رزروهای من */
    }).catch(e => {
      f.err = e.message;
    }).finally(() => {
      render();
    });
  },
  'rs-again'() {
    App.cst.rs = { name: App.cst.rs.name, phone: App.cst.rs.phone, date: '', time: '', persons: '۲', note: '', err: '', ok: null };
    render();
  },

  /* صندوق */
  'csh-tab'(el) { App.csh.tab = el.dataset.tab; render(); },
  'csh-filter'(el) { App.csh.f = el.dataset.f; render(); },
  'walkin'() { openWalkin(); },
  'wk-add'(el) {
    const s = App.csh.sale;
    const it = findItem(el.dataset.id);
    if (!it) return;
    const l = s.lines.find(x => x.id === it.id);
    l ? l.qty++ : s.lines.push({ id: it.id, name: it.name, price: effPrice(it), qty: 1 });
    const body = $('#modal-root .sh-body');
    if (body) body.innerHTML = walkinBodyHTML();
  },
  'wk-inc'(el) {
    const l = App.csh.sale.lines.find(x => x.id === el.dataset.id);
    if (l) {
      l.qty++;
      const b = $('#modal-root .sh-body');
      if (b) b.innerHTML = walkinBodyHTML();
    }
  },
  'wk-dec'(el) {
    const s = App.csh.sale;
    const l = s.lines.find(x => x.id === el.dataset.id);
    if (!l) return;
    l.qty > 1 ? l.qty-- : s.lines = s.lines.filter(x => x !== l);
    const b = $('#modal-root .sh-body');
    if (b) b.innerHTML = walkinBodyHTML();
  },
  'wk-table'(el) {
    const s = App.csh.sale;
    s.table = s.table === el.dataset.id ? null : el.dataset.id;
    const b = $('#modal-root .sh-body');
    if (b) b.innerHTML = walkinBodyHTML();
  },
  'wk-method'(el) {
    App.csh.sale.method = el.dataset.m;
    const b = $('#modal-root .sh-body');
    if (b) b.innerHTML = walkinBodyHTML();
  },
  'wk-done'() {
    const s = App.csh.sale;
    if (!s || !s.lines.length) return;
    let ph = en(s.phone).replace(/\D/g, '');
    if (ph && !/^09\d{9}$/.test(ph)) {
      ui.toast({ msg: 'شمارهٔ موبایل معتبر نیست — فیلد را خالی بگذارید یا اصلاح کنید' });
      return;
    }
    Api.post('order_create', {
      items: s.lines.map(l => ({ id: l.id, qty: l.qty, opts: [] })),
      name: ph ? 'مشتری' : '',
      phone: ph,
      method: s.method || 'cash',   /* v5.0.4-test.4: روش واقعی فروش حضوری (نقدی/کارتخوان) */
      tableNo: 0
    }).then(async () => {
      await Store.init();
      App.csh.sale = { q: '', lines: [], method: 'cash', phone: '', table: null };
      ui.closeModal();
      render();
      const o = [...Store.db.orders].sort((a, b) => b.ts - a.ts)[0];
      ui.toast({ msg: `فروش #${fa(o.num)} ثبت شد` });
      receiptModal(o);
    }).catch(e => ui.toast({ msg: e.message }));
  },
  'take-pay'(el) {
    Api.post('order_pay', { id: el.dataset.id }).then(db => {
      Store.db = db;
      render();
      ui.toast({ msg: 'وجه سفارش دریافت شد' });
    });
  },
  'deliver'(el) {
    Api.post('order_deliver', { id: el.dataset.id }).then(db => {
      Store.db = db;
      render();
      ui.toast({ msg: 'سفارش تحویل شد — نوش جان!' });
    });
  },
  'split-open'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    App.adm.spN = '۲';
    ui.modal(`<p class="dim" style="margin-bottom:10px">تقسیم صورت‌حساب سفارش #${fa(o.num)} — مجموع ${faM(o.total)} تومان</p>
      <label class="f"><span>تعداد نفرات</span><input data-input="sp-n" inputmode="numeric" value="۲"></label>
      <p class="mini" id="sp-prev"></p>
      <div class="m-acts"><button class="btn" data-act="split-print" data-id="${o.id}">${ic('printer', 15)} چاپ رسیدهای جدا</button>
      <button class="btn ghost" data-act="close-modal">انصراف</button></div>`, { title: 'تقسیم حساب' });
    updSplitPrev(o);
  },
  'split-print'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    const n = Math.max(2, parseInt(en(App.adm.spN), 10) || 2);
    ui.closeModal();
    $('#print-root').innerHTML = Array.from({ length: n }, (_, k) => receiptHTML(o, { i: k + 1, n })).join('');
    window.print();
  },

  'receipt'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (o) receiptModal(o);
  },
  'do-print'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (o) doPrint(o);
  },
  'invoice'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (o) invoiceModal(o);
  },
  'invoice-new'() {
    const os = [...Store.db.orders].sort((a, b) => b.ts - a.ts).slice(0, 15);
    ui.modal(`<div class="inv-pick">${os.map(o => `<button data-act="invoice" data-id="${o.id}"><b>#${fa(o.num)}</b><span>${dtfD.format(o.ts)} · ${tf.format(o.ts)}</span><b>${faM(o.total)}</b></button>`).join('') || '<p class="dim">سفارشی نیست.</p>'}</div>`,
      { title: 'انتخاب سفارش برای فاکتور' });
  },
  'send-inv'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    const ph = en($('#inv-phone').value).replace(/\D/g, '');
    if (!/^09\d{9}$/.test(ph)) { ui.toast({ msg: 'شمارهٔ موبایل معتبر نیست' }); return; }
    el.disabled = true;
    el.innerHTML = '<span class="spin"></span> در حال ارسال…';
    /* v5.0.5-test.5: ارسال واقعی با پنل پیامکی کافه (قبلاً فقط شبیه‌سازی بود) */
    Api.post('sms_send_one', { phone: ph, txt: M().brand.name + ' | فاکتور سفارش #' + o.num + ' — مبلغ ' + o.total + ' تومان — ' + dtfD.format(o.ts) })
      .then(() => {
        Store.db.invoices.push({ id: uid(), oid: o.id, num: o.num, phone: ph, amount: o.total, ts: Date.now() });
        Store.commit('invoices');
        ui.closeModal();
        ui.toast({ msg: `فاکتور #${fa(o.num)} به ${ph} پیامک شد` });
      })
      .catch(e => {
        el.disabled = false;
        el.innerHTML = ic('send', 15) + ' ارسال پیامک فاکتور';
        ui.toast({ msg: e.message });
      });
  },

  /* KDS */
  'kds-ready'(el) {
    Api.post('order_ready', { id: el.dataset.id }).then(db => {
      Store.db = db;
      render();
      beep();
      ui.toast({ msg: 'سفارش آماده شد — صندوق خبردار شد' });
    });
  },

  /* تنظیمات */
  'set-tgl'(el) {
    const S = SET();
    const k = el.dataset.k;
    if (k === 'hh') S.hh.on = !S.hh.on;
    else if (k === 'pts') { S.points = S.points || {}; S.points.on = !(S.points.on !== false); }
    else S[k] = !S[k];
    Store.commit('settings');
    render();
  },
  'promo-new'() {
    const a = App.adm.nu;
    const code = en(a.code).trim().toUpperCase();
    const off = parseInt(en(a.off), 10) || 0;
    if (code.length < 2 || off < 1 || off > 90) { ui.toast({ msg: 'کد (حداقل ۲ حرف) و درصد (۱ تا ۹۰) معتبر وارد کنید' }); return; }
    Store.db.promos.push({ id: uid(), code, off, on: true });
    App.adm.nu = { code: '', off: '' };
    Store.commit('promos');
    render();
    ui.toast({ msg: `کد ${code} ساخته شد` });
  },
  'promo-del'(el) {
    const arr = Store.db.promos;
    const i = arr.findIndex(x => x.id === el.dataset.id);
    if (i < 0) return;
    arr.splice(i, 1);
    Store.commit('promos');
    render();
  },
  'user-new'() {
    const a = App.adm.nuu;
    const name = a.name.trim();
    const phone = en(a.phone).replace(/\D/g, '');
    let pass = a.pass;   /* FIX_CONST_PASS_V1 — برای رمز موقت دعوت پرسنل */
    const pin = en(a.pin).replace(/\D/g, '');
    if (name.length < 2) { ui.toast({ msg: 'نام کاربر را وارد کنید' }); return; }
    if (phone && phone.length !== 11) { ui.toast({ msg: 'موبایل باید ۱۱ رقم باشد (مثلاً 09121234567)' }); return; }
    if (pass && (pass.length < 8 || !/[A-Za-z\u0600-\u06FF]/.test(pass) || !/\d/.test(pass))) { ui.toast({ msg: 'رمز حداقل ۸ کاراکتر با حداقل یک حرف و یک عدد باشد' }); return; }
    if (pin && pin.length !== 4) { ui.toast({ msg: 'پین باید ۴ رقم باشد یا خالی بماند' }); return; }
    if (!phone && !pin) { ui.toast({ msg: 'برای ورود، دست‌کم موبایل + رمز یا پین لازم است' }); return; }
    /* STAFF_INVITE_V1: اگر موبایل داد ولی رمز نه، رمز موقت می‌سازیم */
    let __tempPass = '';
    if (phone && !pass) { __tempPass = genTempPass(); pass = __tempPass; }
    const u = { id: uid(), name, pin };
    if (phone) u.phone = phone;
    if (pass) u.pass = pass;   /* سرور هش می‌کند؛ هرگز متنی ذخیره نمی‌شود */
    Store.db.users.push(u);
    if (Store.db.auth) delete Store.db.auth.pwaudit;   /* v5.0.5: کش بررسی رمز باطل شود */
    App.adm.nuu = { name: '', phone: '', pass: '', pin: '' };
    Store.commit('users');
    render();
    /* STAFF_INVITE_V1: SMS دعوت یا نمایش مودال */
    if (__tempPass && phone) {
      const __sm = Store.db.sms || {};
      const __cafe = (Store.db.menu && Store.db.menu.brand && Store.db.menu.brand.name) || 'کافه';
      const __txt = `ورود به پنل ${__cafe}:\nنام کاربری: ${phone}\nرمز موقت: ${__tempPass}\nپس از ورود، رمز را تغییر دهید.`;
      if (__sm.en) {
        Api.post('sms_send_one', { phone, txt: __txt })
          .then(() => showCredModal(name, phone, __tempPass, 'sms-sent'))
          .catch(() => showCredModal(name, phone, __tempPass, 'no-sms'));
      } else {
        showCredModal(name, phone, __tempPass, 'no-sms');
      }
    } else {
      ui.toast({ msg: `کاربر «${name}» اضافه شد` });
    }
  },
  'user-del'(el) {
    const arr = Store.db.users;
    const i = arr.findIndex(x => x.id === el.dataset.id);
    if (i < 0) return;
    arr.splice(i, 1);
    if (Store.db.auth) delete Store.db.auth.pwaudit;   /* v5.0.5: کش بررسی رمز باطل شود */
    Store.commit('users');
    render();
  },
  'exp-csv'() {
    const rows = [['شماره', 'تاریخ', 'ساعت', 'اقلام', 'جمع', 'تخفیف‌ها', 'مالیات', 'انعام', 'پرداختی', 'روش', 'میز', 'صندوق‌دار']];
    Store.db.orders.forEach(o => rows.push([
      o.num,
      dtfF.format(o.ts),
      tf.format(o.ts),
      o.items.map(i => i.qty + 'x' + i.name).join(' | '),
      o.sub,
      (o.promoAmt || 0) + (o.hhAmt || 0) + (o.discount || 0),
      o.vat || 0,
      o.tip || 0,
      o.total,
      methodLabel(o.method),
      o.tableNo || '',
      o.cashier || ''
    ]));
    const csv = '\uFEFF' + rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
    download('orders.csv', csv, 'text/csv;charset=utf-8');
    ui.toast({ msg: 'فایل CSV دانلود شد' });
  },

  /* نظرات و امتیاز */
  'rate'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    o.rated = +el.dataset.v || 0;
    Store.commit('orders').then(() => {
      render();
      ui.toast({ msg: `امتیاز شما ثبت شد — ${fa(o.rated)} ستاره` });
    });
  },
  'review-open'(el) {
    const o = Store.db.orders.find(x => x.id === el.dataset.id);
    if (!o) return;
    App.cst.rv = { oid: o.id, itemId: (o.items[0] || {}).id || '', stars: 0, txt: '' };
    reviewModal();
  },
  'rv-pick'(el) {
    if (App.cst.rv) { App.cst.rv.itemId = el.dataset.id; reviewModal(); }
  },
  'rv-star'(el) {
    if (App.cst.rv) { App.cst.rv.stars = +el.dataset.v || 0; reviewModal(); }
  },
  'review-send'() {
    const rv = App.cst.rv;
    if (!rv) return;
    if (!rv.itemId) { ui.toast({ msg: 'اول آیتم را انتخاب کنید' }); return; }
    if (rv.stars < 1) { ui.toast({ msg: 'تعداد ستاره را انتخاب کنید' }); return; }
    const txt = ($('#rv-txt') && $('#rv-txt').value.trim()) || '';
    if (txt.length < 3) { ui.toast({ msg: 'متن نظر حداقل ۳ حرف باشد' }); return; }
    Api.post('review_add', {
      itemId: rv.itemId,
      oid: rv.oid,
      by: (Store.cust && Store.cust.name) || 'مشتری',
      stars: rv.stars,
      txt
    }).then(() => {
      App.cst.rv = null;
      ui.closeModal();
      render();
      ui.toast({ msg: 'نظر شما ثبت شد و پس از تأیید مدیر نمایش داده می‌شود' });
    });
  }
};

/* ═══ ورودی‌ها (Inputs) ═══ */
const Inputs = {
  'li-user': el => App.login.user = el.value,
  'li-pass': el => App.login.pass = el.value,
  'fg-phone': el => App.login.phone = el.value,
  'rs-pass': el => App.login.pass = el.value,
  'rs-pass2': el => App.login.pass2 = el.value,
  'wz-tag': el => App.wiz.biz.tag = el.value,
  'wz-seats': el => App.wiz.tbl.seats = el.value,
  'mn-q': el => {
    App.cst.sq = el.value;
    const b = $('#mn-body');
    if (b) b.innerHTML = mnBodyHTML(false);
  },
  'sch-o': el => {
    const s = M().brand.sched;
    if (s && s[+el.dataset.i]) { s[+el.dataset.i].o = el.value || '10:00'; Store.commit('menu'); refreshMenuPreview(); }
  },
  'sch-c': el => {
    const s = M().brand.sched;
    if (s && s[+el.dataset.i]) { s[+el.dataset.i].c = el.value || '23:00'; Store.commit('menu'); refreshMenuPreview(); }
  },
  'head-g1': el => {
    const t = M().theme;
    t.headG1 = el.value;
    Store.commit('menu');
    const h = document.getElementById('mnhead');
    if (h) {
      const g2 = t.headG2 || shade(t.colors.acc, 0.2);
      h.style.background = `linear-gradient(150deg, ${el.value} 0%, ${g2} 100%)`;
    }
  },
  'head-g2': el => {
    const t = M().theme;
    t.headG2 = el.value;
    Store.commit('menu');
    const h = document.getElementById('mnhead');
    if (h) {
      const g1 = t.headG1 || shade(t.colors.acc, -0.5);
      h.style.background = `linear-gradient(150deg, ${g1} 0%, ${el.value} 100%)`;
    }
  },
  'bg-g1': el => {
    const t = M().theme;
    t.bgG1 = el.value;
    Store.commit('menu');
    const n = document.getElementById('mnbg');
    if (n) {
      const g2 = t.bgG2 || mixHex(t.colors.bg, t.colors.acc, 0.32);
      n.style.background = `linear-gradient(160deg, ${el.value} 10%, ${g2} 100%)`;
    }
  },
  'bg-g2': el => {
    const t = M().theme;
    t.bgG2 = el.value;
    Store.commit('menu');
    const n = document.getElementById('mnbg');
    if (n) {
      const g1 = t.bgG1 || t.colors.bg;
      n.style.background = `linear-gradient(160deg, ${g1} 10%, ${el.value} 100%)`;
    }
  },
  'bnr-g1': el => {
    const b = M().banners.find(x => x.id === el.dataset.id);
    if (!b) return;
    b.g1 = el.value;
    Store.commit('menu');
    const n = document.getElementById('bnr-' + b.id);
    if (n && b.g2) n.style.background = `linear-gradient(135deg,${b.g1},${b.g2})`;
  },
  'bnr-g2': el => {
    const b = M().banners.find(x => x.id === el.dataset.id);
    if (!b) return;
    b.g2 = el.value;
    Store.commit('menu');
    const n = document.getElementById('bnr-' + b.id);
    if (n && b.g1) n.style.background = `linear-gradient(135deg,${b.g1},${b.g2})`;
  },
  'adm-cat': el => { App.adm.selCat = el.value; render(); },
  'co-name': el => { App.cst.form.name = el.value; },
  /* v5.0.4-test.6: لوکیشن/آدرس اختیاری سفارش از راه دور */
  'co-loc': el => { App.cst.form.loc = el.value; },
  /* v5.0.4-test.4: انعام — مبلغ دلخواه (بدون رندر تا فوکوس از دست نرود) + توضیح */
  'tip-fix': el => {
    const f = App.cst.form;
    f.tipFix = +en(el.value).replace(/\D/g, '').slice(0, 12) || 0;
    if (f.tipFix > 0) f.tip = 0;
    const t = totals(), pa = $('#pay-amt b');
    if (pa) pa.textContent = faM(t.total) + ' تومان';
  },
  'tip-note': el => { App.cst.form.tipNote = el.value.slice(0, 140); },
  'co-phone': el => {
    App.cst.form.phone = el.value;
    const m = memberByPhone(en(el.value).replace(/\D/g, ''));
    const line = $('#mb-line');
    if (line) line.innerHTML = m ? `<b style="color:var(--ok)">${esc(m.name)}</b>&nbsp;· عضو ${levelOf(m.points).n} · ${fa(m.points)} امتیاز` : '';
  },
  'use-pts': el => { App.cst.form.usePts = el.checked; render(); },
  'gw-card': el => {
    let v = en(el.value).replace(/\D/g, '').slice(0, 16);
    el.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
    App.cst.form.card = el.value;
  },
  'lg-phone': el => { App.cst.lg.phone = el.value; },
  'lg-name': el => { App.cst.lg.name = el.value; },
  'wk-q': el => {
    App.csh.sale.q = el.value;
    const g = $('#modal-root .wk-grid');
    if (g) g.innerHTML = wkGridHTML();
  },
  'wk-phone': el => { App.csh.sale.phone = el.value; },
  'pal-color': el => {
    const m = M();
    m.theme.colors[el.dataset.k] = el.value;
    m.theme.paletteId = 'custom';
    resetGrads();
    Store.commit('menu');
    refreshMenuPreview();
    render();
  },
  'tg-count': el => App.adm.grp.count = el.value,
  'tg-seats': el => App.adm.grp.seats = el.value,
  'tg-zone': el => App.adm.grp.zone = el.value,
  /* v5.0.4-test.5: فرم میز تازه + جستجوی باشگاه مشتریان */
  'tn-seats': el => App.adm.tnew.seats = el.value,
  'tn-zone': el => App.adm.tnew.zone = el.value,
  'cust-q': el => { App.adm.cq = el.value; const b = $('#cust-body'); if (b) b.innerHTML = custListHTML(); },
  't-seats': el => {
    const t = Store.db.tables.find(x => x.id === el.dataset.id);
    if (t) {
      t.seats = parseInt(en(el.value), 10) || t.seats;
      Store.commit('tables');
    }
  },
  'vat-pct': el => { SET().vatPct = parseInt(en(el.value), 10) || 0; Store.commit('settings'); },
  'tg-target': el => {
    SET().target = parseInt(moneyRaw(el.value), 10) || 0;
    el.value = moneyFmt(el.value);
    Store.commit('settings');
  },
  'hh-from': el => { SET().hh.from = parseInt(en(el.value), 10) || 0; Store.commit('settings'); },
  'hh-to': el => { SET().hh.to = parseInt(en(el.value), 10) || 24; Store.commit('settings'); },
  'hh-off': el => { SET().hh.off = parseInt(en(el.value), 10) || 0; Store.commit('settings'); },
  /* v5.0.5-test.1: پیکربندی امتیاز باشگاه — نرخ، ارزش، سقف */
  'pt-earn': el => {
    const P = SET().points = SET().points || {};
    P.earn = Math.max(1000, parseInt(moneyRaw(el.value), 10) || 10000);
    el.value = moneyFmt(el.value);
    Store.commit('settings');
  },
  'pt-value': el => {
    const P = SET().points = SET().points || {};
    P.value = Math.max(500, parseInt(moneyRaw(el.value), 10) || 1000);
    el.value = moneyFmt(el.value);
    Store.commit('settings');
  },
  'pt-max': el => {
    const P = SET().points = SET().points || {};
    P.maxPct = Math.max(5, Math.min(100, parseInt(en(el.value), 10) || 50));
    Store.commit('settings');
  },
  /* v5.0.5-test.1: ارسال پیام دستی + فرم رزرو مشتری */
  'nb-to': el => App.adm.nb.to = el.value,
  'nb-title': el => App.adm.nb.title = el.value.slice(0, 80),
  'nb-body': el => App.adm.nb.body = el.value.slice(0, 200),
  'rs-name': el => App.cst.rs.name = el.value,
  'rs-phone': el => App.cst.rs.phone = el.value,
  'rs-date': el => App.cst.rs.date = el.value,
  'rs-time': el => App.cst.rs.time = el.value,
  'rs-persons': el => App.cst.rs.persons = el.value,
  'rs-note': el => App.cst.rs.note = el.value.slice(0, 160),
  'pm-code': el => App.adm.nu.code = el.value,
  'pm-off': el => App.adm.nu.off = el.value,
  'us-name': el => App.adm.nuu.name = el.value,
  'us-phone': el => App.adm.nuu.phone = el.value,
  'us-pass': el => App.adm.nuu.pass = el.value,
  'us-pin': el => App.adm.nuu.pin = el.value,
  'sh-user': el => App.adm.shu.user = el.value,
  'sh-pin': el => App.adm.shu.pin = el.value,
  'sh-free': el => App.adm.shu.free = el.value,
  'rz-table': el => App.adm.rz.table = el.value,
  'rz-name': el => App.adm.rz.name = el.value,
  'rz-phone': el => App.adm.rz.phone = el.value,
  'rz-time': el => App.adm.rz.time = el.value,
  'rz-seats': el => App.adm.rz.seats = el.value,
  'it-cost': el => {
    const it = findItem(el.dataset.id);
    if (it) {
      it.cost = parseInt(moneyRaw(el.value), 10) || 0;
      el.value = moneyFmt(el.value);
      Store.commit('menu');
    }
  },
  'it-stock': el => {
    const it = findItem(el.dataset.id);
    if (!it) return;
    const raw = en(el.value).replace(/[^\d]/g, '');
    it.stock = raw === '' ? null : parseInt(raw, 10);
    if (it.stock != null && it.stock > 0 && it.tags) it.tags.soldout = false;
    Store.commit('menu');
  },
  'sp-n': el => {
    App.adm.spN = el.value;
    const btn = el.closest('.sheet') && el.closest('.sheet').querySelector('[data-act="split-print"]');
    const o = btn ? Store.db.orders.find(x => x.id === btn.dataset.id) : null;
    if (o) updSplitPrev(o);
  },
};

/* ═══ ثبت تغییرات در دیتابیس سرور ═══ */
Store.commit = function (section) {
  this._dirty[section] = true;
  clearTimeout(this._t);
  this._t = setTimeout(async () => {
    const pend = this._dirty;
    this._dirty = {};
    for (const s of Object.keys(pend)) {
      try {
        if (s === 'orders') continue;
        this.db = await Api.post('save_' + s, this.db[SEC_KEY[s]]);
      } catch (e) {
        ui.toast({ msg: 'ذخیرهٔ ' + (SEC_FA[s] || s) + ' ناموفق بود: ' + e.message });
      }
    }
    const b = $('#saveBadge');
    if (b) {
      b.textContent = 'ذخیره شد ✓';
      b.classList.add('flash');
      setTimeout(() => { b.textContent = 'ذخیرهٔ خودکار'; b.classList.remove('flash'); }, 1500);
    }
  }, 400);
};

/* ═══ ویرایش inline متن‌ها ═══ */
document.addEventListener('input', e => {
  const d = e.target.dataset;
  if (!d) return;
  if (d.ce) {
    const m = M();
    if (d.ce === 'brand') m.brand.name = e.target.textContent;
    if (d.ce === 'tagline') m.brand.tagline = e.target.textContent;
    if (d.ce === 'addr') m.brand.address = e.target.textContent;
    if (d.ce === 'catname') { const c = findCat(d.cat); if (c) c.name = e.target.textContent; }
    if (d.ce === 'iname') { const i = findItem(d.item); if (i) i.name = e.target.textContent; }
    if (d.ce === 'idesc') { const i = findItem(d.item); if (i) i.desc = e.target.textContent; }
    if (d.ce === 'btxt' || d.ce === 'bsub') {
      const b = (m.banners || []).find(x => x.id === d.bnr);
      if (b) { if (d.ce === 'btxt') b.txt = e.target.textContent; else b.sub = e.target.textContent; }
    }
    if (d.ce === 'price') {
      const raw = en(e.target.textContent).replace(/[^\d]/g, '').slice(0, 12);
      e.target.textContent = moneyFmt(raw);
      const it = findItem(d.item);
      if (it) it.price = +raw || 0;
      const r = document.createRange();
      r.selectNodeContents(e.target);
      r.collapse(false);
      const s = window.getSelection();
      s.removeAllRanges();
      s.addRange(r);
      Store.commit('menu');
      return;
    }
    Store.commit('menu');
    return;
  }
  if (d.input && Inputs[d.input]) Inputs[d.input](e.target, e);
});

document.addEventListener('focusout', e => {
  const d = e.target.dataset;
  if (!d || !d.ce) return;
  const m = M(), trim = (e.target.textContent || '').trim();
  if (d.ce === 'price') {
    const it = findItem(d.item);
    if (!it) return;
    it.price = parseInt(en(trim).replace(/[^\d]/g, ''), 10) || 0;
    e.target.textContent = faM(it.price);
    Store.commit('menu');
    return;
  }
  if (d.ce === 'brand') m.brand.name = trim || m.brand.name;
  if (d.ce === 'tagline') m.brand.tagline = trim || m.brand.tagline;
  if (d.ce === 'addr') m.brand.address = trim || m.brand.address;
  if (d.ce === 'catname') { const c = findCat(d.cat); if (c) { c.name = trim || c.name; e.target.textContent = c.name; } }
  if (d.ce === 'iname') { const i = findItem(d.item); if (i) { i.name = trim || i.name; e.target.textContent = i.name; } }
  if (d.ce === 'idesc') { const i = findItem(d.item); if (i) i.desc = trim; }
  if (d.ce === 'btxt' || d.ce === 'bsub') {
    const b = (m.banners || []).find(x => x.id === d.bnr);
    if (b && d.ce === 'btxt') b.txt = trim;
  }
  Store.commit('menu');
});

document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && e.target.dataset && e.target.dataset.ce) {
    e.preventDefault();
    e.target.blur();
  }
  if (e.key === 'Escape') ui.closeModal();
});

document.addEventListener('paste', e => {
  if (!(e.target.dataset && e.target.dataset.ce)) return;
  e.preventDefault();
  document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
});

function flyToCart(from) {
  const bar = $('.cartbar');
  if (!bar || !from) return;
  const a = from.getBoundingClientRect(), b = bar.getBoundingClientRect();
  const d = document.createElement('div');
  d.className = 'fly';
  d.style.left = (a.left + a.width / 2 - 6) + 'px';
  d.style.top = (a.top + a.height / 2 - 6) + 'px';
  document.body.appendChild(d);
  const dx = (b.left + b.width / 2) - (a.left + a.width / 2), dy = (b.top + b.height / 2) - (a.top + a.height / 2);
  d.animate([
    { transform: 'translate(0,0) scale(1)', opacity: 1 },
    { transform: `translate(${dx}px,${dy}px) scale(.35)`, opacity: .15 }
  ], { duration: 520, easing: 'cubic-bezier(.3,.7,.3,1)' }).onfinish = () => d.remove();
}

function refreshItemCtrl(id) {
  $$(`[data-qid="${id}"]`).forEach(s => {
    const it = findItem(id);
    if (it && it.opts && it.opts.length) {
      s.innerHTML = `<button class="qbtn" data-act="opt-open" data-id="${id}">${ic('plus', 15)}</button>`;
      return;
    }
    const q = cartQty(id);
    const pl = Store.cart.find(c => c.id === id && !(c.opts && c.opts.length));
    const k = pl ? pl.k : '';
    s.innerHTML = q === 0
      ? `<button class="qbtn" data-act="cart-add" data-id="${id}">${ic('plus', 15)}</button>`
      : `<span class="qstep"><button data-act="cart-dec" data-k="${k}">${ic('minus', 13)}</button><b>${fa(q)}</b><button data-act="cart-inc" data-k="${k}">${ic('plus', 13)}</button></span>`;
  });
  const slot = $('#cb-slot');
  if (slot && App.cst.tab === 'menu') slot.innerHTML = cartBarHTML();
}

/* ═══ رویداد کلیک سراسری ═══ */
document.addEventListener('click', e => {
  const el = e.target.closest('[data-act]');
  if (!el) return;
  const fn = Actions[el.dataset.act];
  if (fn) {
    if (el.tagName === 'BUTTON') e.preventDefault();
    fn(el, e);
  }
});

/* ═══ جابه‌جایی منوها (Drag & Drop) ═══ */
(function () {
  let drag = null;
  document.addEventListener('pointerdown', e => {
    const h = e.target.closest && e.target.closest('.dragh');
    if (!h) return;
    const el = h.closest('[data-id]');
    const list = h.closest('[data-draglist]');
    if (!el || !list) return;
    e.preventDefault();
    drag = { el, list, sx: e.clientX, sy: e.clientY };
    el.classList.add('dragging');
    el.style.pointerEvents = 'none';
  });
  document.addEventListener('pointermove', e => {
    if (!drag) return;
    drag.el.style.transform = `translate(${e.clientX - drag.sx}px,${e.clientY - drag.sy}px)`;
    const under = document.elementFromPoint(e.clientX, e.clientY);
    const sib = under && under.closest ? under.closest('[data-id]') : null;
    if (sib && sib !== drag.el && sib.parentElement === drag.list) {
      const r = sib.getBoundingClientRect();
      const cy = r.top + r.height / 2;
      const after = Math.abs(e.clientY - cy) <= r.height / 2 ? e.clientY > cy : e.clientY > cy;
      drag.list.insertBefore(drag.el, after ? sib.nextSibling : sib);
      drag.sx = e.clientX;
      drag.sy = e.clientY;
      drag.el.style.transform = '';
    }
  });
  const end = () => {
    if (!drag) return;
    const { el, list } = drag;
    el.classList.remove('dragging');
    el.style.transform = '';
    el.style.pointerEvents = '';
    const order = [...list.querySelectorAll('[data-id]')].map(n => n.dataset.id);
    const cat = findCat(list.dataset.cat);
    if (cat) {
      cat.items.sort((a, b) => {
        const ia = order.indexOf(a.id), ib = order.indexOf(b.id);
        return (ia < 0 ? 999 : ia) - (ib < 0 ? 999 : ib);
      });
      Store.commit('menu');
      render();
    }
    drag = null;
  };
  document.addEventListener('pointerup', end);
  document.addEventListener('pointercancel', end);
})();

/* ═══ اسلایدر بنرها ═══ */
function syncDots() {
  $$('.bnrs').forEach(c => {
    const bi = +c.dataset.bi || 0;
    const zone = c.closest('.bnr-zone');
    if (!zone) return;
    zone.querySelectorAll('.bdot').forEach((d, i) => d.classList.toggle('on', i === bi));
  });
}

setInterval(() => {
  $$('.bnrs').forEach(c => {
    if (c.dataset.pause === '1') return;
    const cards = [...c.querySelectorAll(':scope > .bnr-w')];
    if (cards.length < 2) return;
    let i = (+c.dataset.bi || 0) + 1;
    if (i >= cards.length) i = 0;
    c.dataset.bi = i;
    cards[i].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  });
  syncDots();
}, 3000);

['pointerenter', 'pointerdown', 'touchstart'].forEach(ev =>
  document.addEventListener(ev, e => {
    const c = e.target.closest && e.target.closest('.bnrs');
    if (c) c.dataset.pause = '1';
  }, { passive: true }));

['pointerleave', 'touchend'].forEach(ev =>
  document.addEventListener(ev, e => {
    const c = e.target.closest && e.target.closest('.bnrs');
    if (c) c.dataset.pause = '';
  }, { passive: true }));

document.addEventListener('scroll', e => {
  const c = e.target && e.target.closest ? e.target.closest('.bnrs') : null;
  if (!c) return;
  clearTimeout(c._st);
  c._st = setTimeout(() => {
    const cards = [...c.querySelectorAll(':scope > .bnr-w')];
    if (!cards.length) return;
    const cr = c.getBoundingClientRect();
    let best = 0, bd = 1e9;
    cards.forEach((el, i) => {
      const r = el.getBoundingClientRect();
      const d = Math.abs((r.left + r.width / 2) - (cr.left + cr.width / 2));
      if (d < bd) { bd = d; best = i; }
    });
    c.dataset.bi = best;
    syncDots();
  }, 140);
}, true);


/* ═══ STAFF_INVITE_V1 helpers ═══ */
function genTempPass() {
  const U='ABCDEFGHJKLMNPQRSTUVWXYZ', L='abcdefghjkmnpqrstuvwxyz', D='23456789', S='@#!$%';
  const p = (s)=>s[Math.floor(Math.random()*s.length)];
  let r = p(U)+p(L)+p(D)+p(S);
  const all = U+L+D;
  for (let i=0;i<4;i++) r += p(all);
  return r;
}
function showCredModal(name, phone, pass, reason) {
  const old = document.getElementById('cred-modal');
  if (old) old.remove();
  const esc = (s)=>String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const msg = reason==='sms-sent' ? '✅ اطلاعات ورود به شماره پیامک شد' : '⚠️ پنل پیامکی وصل نیست — رمز را دستی به پرسنل بدهید';
  const html = '<div id="cred-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:14px">'
    + '<div style="background:#FBF6EB;border-radius:16px;padding:22px 20px;max-width:400px;width:100%;direction:rtl;font-family:Vazirmatn,sans-serif">'
    + '<h3 style="margin:0 0 4px;font-size:17px">اطلاعات ورود پرسنل</h3>'
    + '<p style="margin:0 0 12px;font-size:13px;color:#7D6C54">'+esc(name)+'</p>'
    + '<div style="background:#fff;border:1px solid #DFD2B6;border-radius:10px;padding:12px;margin-bottom:10px">'
    + '<div style="margin-bottom:6px"><span style="font-size:13px;color:#7D6C54">نام کاربری:</span> <code dir="ltr" style="background:#F2EADA;padding:2px 8px;border-radius:4px;font-size:15px">'+esc(phone)+'</code></div>'
    + '<div><span style="font-size:13px;color:#7D6C54">رمز موقت:</span> <code dir="ltr" style="background:#F2EADA;padding:2px 8px;border-radius:4px;font-size:15px;font-weight:700">'+esc(pass)+'</code></div>'
    + '</div>'
    + '<p style="font-size:12px;color:#7D6C54;margin:0 0 14px">'+msg+'</p>'
    + '<button id="cred-close" style="width:100%;padding:12px;background:#B4531F;color:#fff;border:0;border-radius:12px;font-family:inherit;font-size:15px;cursor:pointer">متوجه شدم</button>'
    + '</div></div>';
  document.body.insertAdjacentHTML('beforeend', html);
  document.getElementById('cred-close').addEventListener('click', function(){
    document.getElementById('cred-modal').remove();
  });
}
/* ═══ پایان STAFF_INVITE_V1 ═══ */
