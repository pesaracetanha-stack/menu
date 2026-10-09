<?php
declare(strict_types=1);

/**
 * تبدیل نمایش تاریخ "انتشار" در panel.php از میلادی به جلالی
 * یک‌بار اجرا کن، بعد پاک کن.
 */

$target = __DIR__ . '/panel/panel.php';

if (!is_file($target)) {
    echo "ERROR: panel/panel.php not found\n";
    exit(1);
}

$content = file_get_contents($target);

// اگر قبلاً تبدیل شده، کاری نکن
if (strpos($content, "jdate((string)\$updMan['released']") !== false) {
    echo "ALREADY CONVERTED — nothing to do\n";
    exit(0);
}

// خط قدیم
$old = "htmlspecialchars((string)\$updMan['released'])";

// خط جدید
$new = "htmlspecialchars(jdate((string)\$updMan['released'], 'Y/m/d'))";

if (strpos($content, $old) === false) {
    echo "ERROR: target string not found in panel.php\n";
    echo "Expected: $old\n";
    exit(1);
}

$count = 0;
$newContent = str_replace($old, $new, $content, $count);

if ($count !== 1) {
    echo "WARNING: expected 1 replacement, got $count\n";
    exit(1);
}

// بک‌آپ
$backup = $target . '.bak-released-' . date('Ymd-His');
file_put_contents($backup, $content);

// ذخیره
if (file_put_contents($target, $newContent) === false) {
    echo "ERROR: could not write file\n";
    exit(1);
}

echo "OK — released date now uses jdate()\n";
echo "Backup: $backup\n";