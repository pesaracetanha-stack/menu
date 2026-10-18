<?php
declare(strict_types=1);
/* _fix-design-overflow.php — Fix layout overflow in design.php */

$dirs = ['_template', 'gitikhale'];

foreach ($dirs as $dir) {
    $f = "tenants/$dir/design.php";
    if (!file_exists($f)) continue;
    
    $c = file_get_contents($f);
    
    // اصلاح CSS برای جلوگیری از بیرون زدگی
    $oldCss = "  #app { flex-grow: 1; height: 100vh; overflow-y: auto; position: relative; }";
    $newCss = "  #app { flex-grow: 1; height: 100vh; overflow-y: auto; overflow-x: hidden; min-width: 0; position: relative; }\n  .menu-item, .item-card, .cat-row { max-width: 100%; box-sizing: border-box; word-break: break-word; }";
    
    if (strpos($c, 'min-width: 0;') === false) {
        $c = str_replace($oldCss, $newCss, $c);
        file_put_contents($f, $c);
        echo "✅ Fixed CSS overflow in: $f\n";
    } else {
        echo "⏭  Already patched: $f\n";
    }
}
echo "\n🎉 Done! Please Ctrl+F5 in browser.\n";