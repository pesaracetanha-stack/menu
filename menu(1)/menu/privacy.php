<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>حریم خصوصی — GitiArts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="style-landing.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,sans-serif;background:#EFE7D6;color:#271D12;padding:24px 16px;line-height:1.95}
.wrap{max-width:800px;margin:0 auto}
.top-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:30px;flex-wrap:wrap}
.brand{font-size:18px;font-weight:900;color:#B4531F;text-decoration:none}
.top-nav a.back{padding:8px 16px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:700;color:#271D12;background:rgba(180,83,31,.08)}
.page{background:#FBF6EB;border:1px solid #DFD2B6;border-radius:16px;padding:36px 32px}
h1{font-size:24px;font-weight:900;margin-bottom:8px;color:#271D12}
.updated{font-size:12px;color:#7D6C54;margin-bottom:24px;padding-bottom:16px;border-bottom:1px dashed #DFD2B6}
h2{font-size:17px;font-weight:800;color:#4A6FA5;margin:28px 0 12px}
h2:first-of-type{margin-top:0}
p{font-size:14px;color:#4A3D2C;margin-bottom:12px}
ul{margin:8px 0 16px 20px;padding:0}
li{font-size:14px;color:#4A3D2C;margin-bottom:8px;padding-right:4px}
strong{color:#271D12;font-weight:800}
.highlight{background:#E8F4E3;border:1px solid #BFDCA8;border-radius:10px;padding:14px 18px;margin:12px 0;font-size:14px;color:#3D6F2B;font-weight:700}
.footer-mini{text-align:center;padding:24px 0;font-size:12px;color:#7D6C54;margin-top:24px}
.footer-mini a{color:#B4531F;text-decoration:none}
@media(max-width:640px){.page{padding:24px 18px}h1{font-size:20px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top-nav">
    <a href="index.html" class="brand">🌿 GitiArts</a>
    <a href="index.html" class="back">‹ بازگشت به صفحه اصلی</a>
  </div>

  <div class="page">
    <h1>🔒 سیاست حریم خصوصی GitiArts</h1>
    <p class="updated">آخرین به‌روزرسانی: ۱۴۰۵/۰۷/۲۵</p>

    <div class="highlight">
      📌 خلاصه در یک خط: داده‌های کافه شما ۱۰۰٪ متعلق به خودتان است. ما فقط به عنوان میزبان امن نگهداری می‌کنیم و هرگز آن‌ها را با کسی به اشتراک نمی‌گذاریم.
    </div>

    <h2>۱. کلیات</h2>
    <p>حفظ حریم خصوصی کاربران برای GitiArts در اولویت است. این سند توضیح می‌دهد که چه اطلاعاتی جمع‌آوری می‌کنیم، چرا جمع‌آوری می‌کنیم، چگونه از آن‌ها محافظت می‌کنیم و شما چه حقوقی نسبت به آن‌ها دارید.</p>

    <h2>۲. اطلاعاتی که جمع‌آوری می‌کنیم</h2>
    <p><strong>الف) اطلاعات حساب کافه‌دار:</strong></p>
    <ul>
      <li>نام کافه / رستوران</li>
      <li>شماره موبایل (برای احراز هویت و ورود)</li>
      <li>رمز عبور (فقط به صورت hash با bcrypt ذخیره می‌شود — ما رمز شما را نمی‌بینیم)</li>
      <li>آدرس و اطلاعات تماس (اختیاری، برای نمایش عمومی)</li>
    </ul>
    <p><strong>ب) اطلاعات مشتریان کافه:</strong></p>
    <ul>
      <li>شماره موبایل مشتری (فقط اگر خودش در فرم ثبت‌نام باشگاه مشتریان وارد کند)</li>
      <li>سابقه سفارش‌ها و امتیازهای باشگاه مشتریان</li>
      <li>یادداشت‌های سفارش (توضیحات مشتری مانند «بدون شکر»)</li>
    </ul>
    <p><strong>ج) اطلاعات فنی:</strong></p>
    <ul>
      <li>آدرس IP (برای جلوگیری از حملات امنیتی)</li>
      <li>نوع مرورگر و سیستم‌عامل</li>
      <li>زمان ورود به سامانه</li>
    </ul>

    <h2>۳. اطلاعاتی که جمع‌آوری نمی‌کنیم</h2>
    <ul>
      <li>❌ اطلاعات کارت بانکی مشتریان</li>
      <li>❌ موقعیت مکانی (GPS) کاربران</li>
      <li>❌ مخاطبین دفترچه تلفن</li>
      <li>❌ محتوای پیام‌های شخصی</li>
      <li>❌ اطلاعات مرور وب خارج از سامانه</li>
    </ul>

    <h2>۴. چرا این اطلاعات را جمع‌آوری می‌کنیم؟</h2>
    <ul>
      <li><strong>ارائه خدمات:</strong> ساخت منو، ثبت سفارش، مدیریت پرسنل</li>
      <li><strong>احراز هویت:</strong> تأیید شماره موبایل هنگام ثبت‌نام</li>
      <li><strong>امنیت:</strong> جلوگیری از دسترسی غیرمجاز به پنل کافه</li>
      <li><strong>بهبود خدمات:</strong> تحلیل استفاده از سامانه برای بهبود UX</li>
    </ul>

    <h2>۵. چطور از داده‌ها محافظت می‌کنیم؟</h2>
    <ul>
      <li>🔐 رمزهای عبور با الگوریتم <strong>bcrypt</strong> هش می‌شوند (غیرقابل بازگشت)</li>
      <li>🔒 تمام ارتباطات با پروتکل <strong>HTTPS</strong> رمزنگاری می‌شوند</li>
      <li>🛡️ هر کافه دیتابیس مستقل خودش را دارد — ایزولاسیون کامل</li>
      <li>💾 بکاپ خودکار روزانه از همه داده‌ها</li>
      <li>🔍 لاگ همه فعالیت‌های حساس (ورود، تغییر رمز، تغییر پلن)</li>
      <li>🚫 دسترسی به داده‌های کافه فقط با احراز هویت مالک کافه</li>
    </ul>

    <h2>۶. با کی به اشتراک می‌گذاریم؟</h2>
    <p><strong>با هیچ‌کس.</strong> ما داده‌های شما را نمی‌فروشیم، اجاره نمی‌دهیم و با هیچ شخص ثالثی به اشتراک نمی‌گذاریم.</p>
    <p>تنها استثنا: اگر مرجع قضایی صالح، حکم رسمی برای دسترسی به داده‌های خاص صادر کند، طبق قانون همکاری می‌کنیم. اما این نیز فقط برای آن کافه و آن داده خاص انجام می‌شود.</p>

    <h2>۷. سرویس‌های شخص ثالث</h2>
    <p>سامانه GitiArts از این سرویس‌های خارجی استفاده می‌کند:</p>
    <ul>
      <li><strong>سرویس‌های پیامکی (کاوه‌نگار / SMS.ir):</strong> برای ارسال کدهای تأیید و پیامک‌های سفارش</li>
      <li><strong>سرویس فونت وزیرمتن (jsDelivr):</strong> برای نمایش فونت فارسی</li>
      <li><strong>درگاه پرداخت زرین‌پال:</strong> فقط اگر کافه‌دار خودش فعال کند (پول مستقیماً به حساب کافه می‌رود)</li>
    </ul>

    <h2>۸. حقوق شما</h2>
    <p>به عنوان کاربر سامانه، شما این حقوق را دارید:</p>
    <ul>
      <li>✅ <strong>حق دسترسی:</strong> می‌توانید هر زمان داده‌های خود را ببینید</li>
      <li>✅ <strong>حق خروجی:</strong> می‌توانید از پنل ادمین یک فایل ZIP بگیرید و همه داده‌ها را ببرید</li>
      <li>✅ <strong>حق حذف:</strong> می‌توانید درخواست حذف کامل حساب و داده‌ها کنید</li>
      <li>✅ <strong>حق تصحیح:</strong> می‌توانید هر اطلاعات نادرست را اصلاح کنید</li>
      <li>✅ <strong>حق اعتراض:</strong> می‌توانید به هرگونه پردازش داده اعتراض کنید</li>
    </ul>

    <h2>۹. مدت نگهداری داده‌ها</h2>
    <ul>
      <li><strong>حساب فعال:</strong> تا زمانی که کافه از سامانه استفاده می‌کند</li>
      <li><strong>حساب غیرفعال:</strong> تا ۹۰ روز پس از آخرین ورود، سپس آرشیو</li>
      <li><strong>پس از حذف:</strong> داده‌ها بلافاصله از دیتابیس حذف می‌شوند (بکاپ‌ها تا ۳۰ روز حفظ می‌شوند)</li>
      <li><strong>لاگ امنیتی:</strong> حداکثر ۱۲ ماه</li>
    </ul>

    <h2>۱۰. کوکی‌ها</h2>
    <p>سامانه از کوکی‌های ضروری برای ورود و نگهداری نشست (session) استفاده می‌کند. این کوکی‌ها با تنظیمات <strong>HttpOnly</strong> و <strong>SameSite</strong> ذخیره می‌شوند و از کوکی‌های ردیابی تبلیغاتی استفاده نمی‌کنیم.</p>

    <h2>۱۱. کودکان</h2>
    <p>سامانه برای کاربران زیر ۱۸ سال در نظر گرفته نشده است. اگر متوجه شویم که حساب کاربری متعلق به فرد زیر ۱۸ سال است، آن را حذف خواهیم کرد.</p>

    <h2>۱۲. تغییرات این سیاست</h2>
    <p>در صورت تغییر عمده در این سیاست، حداقل ۷ روز قبل از طریق ایمیل یا اعلان در پنل به شما اطلاع می‌دهیم. ادامه استفاده پس از این تاریخ به معنای پذیرش نسخه جدید است.</p>

    <h2>۱۳. تماس با ما</h2>
    <p>برای هر سوال یا درخواست مربوط به حریم خصوصی:</p>
    <ul>
      <li>📱 تلفن: <strong>+98 919 834 5661</strong></li>
      <li>💬 تلگرام: <strong>GitiArts</strong></li>
      <li>📧 ایمیل: <strong>privacy@gitiarts.ir</strong></li>
      <li>🎫 یا از طریق تیکت پشتیبانی در پنل کافه</li>
    </ul>
  </div>

  <div class="footer-mini">
    © GitiArts — <a href="terms.php">شرایط استفاده</a> · <a href="privacy.php">حریم خصوصی</a> · <a href="support.php">پشتیبانی</a>
  </div>
</div>
</body>
</html>