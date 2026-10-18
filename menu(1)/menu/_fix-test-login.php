<?php
declare(strict_types=1);

require_once __DIR__ . '/api/lib/paths.php';
require_once __DIR__ . '/api/lib/class-gstore.php';

$slug = 'testcafe';
$store = GStore::for(p_root() . '/tenants/' . $slug);

$store->txn(function (&$db) {
    // مقداردهی اطلاعات مدیریت
    $db['auth'] = [
        'phone' => '09123000000',
        'ph'    => password_hash('Admin@123', PASSWORD_DEFAULT),
        'user'  => 'مدیر تست'
    ];
    return $db; // برگرداندن سند برای تکمیل تراکنش
});

echo "✅ Admin user created for testcafe.\n";
echo "Phone: 09123000000\n";
echo "Pass: Admin@123\n";