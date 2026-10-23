<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/paths.php';
header('Content-Type: application/json; charset=utf-8');

$slug = strtolower(trim($_GET['slug'] ?? ''));
if (!$slug) {
    echo json_encode(['ok' => false, 'msg' => 'لینک را وارد کنید']);
    exit;
}

$tenants = p_tenants_load()['tenants'] ?? [];
$exists = false;
foreach ($tenants as $t) {
    if (strtolower($t['slug'] ?? '') === $slug) {
        $exists = true; break;
    }
}
if (is_dir(p_root() . '/tenants/' . $slug)) {
    $exists = true;
}

if ($exists) {
    echo json_encode(['ok' => false, 'msg' => '❌ این لینک قبلاً گرفته شده است']);
} else {
    echo json_encode(['ok' => true, 'msg' => '✅ این لینک آزاد است']);
}