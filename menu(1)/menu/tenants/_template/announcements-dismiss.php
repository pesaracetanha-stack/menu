<?php
declare(strict_types=1);
/* ثبت خوانده‌شدن اعلان */

$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  http_response_code(403); echo json_encode(['ok'=>false]); exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false]); exit; }

$f = dirname(__DIR__, 2) . '/data/announcements.json';
if (!is_file($f)) { echo json_encode(['ok'=>false]); exit; }

$d = json_decode((string)@file_get_contents($f), true);
if (!is_array($d) || !isset($d['items'])) { echo json_encode(['ok'=>false]); exit; }

$changed = false;
foreach ($d['items'] as $i => $it) {
  if ((int)$it['id'] !== $id) continue;
  $rb = $it['read_by'] ?? [];
  if (!in_array($slug, $rb, true)) {
    $rb[] = $slug;
    $d['items'][$i]['read_by'] = $rb;
    $changed = true;
  }
  break;
}
if ($changed) {
  @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
echo json_encode(['ok' => true]);