<?php
declare(strict_types=1);
/* ثبت خوانده‌شدن اعلان — POST only + atomic lock */

$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('TEN_SESS');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
    http_response_code(403); echo json_encode(['ok'=>false, 'err'=>'unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false, 'err'=>'method_not_allowed']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false, 'err'=>'bad_id']); exit; }

$f = dirname(__DIR__, 2) . '/data/announcements.json';
if (!is_file($f)) { echo json_encode(['ok'=>false, 'err'=>'no_data']); exit; }

require_once dirname(__DIR__, 2) . '/api/lib/atomic-json.php';

try {
    ajl_update($f, function(array $d) use ($id, $slug) {
        if (!isset($d['items'])) $d['items'] = [];
        foreach ($d['items'] as $i => $it) {
            if ((int)$it['id'] !== $id) continue;
            $rb = $it['read_by'] ?? [];
            if (!in_array($slug, $rb, true)) {
                $rb[] = $slug;
                $d['items'][$i]['read_by'] = $rb;
            }
            break;
        }
        return $d;
    });
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'err'=>'exception']);
}