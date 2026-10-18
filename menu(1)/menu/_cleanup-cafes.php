<?php
declare(strict_types=1);
/* _cleanup-cafes.php — حذف کامل تمام کافه‌های تستی */

$root = __DIR__;
$jsonPath = $root . '/tenants.json';
$tenantsDir = $root . '/tenants';

echo "===========================================\n";
echo "  پاک‌سازی کامل کافه‌ها\n";
echo "===========================================\n\n";

// ۱. پاک کردن دایرکتوری‌ها (به جز _template)
if (is_dir($tenantsDir)) {
    $folders = array_filter(scandir($tenantsDir), function($d) use ($tenantsDir) {
        return is_dir($tenantsDir . '/' . $d) && $d !== '.' && $d !== '..' && $d !== '_template';
    });
    
    $deletedCount = 0;
    foreach ($folders as $slug) {
        $path = $tenantsDir . '/' . $slug;
        echo "🗑️  در حال حذف پوشه: tenants/{$slug}/\n";
        
        // تابع حذف بازگشتی
        $it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($path);
        $deletedCount++;
    }
    echo "✅ " . $deletedCount . " پوشه کافه حذف شد.\n\n";
}

// ۲. ریست کردن tenants.json
$backupPath = $jsonPath . '.bak.' . date('Ymd_His');
if (file_exists($jsonPath)) {
    copy($jsonPath, $backupPath);
    echo "📦 بکاپ لیست در: " . basename($backupPath) . "\n";
}

$emptyState = ['nextId' => 1, 'tenants' => []];
file_put_contents($jsonPath, json_encode($emptyState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "✅ لیست کافه‌ها (tenants.json) خالی شد.\n";

echo "\n🎉 همه چیز تمیز شد! فقط _template باقی مانده است.\n";