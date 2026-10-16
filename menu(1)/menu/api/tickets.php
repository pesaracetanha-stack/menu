<?php
/* ═══════════════════════════════════════════════════════════════
   v5.0.6: سیستم تیکتینگ پشتیبانی
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/lib/paths.php';
require_once __DIR__ . '/lib/platform-auth.php';
require_once __DIR__ . '/lib/platform-admin.php';

header('Content-Type: application/json; charset=utf-8');

function tk_out(bool $ok, ?string $err = null, $data = null): void {
  while (ob_get_level() > 0) ob_end_clean();
  echo json_encode(['ok' => $ok, 'err' => $err, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

function tk_file(): string {
  return p_root() . '/data/tickets.json';
}

function tk_load(): array {
  $f = tk_file();
  if (!is_file($f)) return ['nextId' => 1, 'tickets' => []];
  $j = json_decode((string)@file_get_contents($f), true);
  if (!is_array($j)) return ['nextId' => 1, 'tickets' => []];
  if (!isset($j['tickets'])) $j['tickets'] = [];
  if (!isset($j['nextId'])) $j['nextId'] = 1;
  return $j;
}

function tk_save(array $d): void {
  $f = tk_file();
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* ─── action: create (کافه‌دار) ─── */
function ticket_create(array $in): array {
  sec_session();

  $slug = (string)($_SESSION['tenant'] ?? '');
  if ($slug === '') throw new Exception('دسترسی ندارید');

  $subject = trim((string)($in['subject'] ?? ''));
  $body    = trim((string)($in['body'] ?? ''));
  $cat     = (string)($in['category'] ?? 'general');

  if (mb_strlen($subject) < 3) throw new Exception('موضوع تیکت را وارد کنید (حداقل ۳ حرف)');
  if (mb_strlen($subject) > 120) throw new Exception('موضوع حداکثر ۱۲۰ حرف');
  if (mb_strlen($body) < 10) throw new Exception('متن تیکت را کامل بنویسید (حداقل ۱۰ حرف)');
  if (mb_strlen($body) > 2000) throw new Exception('متن تیکت حداکثر ۲۰۰۰ حرف');

  $allowed = ['technical', 'billing', 'feature', 'general'];
  if (!in_array($cat, $allowed, true)) $cat = 'general';

  $user = (string)($_SESSION['tenant_user'] ?? 'کاربر');

  $d = tk_load();
  $id = (int)$d['nextId'];
  $d['nextId']++;

  $ticket = [
    'id'        => $id,
    'slug'      => $slug,
    'subject'   => $subject,
    'category'  => $cat,
    'status'    => 'open',   /* open | answered | closed */
    'created'   => time(),
    'updated'   => time(),
    'messages'  => [
      [
        'from'   => 'cafe',
        'author' => $user,
        'text'   => $body,
        'ts'     => time(),
      ],
    ],
  ];

  $d['tickets'][] = $ticket;
  tk_save($d);

  return ['ticket' => $ticket];
}

/* ─── action: list ─── */
function ticket_list(array $in): array {
  sec_session();

  $isAdmin = !empty($_SESSION['plat_user']);
  $slug    = (string)($_SESSION['tenant'] ?? '');

  if (!$isAdmin && $slug === '') throw new Exception('دسترسی ندارید');

  $d = tk_load();
  $tickets = $d['tickets'];

  /* فیلتر */
  if (!$isAdmin) {
    $tickets = array_values(array_filter($tickets, fn($t) => ($t['slug'] ?? '') === $slug));
  }

  $filter = (string)($in['status'] ?? 'all');
  if ($filter !== 'all') {
    $tickets = array_values(array_filter($tickets, fn($t) => ($t['status'] ?? '') === $filter));
  }

  /* خلاصه برای لیست (بدون متن کامل پیام‌ها) */
  $summary = [];
  foreach ($tickets as $t) {
    $lastMsg = end($t['messages']);
    $summary[] = [
      'id'       => (int)$t['id'],
      'slug'     => $t['slug'],
      'subject'  => $t['subject'],
      'category' => $t['category'],
      'status'   => $t['status'],
      'created'  => (int)$t['created'],
      'updated'  => (int)$t['updated'],
      'count'    => count($t['messages']),
      'lastFrom' => $lastMsg['from'] ?? '',
      'lastText' => mb_substr((string)($lastMsg['text'] ?? ''), 0, 80),
    ];
  }

  /* مرتب بر اساس آخرین به‌روزرسانی */
  usort($summary, fn($a, $b) => $b['updated'] <=> $a['updated']);

  /* تعداد باز */
  $openCount = 0;
  foreach ($d['tickets'] as $t) {
    if (($t['status'] ?? '') === 'open') $openCount++;
  }

  return [
    'tickets' => $summary,
    'openCount' => $openCount,
    'isAdmin' => $isAdmin,
  ];
}

