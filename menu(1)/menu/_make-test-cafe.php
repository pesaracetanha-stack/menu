<?php
declare(strict_types=1);

$root = __DIR__;
$slug = 'testcafe';
$tenantDir = $root . '/tenants/' . $slug;
$templateDir = $root . '/tenants/_template';

echo "Creating test cafe: $slug\n";

// 1. Copy template
if (is_dir($tenantDir)) {
    echo "Folder already exists.\n";
} else {
    function rcopy($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    rcopy($src . '/' . $file,$dst . '/' . $file);
                } else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    rcopy($templateDir, $tenantDir);
    echo "Copied _template to tenants/$slug\n";
}

// 2. Ensure data dir and empty DB
$dataDir = $tenantDir . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dbFile = $dataDir . '/db.json';
if (!is_file($dbFile)) {
    file_put_contents($dbFile, json_encode(['menu' => ['brand' => ['name' => 'تست کافه']], 'setup' => ['done' => true]], JSON_UNESCAPED_UNICODE));
    echo "Created empty db.json\n";
}

// 3. Update tenants.json
$tFile = $root . '/tenants.json';
$tData = json_decode(file_get_contents($tFile), true) ?: ['nextId' => 1, 'tenants' => []];

$exists = false;
foreach ($tData['tenants'] as $t) {
    if ($t['slug'] === $slug) { $exists = true; break; }
}

if (!$exists) {
    $tData['tenants'][] = [
        'id' => $tData['nextId']++,
        'slug' => $slug,
        'name' => 'کافه تست',
        'phone' => '09120000000',
        'active' => true,
        'created' => round(microtime(true) * 1000),
        'domain' => '',
        'license' => 'TEST-LICENSE',
        'password' => password_hash('123456', PASSWORD_DEFAULT) // رمز فرضی
    ];
    file_put_contents($tFile, json_encode($tData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Added to tenants.json\n";
}

echo "\n✅ Done! Test cafe created.\n";