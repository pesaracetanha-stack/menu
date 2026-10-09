<?php
declare(strict_types=1);
/* یک‌بار اجرا کن، بعد پاک کن */

$file = __DIR__ . '/tenants/_template/panel-admin.php';

if (!is_file($file)) {
    echo "ERROR: file not found\n";
    exit(1);
}

$c = file_get_contents($file);

$old = '<?= number_format($todaySum) ?>';
$new = '<?= toman($todaySum) ?>';

if (strpos($c, '<?= toman($todaySum) ?>') !== false) {
    echo "ALREADY CONVERTED\n";
    exit(0);
}

if (strpos($c, $old) === false) {
    echo "ERROR: pattern not found\n";
    exit(1);
}

$count = 0;
$c = str_replace($old, $new, $c, $count);

if ($count !== 1) {
    echo "WARNING: got $count matches\n";
    exit(1);
}

$backup = $file . '.bak-' . date('Ymd-His');
file_put_contents($backup, file_get_contents($file));
file_put_contents($file, $c);

echo "OK — panel-admin.php: number_format → toman\n";
echo "Backup: $backup\n";