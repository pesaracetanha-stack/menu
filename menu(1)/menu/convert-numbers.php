<?php
declare(strict_types=1);

/**
 * تبدیل اعداد لاتین به فارسی در panel.php
 * اجرا با --dry-run برای دیدن قبل از اعمال
 */

$target = __DIR__ . '/panel/panel.php';
$dryRun = in_array('--dry-run', $argv, true);

if (!is_file($target)) {
    echo "ERROR: panel/panel.php not found\n";
    exit(1);
}

$content = file_get_contents($target);
$original = $content;

$replacements = [
    // خط ۱۶۸: MB مصرفی
    "<?= round(\$totalMb, 1) ?>"
        => "<?= pnum(round(\$totalMb, 1)) ?>",
    
    // خط ۱۶۹: MB سقف مجموع
    "<?= \$cfg['limits']['upload_mb'] * max(1, \$stats['total']) ?>"
        => "<?= pnum(\$cfg['limits']['upload_mb'] * max(1, \$stats['total'])) ?>",
    
    // خط ۱۸۸: حجم پشتیبان
    "<?= round((int)filesize(\$updBk) / 1048576, 1) ?>"
        => "<?= pnum(round((int)filesize(\$updBk) / 1048576, 1)) ?>",
    
    // خط ۳۱۳: فضا
    "<span>فضا: <b><?= \$x['size_mb'] ?></b> MB</span>"
        => "<span>فضا: <b><?= pnum(\$x['size_mb']) ?></b> MB</span>",
    
    // خط ۳۷۵: حجم کل
    "<span>حجم کل: <b><?= \$x['size_mb'] ?></b> MB از <?= \$cfg['limits']['upload_mb'] ?> MB</span>"
        => "<span>حجم کل: <b><?= pnum(\$x['size_mb']) ?></b> MB از <?= pnum(\$cfg['limits']['upload_mb']) ?> MB</span>",
    
    // خط ۴۱۰: فضا (سطل زباله)
    "<span>فضا: <?= \$t['size_mb'] ?> MB</span>"
        => "<span>فضا: <?= pnum(\$t['size_mb']) ?> MB</span>",
];

$count = 0;
$found = [];

foreach ($replacements as $old => $new) {
    if (strpos($content, $old) !== false) {
        $n = substr_count($content, $old);
        $count += $n;
        $found[] = ['old' => $old, 'new' => $new, 'count' => $n];
        if (!$dryRun) {
            $content = str_replace($old, $new, $content);
        }
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════\n";
echo $dryRun ? "  حالت آزمایشی\n" : "  اعمال تغییرات\n";
echo "═══════════════════════════════════════════════════\n\n";

if (empty($found)) {
    echo "هیچ موردی پیدا نشد.\n";
    exit(0);
}

foreach ($found as $i => $f) {
    $n = $i + 1;
    echo "  $n. ({$f['count']}x)\n";
    echo "     قدیم: {$f['old']}\n";
    echo "     جدید: {$f['new']}\n\n";
}

echo "─────────────────────────────────────────────────\n";
echo "  مجموع: $count مورد\n";
echo "─────────────────────────────────────────────────\n\n";

if ($dryRun) {
    echo "برای اعمال: php convert-numbers.php\n";
    exit(0);
}

$backup = $target . '.bak-num-' . date('Ymd-His');
file_put_contents($backup, $original);

if (file_put_contents($target, $content) === false) {
    echo "ERROR: could not write file\n";
    exit(1);
}

echo "✓ تغییرات ذخیره شد\n";
echo "  بک‌آپ: $backup\n";