/* ─── action: get (یک تیکت با پیام‌ها) ─── */
function ticket_get(array $in): array {
  sec_session();

  $isAdmin = !empty($_SESSION['plat_user']);
  $slug    = (string)($_SESSION['tenant'] ?? '');
  $id      = (int)($in['id'] ?? 0);

  if ($id <= 0) throw new Exception('شناسه تیکت نامعتبر');
  if (!$isAdmin && $slug === '') throw new Exception('دسترسی ندارید');

  $d = tk_load();
  foreach ($d['tickets'] as $t) {
    if ((int)$t['id'] === $id) {
      if (!$isAdmin && ($t['slug'] ?? '') !== $slug) {
        throw new Exception('دسترسی ندارید');
      }
      return ['ticket' => $t, 'isAdmin' => $isAdmin];
    }
  }
  throw new Exception('تیکت یافت نشد');
}

/* ─── action: reply ─── */
function ticket_reply(array $in): array {
  sec_session();

  $isAdmin = !empty($_SESSION['plat_user']);
  $slug    = (string)($_SESSION['tenant'] ?? '');
  $id      = (int)($in['id'] ?? 0);
  $text    = trim((string)($in['text'] ?? ''));

  if ($id <= 0) throw new Exception('شناسه تیکت نامعتبر');
  if (mb_strlen($text) < 2) throw new Exception('متن پاسخ خیلی کوتاه است');
  if (mb_strlen($text) > 2000) throw new Exception('متن پاسخ حداکثر ۲۰۰۰ حرف');

  if (!$isAdmin && $slug === '') throw new Exception('دسترسی ندارید');

  $d = tk_load();
  $found = false;
  foreach ($d['tickets'] as $i => $t) {
    if ((int)$t['id'] !== $id) continue;
    if (!$isAdmin && ($t['slug'] ?? '') !== $slug) throw new Exception('دسترسی ندارید');

    if (($t['status'] ?? '') === 'closed') throw new Exception('این تیکت بسته شده است');

    $author = $isAdmin ? 'پشتیبانی' : (string)($_SESSION['tenant_user'] ?? 'کاربر');
    $from   = $isAdmin ? 'admin' : 'cafe';

    $d['tickets'][$i]['messages'][] = [
      'from'   => $from,
      'author' => $author,
      'text'   => $text,
      'ts'     => time(),
    ];
    $d['tickets'][$i]['updated'] = time();
    /* اگر ادمین پاسخ داد → answered، اگر کافه پاسخ داد → open */
    $d['tickets'][$i]['status'] = $isAdmin ? 'answered' : 'open';
    $found = true;
    break;
  }

  if (!$found) throw new Exception('تیکت یافت نشد');

  tk_save($d);
  return ['ok' => true];
}

/* ─── action: close ─── */
function ticket_close(array $in): array {
  sec_session();

  $isAdmin = !empty($_SESSION['plat_user']);
  $slug    = (string)($_SESSION['tenant'] ?? '');
  $id      = (int)($in['id'] ?? 0);

  if ($id <= 0) throw new Exception('شناسه تیکت نامعتبر');
  if (!$isAdmin && $slug === '') throw new Exception('دسترسی ندارید');

  $d = tk_load();
  $found = false;
  foreach ($d['tickets'] as $i => $t) {
    if ((int)$t['id'] !== $id) continue;
    if (!$isAdmin && ($t['slug'] ?? '') !== $slug) throw new Exception('دسترسی ندارید');

    $d['tickets'][$i]['status'] = 'closed';
    $d['tickets'][$i]['updated'] = time();
    $found = true;
    break;
  }

  if (!$found) throw new Exception('تیکت یافت نشد');

  tk_save($d);
  return ['ok' => true];
}

/* ─── dispatcher ─── */
try {
  $action = (string)($_GET['action'] ?? '');
  $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;

  switch ($action) {
    case 'create': tk_out(true, null, ticket_create($in));
    case 'list':   tk_out(true, null, ticket_list($in));
    case 'get':    tk_out(true, null, ticket_get($in));
    case 'reply':  tk_out(true, null, ticket_reply($in));
    case 'close':  tk_out(true, null, ticket_close($in));
    default:       tk_out(false, 'action نامعتبر');
  }
} catch (Throwable $e) {
  tk_out(false, $e->getMessage());
}