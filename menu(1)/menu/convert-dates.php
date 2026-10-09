<?php
declare(strict_types=1);

/**
 * تبدیل date() به jdate() در panel.php
 * اجرا با --dry-run برای دیدن تغییرات قبل از اعمال
 */

$target = __DIR__ . '/panel/panel.php';
$dryRun = in_array('--dry-run', $argv, true);

if (!is_file($target)) {
    echo "ERROR: panel/panel.php not found\n";
    exit(1);
}

$content = file_get_contents($target);
$original = $content;

// فهرست جایگزینی‌ها: از → به
$replacements = [
    // Line 187: آخرین پشتیبان
    "date('m-d H:i', (int)filemtime(\$updBk))"
        => "jdate((int)filemtime(\$updBk), 'm/d H:i')",
    
    // Line 235: آخرین نصب
    "date('m-d H:i', (int)\$updLogs[0]['ts'])"
        => "jdate((int)\$updLogs[0]['ts'], 'm/d H:i')",
    
    // Line 252: تاریخ درخواست
    "date('Y-m-d H:i', (int)(\$r['ts'] ?? 0))"
        => "jdate((int)(\$r['ts'] ?? 0), 'Y/m/d H:i')",
    
    // Line 254: انقضای لینک
    "date('Y-m-d H:i', (int)(\$r['expires'] ?? 0))"
        => "jdate((int)(\$r['expires'] ?? 0), 'Y/m/d H:i')",
    
    // Line 319: آخرین سفارش
    "date('m-d H:i', (int)(\$det['last_order'] / 1000))"
        => "jdate((int)(\$det['last_order'] / 1000), 'm/d H:i')",
    
    // Line 371: تاریخ ثبت
    "date('Y-m-d', (int)(\$det['created'] / 1000))"
        => "jdate((int)(\$det['created'] / 1000), 'Y/m/d')",
    
    // Line 408: حذف
    "date('Y-m-d H:i', (int)\$t['ts'])"
        => "jdate((int)\$t['ts'], 'Y/m/d H:i')",
    
    // Line 434: رویداد
    "date('m-d H:i', (int)\$a['ts'])"
        => "jdate((int)\$a['ts'], 'm/d H:i')",
];

$count = 0;
$found = [];

foreach ($replacements as $old => $new) {
    if (strpos($content, $old) !== false) {
        $occurrences = substr_count($content, $old);
        $count += $occurrences;
        $found[] = ['old' => $old, 'new' => $new, 'count' => $occurrences];
        
        if (!$dryRun) {
            $content = str_replace($old, $new, $content);
        }
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════\n";
if ($dryRun) {
    echo "  حالت آزمایشی (dry run)\n";
} else {
    echo "  اعمال تغییرات\n";
}
echo "═══════════════════════════════════════════════════\n\n";

if (empty($found)) {
    echo "هیچ موردی برای تغییر پیدا نشد.\n";
    echo "(ممکن است قبلاً تبدیل شده باشند.)\n\n";
    exit(0);
}

echo "موارد پیدا شده:\n\n";
foreach ($found as $i => $f) {
    $n = $i + 1;
    echo "  $n. ($f[count]x)\n";
    echo "     قدیم: $f[old]\n";
    echo "     جدید: $f[new]\n\n";
}

echo "─────────────────────────────────────────────────\n";
echo "  مجموع: $count مورد\n";
echo "─────────────────────────────────────────────────\n\n";

if ($dryRun) {
    echo "برای اعمال تغییرات، این را بزن:\n";
    echo "  php convert-dates.php\n\n";
    exit(0);
}

// Backup
$backup = $target . '.bak-conv-' . date('Ymd-His');
file_put_contents($backup, $original);

// Save
$result = file_put_contents($target, $content);
if ($result === false) {
    echo "ERROR: نمی‌توان فایل را نوشت\n";
    exit(1);
}

echo "✓ تغییرات ذخیره شد\n";
echo "  بک‌آپ: $backup\n\n";
echo "برای چک syntax:\n";
echo "  php -l panel/panel.php\n\n";
