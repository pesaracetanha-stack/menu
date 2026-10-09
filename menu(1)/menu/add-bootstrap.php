<?php
declare(strict_types=1);

/**
 * اسکریپت یک‌باره برای اضافه کردن bootstrap-i18n به panel.php
 * این اسکریپت خودش را بعد از اجرا پاک کنید
 */

$target = __DIR__ . '/panel/panel.php';

if (!is_file($target)) {
    echo "ERROR: panel/panel.php not found\n";
    exit(1);
}

$content = file_get_contents($target);

// اگر قبلاً اضافه شده، کاری نکن
if (strpos($content, 'bootstrap-i18n.php') !== false) {
    echo "ALREADY ADDED — nothing to do\n";
    exit(0);
}

// خط مورد نظر که بعد از آن درج می‌کنیم
$needle = "require_once __DIR__ . '/../api/lib/updater.php';";

if (strpos($content, $needle) === false) {
    echo "ERROR: needle not found in panel.php\n";
    echo "Manual review required.\n";
    exit(1);
}

// درج بعد از خط آخر
$replacement = $needle . "\nrequire_once __DIR__ . '/../bootstrap-i18n.php';";

$new = str_replace($needle, $replacement, $content, $count);

if ($count !== 1) {
    echo "WARNING: expected 1 replacement, got $count\n";
    exit(1);
}

// بک‌آپ بگیر
$backup = $target . '.bak-' . date('Ymd-His');
file_put_contents($backup, $content);

// ذخیره
$result = file_put_contents($target, $new);

if ($result === false) {
    echo "ERROR: could not write file\n";
    exit(1);
}

echo "OK — bootstrap-i18n.php inserted into panel/panel.php\n";
echo "Backup saved: $backup\n";