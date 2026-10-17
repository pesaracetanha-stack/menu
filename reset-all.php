<?php
// اسکریپت پاکسازی کامل کافه‌ها (فقط یکبار اجرا شود)
// ⚠️ بعد از اجرا، این فایل را حتماً پاک کنید!

$tenantsFile = __DIR__ . '/tenants.json';
$tenantsDir = __DIR__ . '/tenants';

// ۱. ریست فایل tenants.json
if (file_exists($tenantsFile)) {
    file_put_contents($tenantsFile, json_encode(['nextId' => 1, 'tenants' => []], JSON_PRETTY_PRINT));
    echo "✅ فایل tenants.json ریست شد.<br>";
}

// ۲. پاک کردن پوشه‌های کافه‌ها (به جز _template)
if (is_dir($tenantsDir)) {
    $items = scandir($tenantsDir);
    $count = 0;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '_template') continue;
        $path = $tenantsDir . '/' . $item;
        if (is_dir($path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($path);
            echo "🗑 پوشه کافه <b>$item</b> پاک شد.<br>";
            $count++;
        }
    }
    echo "✅ در مجموع $count کافه پاک شد.<br>";
}

// ۳. پاکسازی داده‌های وابسته (تیکت‌ها و اعلانات)
$dataDir = __DIR__ . '/data';
if (file_exists($dataDir . '/tickets.json')) {
    file_put_contents($dataDir . '/tickets.json', json_encode(['nextId' => 1, 'tickets' => []], JSON_PRETTY_PRINT));
    echo "✅ تیکت‌ها پاک شدند.<br>";
}
if (file_exists($dataDir . '/announcements.json')) {
    file_put_contents($dataDir . '/announcements.json', json_encode(['nextId' => 1, 'items' => []], JSON_PRETTY_PRINT));
    echo "✅ اعلانات پاک شدند.<br>";
}

echo "<br><b style='color:red'>⚠️ هشدار: الان این فایل (reset-all.php) را از روی هاست پاک کنید!</b>";