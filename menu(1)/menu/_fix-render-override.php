<?php
declare(strict_types=1);
/* _fix-render-override.php — Fix render() overriding cashier/kds roles */

$dirs = ['_template', 'cafetest'];

foreach ($dirs as $dir) {
    $f = "tenants/$dir/js/app.js";
    if (!file_exists($f)) continue;
    
    $c = file_get_contents($f);
    
    // پیدا کردن شرطی که نقش را به زور customer می‌کند
    $oldPattern = "if(!isAdmin && App.role !== 'wizard'){";
    $newPattern = "if(!isAdmin && App.role !== 'wizard' && App.role !== 'cashier' && App.role !== 'kds'){";
    
    if (strpos($c, $newPattern) !== false) {
        echo "⏭  already patched: $f\n";
        continue;
    }
    
    if (strpos($c, $oldPattern) !== false) {
        $c = str_replace($oldPattern, $newPattern, $c);
        file_put_contents($f, $c);
        echo "✅ Fixed render() override in: $f\n";
    } else {
        echo "⚠️ Pattern not found in: $f (maybe syntax is slightly different)\n";
    }
}

echo "\n🎉 Done! Please press Ctrl+F5 in your browser.\n";