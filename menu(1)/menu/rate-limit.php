<?php
declare(strict_types=1);

/**
 * Rate Limiting ساده برای GitiArts Menu
 * 
 * رویکرد: فایل‌محور (بدون نیاز به Redis)
 * - هر IP: حداکثر ۱۰ تلاش در ۱۵ دقیقه
 * - هر شماره موبایل: حداکثر ۵ تلاش در ۱۵ دقیقه
 * - هر OTP: حداکثر ۳ ارسال در ۱۰ دقیقه
 */

if (!function_exists('rate_limit_check')) {
    function rate_limit_check(string $key, int $max, int $windowSec): bool
    {
        $file = rate_limit_file($key);
        $now = time();
        
        $data = ['count' => 0, 'reset_at' => $now + $windowSec, 'first_at' => $now];
        
        if (is_file($file)) {
            $json = @file_get_contents($file);
            if ($json !== false) {
                $parsed = @json_decode($json, true);
                if (is_array($parsed) && isset($parsed['count'], $parsed['reset_at'])) {
                    if ($now < (int)$parsed['reset_at']) {
                        $data = $parsed;
                    }
                }
            }
        }
        
        $data['count'] = (int)$data['count'] + 1;
        $data['last_at'] = $now;
        
        @file_put_contents($file, json_encode($data), LOCK_EX);
        
        return $data['count'] <= $max;
    }
    
    function rate_limit_remaining(string $key): int
    {
        $file = rate_limit_file($key);
        if (!is_file($file)) return 0;
        
        $json = @file_get_contents($file);
        if ($json === false) return 0;
        
        $parsed = @json_decode($json, true);
        if (!is_array($parsed) || !isset($parsed['reset_at'])) return 0;
        
        $remaining = (int)$parsed['reset_at'] - time();
        return max(0, $remaining);
    }
    
    function rate_limit_reset(string $key): void
    {
        $file = rate_limit_file($key);
        if (is_file($file)) @unlink($file);
    }
    
    function rate_limit_file(string $key): string
    {
        // پوشه ذخیره در کنار خود فایل rate-limit.php
        $dir = __DIR__ . '/data/rate-limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        
        // پاکسازی خودکار فایل‌های قدیمی (۱٪ احتمال در هر درخواست)
        if (random_int(1, 100) === 1) {
            rate_limit_cleanup($dir);
        }
        
        return $dir . '/' . hash('sha256', $key) . '.json';
    }
    
    function rate_limit_cleanup(string $dir): void
    {
        $now = time();
        $files = @glob($dir . '/*.json');
        if ($files === false) return;
        
        foreach ($files as $f) {
            $json = @file_get_contents($f);
            if ($json === false) continue;
            
            $data = @json_decode($json, true);
            if (!is_array($data) || !isset($data['reset_at'])) {
                @unlink($f);
                continue;
            }
            
            // پاک کردن فایل‌های تمام‌شده (بیش از ۱ روز از انقضاشون گذشته)
            if ($now > (int)$data['reset_at'] + 86400) {
                @unlink($f);
            }
        }
    }
    
    /**
     * گرفتن IP واقعی کاربر (حتی اگر پشت Cloudflare/Proxy باشد)
     */
    function rate_limit_client_ip(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', (string)$_SERVER[$k])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}