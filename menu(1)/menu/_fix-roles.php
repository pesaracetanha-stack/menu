<?php
declare(strict_types=1);
/* _fix-roles.php — Fix role detection priority for cashier/kds */

$dirs = ['_template', 'cafetest'];

$oldBlock = <<<'OLD'
 if(qt && (Store.db.tables || []).some(t => t.no === qt)){
   App.role = 'customer';
   App.cst.tableNo = qt;
 } else if(!isAdmin) {
   App.role = 'customer';
 } else if(Store.db && Store.db.setup && !Store.db.setup.done) {
   App.role = 'wizard';
 } else if(page === 'cashier' || rp === 'cashier') {
   App.role = 'cashier';
 } else if((page === 'kds' || rp === 'kds') && SET().kds) {
   App.role = 'kds';
 } else {
   App.role = 'admin';
 }
OLD;

$newBlock = <<<'NEW'
 /* FIX_V2: نام صفحه (cashier/kds) بالاترین اولویت را دارد */
 if(page === 'cashier' || rp === 'cashier') {
   App.role = 'cashier';
 } else if((page === 'kds' || rp === 'kds') && SET().kds) {
   App.role = 'kds';
 } else if(qt && (Store.db.tables || []).some(t => t.no === qt)){
   App.role = 'customer';
   App.cst.tableNo = qt;
 } else if(!isAdmin) {
   App.role = 'customer';
 } else if(Store.db && Store.db.setup && !Store.db.setup.done) {
   App.role = 'wizard';
 } else {
   App.role = 'admin';
 }
NEW;

foreach ($dirs as $dir) {
    $f = "tenants/$dir/js/app.js";
    if (!file_exists($f)) continue;
    
    $c = file_get_contents($f);
    if (strpos($c, 'FIX_V2: نام صفحه') !== false) {
        echo "⏭  already patched: $f\n";
        continue;
    }
    
    // Find and replace the block
    // Using str_replace with exact string might fail if whitespace differs slightly.
    // We'll use preg_replace to match the block dynamically.
    
    $pattern = '/if\s*\(\s*qt\s*&&.*?App\.role\s*=\s*\'admin\'\s*;\s*\}/s';
    
    // Wait, regex might be risky. Let's just do a specific search for the lines.
    // Actually, we can replace from "if(qt &&" down to "App.role = 'admin';"
    // But let's try exact string replace first.
    
    // The original code has some spaces. We'll construct a regex that is robust.
    $regex = '/(\s*)if\s*\(qt && \(Store\.db\.tables \|\| \[\]\)\.some\(t => t\.no === qt\)\)\{\s*App\.role = \'customer\';\s*App\.cst\.tableNo = qt;\s*\}\s*else if\(!isAdmin\) \{\s*App\.role = \'customer\';\s*\}\s*else if\(Store\.db && Store\.db\.setup && !Store\.db\.setup\.done\) \{\s*App\.role = \'wizard\';\s*\}\s*else if\(page === \'cashier\' \|\| rp === \'cashier\'\) \{\s*App\.role = \'cashier\';\s*\}\s*else if\(\(page === \'kds\' \|\| rp === \'kds\'\) && SET\(\)\.kds\) \{\s*App\.role = \'kds\';\s*\}\s*else \{\s*App\.role = \'admin\';\s*\}/s';

    if (preg_match($regex, $c)) {
        $c = preg_replace($regex, "\n" . $newBlock, $c);
        file_put_contents($f, $c);
        echo "✅ Fixed role priority: $f\n";
    } else {
        // Fallback to exact string replace if regex fails
        if (strpos($c, $oldBlock) !== false) {
            $c = str_replace($oldBlock, $newBlock, $c);
            file_put_contents($f, $c);
            echo "✅ Fixed role priority (exact match): $f\n";
        } else {
            echo "❌ ERROR: Could not find the block in: $f\n";
            echo "   Please check the file manually.\n";
        }
    }
}

echo "\n🎉 Done! Please press Ctrl+F5 in your browser.\n";