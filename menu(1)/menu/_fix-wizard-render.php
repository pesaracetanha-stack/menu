<?php
declare(strict_types=1);
/* _fix-wizard-render.php — Bypass setup wizard in design.php for template and all tenants */

$root = __DIR__ . '/tenants';
$dirs = array_filter(scandir($root), fn($d) => is_dir("$root/$d") && !in_array($d, ['.', '..']));

echo "Found " . count($dirs) . " folder(s) to check.\n\n";

foreach ($dirs as $dir) {
    $f = "$root/$dir/js/app.js";
    if (!file_exists($f)) continue;
    
    $c = file_get_contents($f);
    $patched = false;

    // ۱. اصلاح تابع boot() (اگر قبلاً اصلاح نشده)
    $oldBoot = "} else if(Store.db && Store.db.setup && !Store.db.setup.done) {";
    $newBoot = "} else if(Store.db && Store.db.setup && !Store.db.setup.done && !window.__DESIGN_MODE__) {";
    if (strpos($c, $newBoot) === false && strpos($c, $oldBoot) !== false) {
        $c = str_replace($oldBoot, $newBoot, $c);
        $patched = true;
    }

    // ۲. اصلاح تابع render() (مشکل اصلی اینجاست)
    $oldRender = "else if(isAdmin && Store.db && Store.db.setup && Store.db.setup.done === false) {";
    $newRender = "else if(isAdmin && Store.db && Store.db.setup && Store.db.setup.done === false && !window.__DESIGN_MODE__) {";
    if (strpos($c, $newRender) === false && strpos($c, $oldRender) !== false) {
        $c = str_replace($oldRender, $newRender, $c);
        $patched = true;
    }
    
    if ($patched) {
        file_put_contents($f, $c);
        echo "✅ Patched: $dir/js/app.js\n";
    } else {
        echo "⏭  Already patched or pattern not found: $dir/js/app.js\n";
    }
}
echo "\n🎉 Done! _template and all tenants are patched. Please Ctrl+F5 in browser.\n";