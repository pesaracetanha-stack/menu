<?php
declare(strict_types=1);

/**
 * GitiArts — Phase 2.5 bootstrap
 * 
 * یک نقطه ورود مرکزی برای لایه فارسی‌سازی.
 * 
 * استفاده:
 *   require_once __DIR__ . '/bootstrap-i18n.php';
 * 
 * بعد از آن، این توابع در دسترس هستند:
 *   fa('menu.item.add')              → ترجمه از fa.json
 *   pnum(1234)                        → ۱۲۳۴
 *   jdate('2026-10-03', 'Y/m/d')      → ۱۴۰۵/۰۷/۱۱
 *   toman(150000)                     → ۱۵۰,۰۰۰ تومان
 * 
 * همه چیز lazy هست — اگر autoloader نبود، هیچ خطایی نمی‌دهد.
 */

// ─── Autoloader ───
$autoload = __DIR__ . '/vendor/autoload.php';
$i18nReady = false;

if (is_file($autoload)) {
    require_once $autoload;
    
    // چک می‌کنیم کلاس‌ها موجود باشند
    if (class_exists(\GitiArts\Phase2\Localization\Lang::class)) {
        try {
            // راه‌اندازی فقط یک بار
            static $initialized = false;
            if (!$initialized) {
                \GitiArts\Phase2\Localization\Lang::init();
                $initialized = true;
            }
            $i18nReady = true;
        } catch (\Throwable $e) {
            // اگر راه‌اندازی خطا داد، بی‌صدا رد می‌شویم
            // (بهتر است پنل کار کند تا اینکه کرش کند)
            $i18nReady = false;
        }
    }
}

// ─── Helper Functions ───

if (!function_exists('fa')) {
    /**
     * ترجمه کلید به فارسی
     * اگر لایه فعال نبود، خود کلید را برمی‌گرداند
     */
    function fa(string $key, array $params = []): string {
        global $i18nReady;
        if ($i18nReady && class_exists(\GitiArts\Phase2\Localization\Lang::class)) {
            try {
                return \GitiArts\Phase2\Localization\Lang::get($key, $params);
            } catch (\Throwable $e) {
                return $key;
            }
        }
        return $key;
    }
}

if (!function_exists('pnum')) {
    /**
     * تبدیل اعداد لاتین به فارسی
     * pnum(1234)      → ۱۲۳۴
     * pnum('1234')    → ۱۲۳۴
     */
    function pnum($value): string {
        global $i18nReady;
        if ($i18nReady && class_exists(\GitiArts\Phase2\Localization\PersianDigits::class)) {
            try {
                return \GitiArts\Phase2\Localization\PersianDigits::toPersian((string)$value);
            } catch (\Throwable $e) {
                return (string)$value;
            }
        }
        return (string)$value;
    }
}

if (!function_exists('jdate')) {
    /**
     * تبدیل تاریخ میلادی به جلالی و فرمت‌دهی
     * 
     * jdate('2026-10-03', 'Y/m/d')             → ۱۴۰۵/۰۷/۱۱
     * jdate('now', 'Y/m/d H:i')                → ۱۴۰۵/۰۷/۱۱ ۱۴:۳۰
     * jdate(1728000000, 'Y/m/d')               → از timestamp
     */
    function jdate($datetime, string $format = 'Y/m/d'): string {
        global $i18nReady;
        if ($i18nReady && class_exists(\GitiArts\Phase2\Localization\JalaliDate::class)) {
            try {
                // اگر timestamp عددی بود، به string تبدیل کن
                if (is_int($datetime)) {
                    $datetime = date('Y-m-d H:i:s', $datetime);
                }
                return \GitiArts\Phase2\Localization\JalaliDate::format((string)$datetime, $format);
            } catch (\Throwable $e) {
                return (string)$datetime;
            }
        }
        return is_int($datetime) ? date($format, $datetime) : (string)$datetime;
    }
}

if (!function_exists('toman')) {
    /**
     * نمایش مبلغ با واحد تومان
     * toman(150000)      → ۱۵۰,۰۰۰ تومان
     * toman(0)           → ۰ تومان
     */
    function toman($amount): string {
        global $i18nReady;
        if ($i18nReady && class_exists(\GitiArts\Phase2\Localization\Currency::class)) {
            try {
                return \GitiArts\Phase2\Localization\Currency::format((int)$amount);
            } catch (\Throwable $e) {
                return number_format((int)$amount) . ' تومان';
            }
        }
        return number_format((int)$amount) . ' تومان';
    }
}