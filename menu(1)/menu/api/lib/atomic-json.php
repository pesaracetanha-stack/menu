<?php
declare(strict_types=1);
/**
 * Atomic JSON helper — read/modify/write under exclusive flock
 *
 * استفاده:
 *   ajl_read($file)     → خواندن امن
 *   ajl_update($file, function(array $data) { ...; return $data; })
 */

if (!function_exists('ajl_read')) {
    function ajl_read(string $file): array
    {
        if (!is_file($file)) return [];
        $json = @file_get_contents($file);
        if ($json === false) return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('ajl_update')) {
    /**
     * Atomic read-modify-write
     *
     * @param string   $file
     * @param callable $fn   تابعی که داده فعلی را می‌گیرد و داده جدید را برمی‌گرداند
     * @return array         داده نهایی
     */
    function ajl_update(string $file, callable $fn): array
    {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $fp = @fopen($file, 'c+');
        if (!$fp) throw new RuntimeException('Cannot open file: ' . $file);

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Cannot lock file: ' . $file);
            }

            $content = stream_get_contents($fp);
            $data = [];
            if ($content !== false && trim((string)$content) !== '') {
                $parsed = json_decode((string)$content, true);
                if (is_array($parsed)) $data = $parsed;
            }

            $result = $fn($data);
            if (is_array($result)) $data = $result;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);

            return $data;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}