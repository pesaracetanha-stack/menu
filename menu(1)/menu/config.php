<?php
/* ════════════════════════════════════════════════════════════
   GitiArts Menu Platform — تنظیمات مرکزی
   ⚠️ این فایل هرگز به مرورگر نمایش داده نمی‌شود (PHP اجرا می‌شود)
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

return [

  /* ── برند و آدرس‌ها ─────────────────────────────── */
  'version'  => '5.0.5-test.5',                                          // نسخهٔ بسته
  'brand'    => 'GitiArts',
  'base_url' => 'https://gitiarts.ir/menu',                              // بدون اسلش آخر
  'hub_url'  => 'https://gitiarts.ir/menu/updates/manifest.json',        // مرکز آپدیت
  'support_telegram' => '+989198345661',

  /* ── کلید نصب پنل ادمین ──────────────────────────
     فقط بار اول (هنگام ساخت رمز ادمین) لازم است.
     بعد از نصب موفق، مقدارش را خالی '' بگذارید. */
  'setup_key' => 'mySecretSetup123',

  /* ── پنل پیامکی ──────────────────────────────────
     ⚠️ امنیتی: کلید قبلی در فایل خروجی افشا شده بود —
     حتماً از پنل کاوه‌نگار حذفش کن و کلید تازه بگیر. */
  'sms' => [
    'provider' => 'kavenegar',   // kavenegar | ghasedak | smsir
    'key'      => '',            // کلید تازه را فقط اینجا بگذار
    'sender'   => '',            // شمارهٔ خط ارسال (اگر پنل‌تان نیاز دارد)
    'template' => '',            // نام قالب کد تأیید (برای kavenegar verify)
    'template_welcome' => '',   // نام قالب خوش‌آمدگویی (اختیاری)
  ],

  /* ── سقف هر کافه ───────────────────────────────── */
  /* ═══ PLANS_V1: تعریف پلن‌های اشتراک ═══
     هر پلن می‌تواند محدودیت‌های متفاوت داشته باشد.
     - price_toman: قیمت ماهانه به تومان (۰ = رایگان)
     - max_users: حداکثر پرسنل (۰ = نامحدود)
     - sms_enabled: آیا پنل پیامکی فعال است؟
     - max_menu_items: حداکثر آیتم منو (۰ = نامحدود)
     - max_orders_month: حداکثر سفارش ماهانه (۰ = نامحدود)
     - custom_domain: دامنه اختصاصی؟
     - support_level: none | email | priority
  */
  'plans' => [
    'free' => [
      'name_fa'        => 'رایگان',
      'price_toman'    => 0,
      'max_users'      => 0,
      'sms_enabled'    => false,
      'max_menu_items' => 30,
      'max_orders_month' => 0,
      'custom_domain'  => false,
      'support_level'  => 'none',
      'color'          => '#7D6C54',
    ],
    'basic' => [
      'name_fa'        => 'پایه',
      'price_toman'    => 200000,
      'max_users'      => 2,
      'sms_enabled'    => false,
      'max_menu_items' => 0,
      'max_orders_month' => 0,
      'custom_domain'  => false,
      'support_level'  => 'email',
      'color'          => '#4F7A3D',
    ],
    'pro' => [
      'name_fa'        => 'حرفه‌ای',
      'price_toman'    => 400000,
      'max_users'      => 0,
      'sms_enabled'    => true,
      'max_menu_items' => 0,
      'max_orders_month' => 0,
      'custom_domain'  => true,
      'support_level'  => 'priority',
      'color'          => '#B4531F',
    ],
  ],  'limits' => [
    'upload_mb'  => 200,     // حجم آپلود هر کافه (MB) — v5.0.3-test.3: حالا واقعاً اعمال می‌شود؛ ۵۰ برای ویدیوی ۶۰MB کم بود
    'items'      => 2000,    // آیتم منو
    'tables'     => 100,     // میز
    'trial_days' => 14,      // دورهٔ آزمایشی
    'grace_days' => 90,      // نگه‌داری داده پس از غیرفعال‌سازی
  ],

  /* ── امنیت ─────────────────────────────────────── */
  'security' => [
    'login_max_attempts' => 5,    // تلاش ناموفق قبل از قفل
    'lockout_minutes'    => 10,   // مدت قفل
    'session_name'       => 'GAMSESS',
    /* v5.0.5-test.3: موبایل مدیر پلتفرم — کد یکبارمصرف ورود به پنل (panel/) به این
       شماره پیامک می‌شود. برای فعال‌سازی: همین شماره + کلید پیامک بالا را پر کنید.
       خالی = ورود دومرحله‌ای غیرفعال (فقط رمز). */
    'admin_mobile'       => '',
  ],

  /* ── مسیرها (دست نزنید) ────────────────────────── */
  'paths' => [
    'data'    => __DIR__ . '/data',
    'tenants' => __DIR__ . '/tenants',
    'updates' => __DIR__ . '/updates',
  ],
];
