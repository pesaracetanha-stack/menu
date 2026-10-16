<?php
declare(strict_types=1);
/* صفحه پشتیبانی عمومی — v1 */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پشتیبانی — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="style-landing.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;min-height:100dvh;padding:20px 14px}
.wrap{max-width:960px;margin:0 auto}
.top-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:30px;flex-wrap:wrap}
.brand{font-size:18px;font-weight:900;color:#B4531F;text-decoration:none}
.nav-links{display:flex;gap:8px;flex-wrap:wrap}
.nav-links a{padding:8px 16px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:700;color:#271D12;background:rgba(180,83,31,.08);transition:.15s}
.nav-links a:hover{background:rgba(180,83,31,.15)}
.nav-links a.primary{background:#271D12;color:#fff}
.nav-links a.primary:hover{background:#3a2a1c}

.hero{text-align:center;margin-bottom:36px}
.hero h1{font-size:28px;font-weight:900;color:#271D12;margin-bottom:8px}
.hero p{font-size:14px;color:#7D6C54;line-height:1.8}

.quick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:36px}
.quick{background:#FBF6EB;border:1.5px solid #DFD2B6;border-radius:16px;padding:22px 20px;text-align:center;text-decoration:none;color:#271D12;transition:.18s}
.quick:hover{transform:translateY(-3px);border-color:#B4531F;box-shadow:0 8px 20px rgba(180,83,31,.1)}
.quick .ic{font-size:36px;margin-bottom:12px;display:block}
.quick h3{font-size:15px;font-weight:800;margin-bottom:6px}
.quick p{font-size:12px;color:#7D6C54;line-height:1.7}

.contact-card{background:linear-gradient(135deg,#FBF6EB 0%,#F2EADA 100%);border:1.5px solid #DFD2B6;border-radius:18px;padding:28px 24px;margin-bottom:36px}
.contact-card h2{font-size:20px;font-weight:900;color:#271D12;text-align:center;margin-bottom:8px}
.contact-card > p{text-align:center;font-size:13px;color:#7D6C54;margin-bottom:24px}
.contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.contact-item{background:#fff;border:1px solid #DFD2B6;border-radius:12px;padding:16px;text-align:center;text-decoration:none;color:inherit;transition:.15s}
.contact-item:hover{border-color:#B4531F}
.contact-item .ci-ic{font-size:26px;margin-bottom:8px}
.contact-item .ci-label{font-size:11px;color:#7D6C54;margin-bottom:4px}
.contact-item .ci-value{font-size:14px;font-weight:800;color:#271D12;direction:ltr}

.faq-section{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:16px;padding:28px 24px;margin-bottom:24px}
.faq-section h2{font-size:20px;font-weight:900;margin-bottom:20px;text-align:center}
.faq-item{background:#fff;border:1px solid #DFD2B6;border-radius:12px;padding:14px 18px;margin-bottom:10px;transition:.15s}
.faq-item[open]{border-color:#B4531F}
.faq-item summary{cursor:pointer;font-weight:800;font-size:14px;color:#271D12;list-style:none;position:relative;padding-left:24px;line-height:1.6}
.faq-item summary::before{content:'+';position:absolute;left:0;top:-2px;font-size:20px;color:#B4531F;font-weight:900;transition:.2s}
.faq-item[open] summary::before{content:'−'}
.faq-item p{margin-top:12px;font-size:13px;color:#4A3D2C;line-height:1.9;padding-top:12px;border-top:1px dashed #DFD2B6}

.cta-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:30px}
.cta-card{background:#271D12;color:#fff;border-radius:16px;padding:24px;text-align:center}
.cta-card h3{font-size:17px;font-weight:800;margin-bottom:8px;color:#fff}
.cta-card p{font-size:13px;opacity:.85;margin-bottom:18px;line-height:1.8}
.cta-card a{display:inline-block;padding:12px 26px;background:#B4531F;color:#fff;text-decoration:none;border-radius:12px;font-weight:800;font-size:14px;transition:.15s}
.cta-card a:hover{background:#9A4517;transform:translateY(-2px)}
.cta-card.alt{background:#FBF6EB;color:#271D12;border:1.5px solid #DFD2B6}
.cta-card.alt h3{color:#271D12}
.cta-card.alt a{background:transparent;color:#B4531F;border:1.5px solid #B4531F}

.footer-mini{text-align:center;padding:20px 0;font-size:12px;color:#7D6C54}
.footer-mini a{color:#B4531F;text-decoration:none}

@media(max-width:640px){
  .hero h1{font-size:22px}
  .contact-card,.faq-section{padding:20px 16px}
}
</style>
</head>
<body>
<div class="wrap">

  <!-- Navigation -->
  <div class="top-nav">
    <a href="index.html" class="brand">🌿 GitiArts</a>
    <div class="nav-links">
      <a href="index.html#pricing">پلن‌ها</a>
      <a href="index.html#faq">سوالات</a>
      <a href="login.php">ورود به پنل</a>
      <a href="index.html#register" class="primary">ثبت‌نام</a>
    </div>
  </div>

  <!-- Hero -->
  <div class="hero">
    <h1>💬 چطور می‌توانیم کمکت کنیم؟</h1>
    <p>تیم پشتیبانی GitiArts آماده پاسخ به سوالات شماست</p>
  </div>

  <!-- Quick Cards -->
  <div class="quick-grid">
    <a href="index.html#register" class="quick">
      <span class="ic">🚀</span>
      <h3>شروع کنید</h3>
      <p>ثبت‌نام کافه جدید و شروع رایگان</p>
    </a>
    <a href="#contact" class="quick">
      <span class="ic">💳</span>
      <h3>پرداخت و اشتراک</h3>
      <p>تمدید، پلن‌ها و سوالات مالی</p>
    </a>
    <a href="#faq" class="quick">
      <span class="ic">🛠️</span>
      <h3>مشکل فنی</h3>
      <p>اگر در استفاده از پنل مشکل دارید</p>
    </a>
    <a href="#contact" class="quick">
      <span class="ic">💡</span>
      <h3>پیشنهاد قابلیت</h3>
      <p>ایده‌ای برای بهتر شدن دارید؟</p>
    </a>
  </div>

  <!-- Contact -->
  <div class="contact-card" id="contact">
    <h2>📞 راه‌های ارتباطی</h2>
    <p>از هر کدام که راحت‌ترید استفاده کنید</p>
    <div class="contact-grid">
      <a href="tel:+989198345661" class="contact-item">
        <div class="ci-ic">📱</div>
        <div class="ci-label">تماس مستقیم</div>
        <div class="ci-value">+98 919 834 5661</div>
      </a>
      <a href="https://t.me/+989198345661" target="_blank" rel="noopener" class="contact-item">
        <div class="ci-ic">💬</div>
        <div class="ci-label">تلگرام</div>
        <div class="ci-value">GitiArts</div>
      </a>
      <a href="mailto:support@gitiarts.ir" class="contact-item">
        <div class="ci-ic">📧</div>
        <div class="ci-label">ایمیل</div>
        <div class="ci-value">support@gitiarts.ir</div>
      </a>
    </div>
  </div>

  <!-- FAQ -->
  <div class="faq-section" id="faq">
    <h2>❓ سوالات متداول</h2>

    <details class="faq-item">
      <summary>برای شروع به چه چیزی نیاز دارم؟</summary>
      <p>فقط یک موبایل یا کامپیوتر با اینترنت. هیچ نصب خاصی لازم نیست. بعد از ثبت‌نام، بلافاصله می‌توانید منوی کافه‌تان را بسازید.</p>
    </details>

    <details class="faq-item">
      <summary>چطور اشتراک بگیرم یا تمدید کنم؟</summary>
      <p>از داخل پنل کافه خودتان، بخش «تنظیمات» → «اشتراک» → «تمدید». اگر پنل ندارید، با شماره پشتیبانی تماس بگیرید.</p>
    </details>

    <details class="faq-item">
      <summary>اگر رمز عبورم را فراموش کردم؟</summary>
      <p>در صفحه ورود، روی «رمز را فراموش کرده‌ام» بزنید. یک کد پیامکی به موبایل ثبت‌شده ارسال می‌شود.</p>
    </details>

    <details class="faq-item">
      <summary>داده‌های کافه‌ام امن است؟</summary>
      <p>بله. هر کافه دیتابیس مستقل خودش را دارد و نمی‌تواند به داده‌های کافه‌های دیگر دسترسی داشته باشد. بکاپ روزانه خودکار از همه‌چیز گرفته می‌شود.</p>
    </details>

    <details class="faq-item">
      <summary>چطور کافه‌ام را از پلتفرم جدا کنم؟</summary>
      <p>در پنل ادمین، دکمه «📦 پشتیبان» را بزنید تا یک فایل ZIP مستقل بگیرید. می‌توانید آن را روی هر هاستی راه بیندازید. داده‌هایتان کاملاً متعلق به خودتان است.</p>
    </details>

    <details class="faq-item">
      <summary>آیا QR کد میزها را خودتان می‌سازید؟</summary>
      <p>در پنل کافه، بخش «میزها» → «چاپ QR» می‌توانید برای هر میز یک QR اختصاصی بسازید و چاپ کنید.</p>
    </details>

    <details class="faq-item">
      <summary>پرداخت آنلاین مشتریان چطور فعال می‌شود؟</summary>
      <p>در پنل کافه، بخش «تنظیمات» → «درگاه پرداخت» یک ویزارد گام‌به‌گام دارید که شما را به دریافت مرچنت کد زرین‌پال هدایت می‌کند. پول مستقیماً به حساب بانکی خودتان می‌رود.</p>
    </details>

    <details class="faq-item">
      <summary>پشتیبانی چقدر سریع پاسخ می‌دهد؟</summary>
      <p>معمولاً زیر ۲ ساعت کاری (شنبه تا پنجشنبه، ۹ صبح تا ۶ عصر). برای موارد فوری، تماس تلفنی سریع‌ترین راه است.</p>
    </details>
  </div>

  <!-- CTA -->
  <div class="cta-box">
    <div class="cta-card">
      <h3>🎫 پنل کافه دارید؟</h3>
      <p>سریع‌ترین راه ارتباط، ارسال تیکت از داخل پنل خودتان است.</p>
      <a href="login.php">ورود به پنل و ارسال تیکت</a>
    </div>
    <div class="cta-card alt">
      <h3>🌱 کافه جدیدی هستید؟</h3>
      <p>۱۴ روز رایگان، بدون کارت بانکی. در کمتر از ۵ دقیقه منوی کافه‌تان آماده است.</p>
      <a href="index.html#register">شروع رایگان</a>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer-mini">
    © GitiArts — <a href="terms.php">شرایط استفاده</a> · <a href="privacy.php">حریم خصوصی</a> · <a href="index.html">صفحه اصلی</a>
  </div>

</div>
</body>
</html>