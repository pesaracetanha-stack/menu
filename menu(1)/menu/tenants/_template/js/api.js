'use strict';
/* ════════════════════════════════════════════════════════════
   Api — لایهٔ ارتباط با سرور (هر کافه، api/ مستقل خودش)
   v5.0.4-test.4: همهٔ خطاهای فنی (شبکه/JSON/سرور) به پیام فارسیِ
   قابل فهم ترجمه می‌شوند تا کاربر غیرحرفه‌ای بفهمد چه شده و چه کند.
   ════════════════════════════════════════════════════════════ */
const Api = {
  NET_ERR: 'ارتباط با سرور برقرار نشد — اینترنت خود را بررسی کنید و دوباره تلاش کنید',
  BAD_RES: 'پاسخ نامعتبر از سرور — چند لحظه بعد دوباره تلاش کنید؛ اگر تکرار شد صفحه را نوسازی کنید (F5)',
  /* ── v5.0.5-test.3: فشرده‌سازی تصویر در مرورگر، قبل از ارسال (سقف ۲۰۰ کیلوبایت) ──
     تصاویر بزرگ موبایل (تا ۸MB) قبل از آپلود کوچک می‌شوند → آپلود چند برابر سریع‌تر.
     شفافیت PNG با WebP حفظ می‌شود؛ اگر مرورگر WebP نداشت یا نتیجه نشد →
     فایل اصلی می‌رود و سرور خودش تضمین ۲۰۰KB را اعمال می‌کند (api/modules/upload.php). */
  async _shrink(file){
    try{
      const KB = 200 * 1024;
      if(!file || !/^image\//.test(file.type) || /svg/i.test(file.type)) return file;
      if(file.size <= KB) return file;
      if(!/^image\/(png|jpeg|webp|bmp)$/i.test(file.type)) return file;
      const url = URL.createObjectURL(file);
      const img = await new Promise((res, rej) => {
        const i = new Image();
        i.onload = () => res(i);
        i.onerror = () => rej(new Error('decode'));
        i.src = url;
      });
      const MAXW = 1200;
      const w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
      const tw = Math.min(w, MAXW), th = Math.max(1, Math.round(h * tw / w));
      const c = document.createElement('canvas');
      c.width = tw; c.height = th;
      const x = c.getContext('2d');
      if(!x){ URL.revokeObjectURL(url); return file; }
      x.drawImage(img, 0, 0, tw, th);
      URL.revokeObjectURL(url);
      for(const q of [0.85, 0.72, 0.58, 0.45, 0.34]){
        const blob = await new Promise(r => c.toBlob(r, 'image/webp', q));
        if(blob && blob.size <= KB)
          return new File([blob], (file.name || 'img').replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp' });
      }
      return file;
    }catch(e){ return file; }
  },
  async _send(url, opts, fallback){
    let r;
    try { r = await fetch(url, opts); }
    catch(_){ throw new Error(this.NET_ERR); }
    let j;
    try { j = await r.json(); }
    catch(_){ throw new Error(this.BAD_RES); }
    if(!j.ok) throw new Error(j.err || fallback);
    return j.data;
  },
  get(action, params){
    const q = params ? '&' + new URLSearchParams(params).toString() : '';
    return this._send('api/index.php?action='+encodeURIComponent(action)+q,
      {credentials:'same-origin'}, 'خطای سرور — چند لحظه بعد دوباره تلاش کنید');
  },
  post(action, data){
    return this._send('api/index.php?action='+encodeURIComponent(action), {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data ?? {}) }, 'خطای سرور — چند لحظه بعد دوباره تلاش کنید');
  },
  async upload(file){
    file = await this._shrink(file);   /* v5.0.5-test.3: فشرده‌سازی مرورگری قبل از ارسال */
    const fd = new FormData(); fd.append('file', file);
    return this._send('api/index.php?action=upload', {
      method:'POST', credentials:'same-origin', body: fd },
      'آپلود ناموفق بود — فایل را دوباره انتخاب کنید');
  },
  /* v5.0.4: بارگذاری فایل اکسل منو — پاسخ فقط پیش‌نمایش است؛ ذخیره با excel_apply */
  async uploadExcel(file){
    const fd = new FormData(); fd.append('file', file);
    return this._send('api/index.php?action=excel_import', {
      method:'POST', credentials:'same-origin', body: fd },
      'ایمپورت ناموفق بود — فایل را دوباره بارگذاری کنید');
  }
};
