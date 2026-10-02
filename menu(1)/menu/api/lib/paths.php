<?php
/* ════════════════════════════════════════════════════════════
   مسیرها و ابزارهای مشترک پلتفرم — نسخهٔ ۲
   تغییر v2: p_lock بازنویسی شد — خواندن+تغییر+نوشتن tenants.json
   همه زیر یک قفل اتمیک انجام می‌شود (رفع بن‌بست قفل تو در تو)
   ════════════════════════════════════════════════════════════ */
declare(strict_types=1);

function p_root(): string { return dirname(__DIR__, 2); }           // .../menu
function p_config(): array { return require p_root() . '/config.php'; }
function p_tenants_file(): string { return p_root() . '/tenants.json'; }

function p_json_out(bool $ok, ?string $err = null, $data = null): void {
  while (ob_get_level() > 0) { ob_end_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>$ok, 'err'=>$err, 'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function p_tenants_load(): array {
  $j = json_decode((string)@file_get_contents(p_tenants_file()), true);
  return (is_array($j) && isset($j['tenants']) && is_array($j['tenants']))
    ? $j : ['nextId'=>1,'tenants'=>[]];
}
function p_tenants_save(array $d): void {
  file_put_contents(p_tenants_file(),
    json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
}
/* ════════════════════════════════════════════════════════════
   قفل اتمیک روی tenants.json
   callback امضایش: function (array &$t): mixed
   دادهٔ فعلی tenants.json به‌صورت reference می‌آید؛ callback
   آن را تغییر می‌دهد؛ ذخیره‌اش را خودِ p_lock انجام می‌دهد.
   ⚠️ داخل callback هرگز p_tenants_save / p_lock صدا نزن — بن‌بست!
   مقدار return شدهٔ callback، خروجی p_lock است.
   ════════════════════════════════════════════════════════════ */
function p_lock(callable $fn) {
  $fp = fopen(p_tenants_file(), 'c+');
  if (!$fp) throw new RuntimeException('tenants.json قابل باز شدن نیست');
  flock($fp, LOCK_EX);
  try {
    $raw = stream_get_contents($fp);
    $j   = json_decode((string)$raw, true);
    $t   = (is_array($j) && isset($j['tenants']) && is_array($j['tenants']))
         ? $j : ['nextId' => 1, 'tenants' => []];
    $r = $fn($t);                                  // تغییر داده زیر قفل
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($t, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $r;
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}
/* slug: لاتین کوچک/عدد/خط‌تیره، ۳..۳۰، اول و آخر حرف‌ویاعدد، کلمات رزرو ممنوع */
function p_slug_valid(string $s): bool {
  if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])?$/', $s)) return false;
  $reserved = ['admin','panel','api','www','mail','ftp','data','updates','demo',
               'register','login','assets','static','cdn','support','test'];
  return !in_array($s, $reserved, true);
}
