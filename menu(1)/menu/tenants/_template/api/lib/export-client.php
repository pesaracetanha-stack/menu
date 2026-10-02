<?php
/* ═══════════════════════════════════════════════════════════════
   کلاینت درخواست فایل نصبی — سمت کافه (قابل‌حمل)
   · روی پلتفرم: درخواست به data/export-requests.json پلتفرم ثبت می‌شود
   · در بستهٔ مستقل: «standalone» است و دکمهٔ درخواست مخفی می‌شود
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

function xc_platform_root(): ?string {
  $root = dirname(T_ROOT, 2);          // tenants/<slug> → ریشهٔ پلتفرم
  return is_file($root . '/config.php') ? $root : null;
}

function xc_is_standalone(): bool {
  return xc_platform_root() === null;
}

/* آخرین درخواست ثبت‌شدهٔ این کافه */
function xc_client_status(string $slug): array {
  if (xc_is_standalone()) return ['mode' => 'standalone', 'req' => null];
  $root = xc_platform_root();
  $f = $root . '/data/export-requests.json';
  $j = json_decode((string)@file_get_contents($f), true);
  $out = null;
  foreach ((is_array($j) && isset($j['reqs'])) ? $j['reqs'] : [] as $r)
    if (($r['slug'] ?? '') === $slug) $out = $r;
  return ['mode' => 'platform', 'req' => $out];
}

/* ثبت درخواست جدید (فقط وقتی پلتفرم موجود است) */
function xc_client_request(string $slug, string $note, string $byName): array {
  $root = xc_platform_root();
  if ($root === null) throw new RuntimeException('این نسخه مستقل است — درخواست از طریق پشتیبانی (تلگرام) ثبت شود');
  $f = $root . '/data/export-requests.json';
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
  $fp = fopen($f, 'c+');
  if (!$fp) throw new RuntimeException('ثبت درخواست ممکن نشد — به پشتیبانی اطلاع دهید');
  flock($fp, LOCK_EX);
  try {
    $j = json_decode((string)stream_get_contents($fp), true);
    $d = (is_array($j) && isset($j['reqs']) && is_array($j['reqs']))
       ? $j : ['nextId' => 1, 'reqs' => []];
    foreach ($d['reqs'] as $r)
      if (($r['slug'] ?? '') === $slug && ($r['status'] ?? '') === 'pending')
        return ['id' => (int)$r['id'], 'dup' => true];
    $id = (int)$d['nextId']++;
    $d['reqs'][] = ['id' => $id, 'slug' => $slug, 'name' => $byName,
                    'note' => mb_substr($note, 0, 300), 'ts' => time(),
                    'status' => 'pending'];
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return ['id' => $id, 'dup' => false];
  } finally {
    flock($fp, LOCK_UN); fclose($fp);
  }
}
