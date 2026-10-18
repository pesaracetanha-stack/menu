<?php
declare(strict_types=1);
/* _fix-wizard-bypass.php — Skip setup wizard in design.php */

$root = __DIR__ . '/tenants';
$dirs = array_filter(scandir($root), fn($d) => is_dir("$root/$d") && !in_array($d, ['.', '..']));

foreach ($dirs as $dir) {
    $f = "$root/$dir/js/app.js";
    if (!file_exists($f)) continue;
    
    $c = file_get_contents($f);
    
    // پیدا کردن شرطی که ویزارد را فعال می‌کند
    $old = "} else if(Store.db && Store.db.setup && !Store.db.setup.done) {";
    $new = "} else if(Store.db && Store.db.setup && !Store.db.setup.done && !window.__DESIGN_MODE__) {";
    
    if (strpos($c, $new) !== false) {
        echo "⏭  already patched: $dir/js/app.js\n";
        continue;
    }
    
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($f, $c);
        echo "✅ Wizard bypass added to design.php in: $dir/js/app.js\n";
    } else {
        echo "⚠️ Pattern not found in: $dir/js/app.js\n";
    }
}
echo "\n🎉 Done! Please Ctrl+F5 in browser.\n";