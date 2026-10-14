<?php
/* ═══════════════════════════════════════════════════════════════
   v5.0.6: ماژول بکاپ خودکار
   - bak_run_all(): بکاپ همه کافه‌ها
   - bak_list(): لیست بکاپ‌ها
   - bak_state(): وضعیت آخرین بکاپ
   - bak_cleanup(): پاکسازی هوشمند
   - bak_lazy_check(): Lazy Cron — اگر >۲۴ ساعت گذشته، بکاپ بگیر
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const BAK_INTERVAL_HOURS = 24;   /* فاصله بین بکاپ‌ها */
const BAK_KEEP_PER_CAFE  = 5;    /* حداکثر بکاپ برای هر کافه */
const BAK_MAX_AGE_DAYS   = 30;   /* حداکثر عمر بکاپ */
const BAK_PLANNED_HOUR   = 3;    /* ساعت برنامه‌ریزی‌شده (۰-۲۳) */

/* ─── مسیرها ─── */
function bak_dir(): string {
  return dirname(__DIR__, 2) . '/data/exports';
}
function bak_state_file(): string {
  return dirname(__DIR__, 2) . '/data/backup-state.json';
}

/* ─── خواندن/نوشتن state ─── */
function bak_state(): array {
  $f = bak_state_file();
  if (!is_file($f)) {
    return ['last_run' => 0, 'last_count' => 0, 'last_duration' => 0.0, 'last_error' => null];
  }
  $j = json_decode((string)@file_get_contents($f), true);
  return is_array($j) ? $j : ['last_run' => 0, 'last_count' => 0, 'last_duration' => 0.0, 'last_error' => null];
}
function bak_state_save(array $s): void {
  $f = bak_state_file();
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* ─── لیست بکاپ‌ها ─── */
function bak_list(): array {
  $dir = bak_dir();
  if (!is_dir($dir)) return [];

  $out = [];
  $files = glob($dir . '/*.zip') ?: [];
  foreach ($files as $f) {
    $name = basename($f);
    /* الگو: slug-YYMMDD_HHMMSS.zip */
    if (!preg_match('/^(.+?)-(\d{6})_(\d{6})\.zip$/', $name, $m)) continue;

    $slug = $m[1];
    $ts = 0;
    /* پارس تاریخ ۲ رقمی: 261014 → 2026-10-14 */
    $d = $m[2]; $t = $m[3];
    $year = 2000 + (int)substr($d, 0, 2);
    $month = (int)substr($d, 2, 2);
    $day = (int)substr($d, 4, 2);
    $hour = (int)substr($t, 0, 2);
    $min = (int)substr($t, 2, 2);
    $sec = (int)substr($t, 4, 2);
    if (checkdate($month, $day, $year)) {
      $ts = mktime($hour, $min, $sec, $month, $day, $year);
    }
    if ($ts === 0) $ts = (int)@filemtime($f);

    $out[] = [
      'name' => $name,
      'slug' => $slug,
      'ts'   => $ts,
      'size' => (int)@filesize($f),
      'path' => $f,
    ];
  }
  usort($out, fn($a, $b) => $b['ts'] <=> $a['ts']);
  return $out;
}

/* ─── زمان انسانی ─── */
function bak_human_ago(int $ts): string {
  if ($ts <= 0) return 'هرگز';
  $diff = time() - $ts;
  if ($diff < 60) return 'همین الان';
  if ($diff < 3600) return (int)($diff / 60) . ' دقیقه پیش';
  if ($diff < 86400) return (int)($diff / 3600) . ' ساعت پیش';
  $days = (int)($diff / 86400);
  if ($days < 30) return $days . ' روز پیش';
  return (int)($days / 30) . ' ماه پیش';
}
function bak_human_size(int $bytes): string {
  if ($bytes < 1024) return $bytes . ' B';
  if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
  if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
  return round($bytes / 1073741824, 2) . ' GB';
}

/* ─── زمان بکاپ بعدی ─── */
function bak_next_time(): int {
  $last = (int)(bak_state()['last_run'] ?? 0);
  $now = time();
  if ($last <= 0) return $now + 60; /* اگر هرگز نبوده، تا ۱ دقیقه دیگر */

  $next = $last + (BAK_INTERVAL_HOURS * 3600);

  /* اگر بکاپ زمان‌بندی‌شده برای ساعت ۳ بامداد است */
  if ($next <= $now) return $now + 60; /* همین حالا */

  return $next;
}

/* ─── پاکسازی هوشمند ─── */
function bak_cleanup(): array {
  $list = bak_list();
  $byCafe = [];
  foreach ($list as $b) {
    $byCafe[$b['slug']][] = $b;
  }

  $deleted = 0;
  $freed = 0;
  $now = time();
  $maxAge = BAK_MAX_AGE_DAYS * 86400;

  foreach ($byCafe as $slug => $items) {
    $i = 0;
    foreach ($items as $b) {
      /* قدیمی‌تر از max age */
      if ($now - $b['ts'] > $maxAge) {
        if (@unlink($b['path'])) { $deleted++; $freed += $b['size']; }
        continue;
      }
      /* بیشتر از keep per cafe */
      $i++;
      if ($i > BAK_KEEP_PER_CAFE) {
        if (@unlink($b['path'])) { $deleted++; $freed += $b['size']; }
      }
    }
  }
  return ['deleted' => $deleted, 'freed' => $freed];
}

/* ─── اجرای بکاپ همه ─── */
function bak_run_all(): array {
  $started = microtime(true);
  $ok = 0; $fail = 0; $errors = [];

  if (!function_exists('plat_list')) {
    return ['ok' => 0, 'fail' => 0, 'errors' => ['platform-admin not loaded'], 'duration' => 0.0];
  }

  $tenants = plat_list();
  foreach ($tenants as $t) {
    $slug = (string)($t['slug'] ?? '');
    if ($slug === '') continue;
    try {
      plat_backup($slug);
      $ok++;
    } catch (Throwable $e) {
      $fail++;
      $errors[] = $slug . ': ' . $e->getMessage();
    }
  }

  /* پاکسازی */
  $cleanup = bak_cleanup();

  $duration = round(microtime(true) - $started, 2);

  bak_state_save([
    'last_run'      => time(),
    'last_count'    => $ok,
    'last_fail'     => $fail,
    'last_duration' => $duration,
    'last_error'    => empty($errors) ? null : implode(' | ', $errors),
    'last_deleted'  => $cleanup['deleted'],
    'last_freed'    => $cleanup['freed'],
  ]);

  return [
    'ok' => $ok, 'fail' => $fail,
    'errors' => $errors,
    'duration' => $duration,
    'cleanup' => $cleanup,
  ];
}

/* ─── Lazy Cron ─── */
function bak_lazy_check(): ?array {
  $state = bak_state();
  $last = (int)($state['last_run'] ?? 0);
  $now = time();
  $interval = BAK_INTERVAL_HOURS * 3600;

  if ($last > 0 && ($now - $last) < $interval) {
    return null; /* هنوز وقتش نشده */
  }

  /* اجرای خودکار */
  try {
    require_once __DIR__ . '/platform-admin.php';
    return bak_run_all();
  } catch (Throwable $e) {
    return ['ok' => 0, 'fail' => 1, 'errors' => [$e->getMessage()], 'duration' => 0.0];
  }
}

/* ─── وضعیت کامل برای UI ─── */
function bak_status(): array {
  $state = bak_state();
  $list = bak_list();
  $totalSize = 0;
  foreach ($list as $b) $totalSize += $b['size'];

  return [
    'last_run'      => (int)($state['last_run'] ?? 0),
    'last_ago'      => bak_human_ago((int)($state['last_run'] ?? 0)),
    'next_run'      => bak_next_time(),
    'next_in'       => bak_human_ago(bak_next_time()), /* منفی می‌شود، یعنی آینده */
    'interval_hours'=> BAK_INTERVAL_HOURS,
    'planned_hour'  => BAK_PLANNED_HOUR,
    'count_total'   => count($list),
    'size_total'    => $totalSize,
    'size_total_h'  => bak_human_size($totalSize),
    'keep_per_cafe' => BAK_KEEP_PER_CAFE,
    'max_age_days'  => BAK_MAX_AGE_DAYS,
    'list'          => array_slice($list, 0, 20), /* ۲۰ مورد آخر */
  ];
}
/* ═══ SMS_REMINDER_V1: یادآوری انقضا با SMS ═══ */
function sms_reminder_state_file(): string {
  return dirname(__DIR__, 2) . '/data/sms-reminders.json';
}
function sms_reminder_state(): array {
  $f = sms_reminder_state_file();
  if (!is_file($f)) return [];
  $j = json_decode((string)@file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function sms_reminder_state_save(array $s): void {
  $f = sms_reminder_state_file();
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* آیا امروز برای این کافه SMS رفته؟ */
function sms_reminder_sent_today(string $slug, string $level): bool {
  $state = sms_reminder_state();
  $today = date('Y-m-d');
  $key = "$slug:$level";
  return isset($state[$key]) && $state[$key] === $today;
}
function sms_reminder_mark_sent(string $slug, string $level): void {
  $state = sms_reminder_state();
  $today = date('Y-m-d');
  $key = "$slug:$level";
  $state[$key] = $today;
  /* پاکسازی رکوردهای قدیمی‌تر از ۷ روز */
  $cutoff = date('Y-m-d', time() - 7 * 86400);
  foreach ($state as $k => $v) {
    if ($v < $cutoff) unset($state[$k]);
  }
  sms_reminder_state_save($state);
}

/* ارسال SMS یادآوری با کلید پلتفرم */
function sms_reminder_send(string $phone, string $cafe, int $days, string $template): bool {
  $cfgFile = dirname(__DIR__, 2) . '/config.php';
  if (!is_file($cfgFile)) return false;
  $cfg = require $cfgFile;
  $s = $cfg['sms'] ?? [];
  if (empty($s['key']) || empty($template)) return false;

  $phone = preg_replace('/\D/', '', $phone);
  if (!preg_match('/^09\d{9}$/', $phone)) return false;

  switch ($s['provider'] ?? 'kavenegar') {
    case 'kavenegar':
      $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
           . '/verify/lookup.json?receptor=' . rawurlencode($phone)
           . '&token=' . rawurlencode((string)$days)
           . '&template=' . rawurlencode($template);
      break;
    default:
      return false;
  }

  $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
  $res = @file_get_contents($url, false, $ctx);
  if ($res === false) return false;
  $j = json_decode($res, true);
  return is_array($j) && ($j['return']['status'] ?? 500) === 200;
}

/* اجرای کامل: چک همه کافه‌ها و ارسال SMS */
function sms_reminder_run(): array {
  $cfgFile = dirname(__DIR__, 2) . '/config.php';
  if (!is_file($cfgFile)) return ['sent' => 0, 'skipped' => 0, 'reason' => 'no config'];
  $cfg = require $cfgFile;
  $smsCfg = $cfg['sms'] ?? [];

  $hasKey = !empty($smsCfg['key']);
  $tplExpiry = (string)($smsCfg['template_expiry'] ?? '');
  $tplExpired = (string)($smsCfg['template_expired'] ?? '');

  if (!$hasKey) {
    return ['sent' => 0, 'skipped' => 0, 'reason' => 'no_sms_key'];
  }

  if (!function_exists('plat_list')) {
    require_once __DIR__ . '/platform-admin.php';
  }
  $list = plat_list();
  $sent = 0; $skipped = 0;

  foreach ($list as $x) {
    $slug = (string)($x['slug'] ?? '');
    $phone = (string)($x['phone'] ?? '');
    $name = (string)($x['name'] ?? $slug);
    $lic = $x['lic'] ?? [];
    $status = (string)($lic['status'] ?? '');
    $days = (int)($lic['days_left'] ?? 999);

    if ($phone === '') { $skipped++; continue; }
    if (!in_array($status, ['active', 'trial'], true)) { $skipped++; continue; }

    /* کافه‌ای که منقضی شده (روز منفی یا صفر) */
    if ($days <= 0) {
      $level = 'expired';
      $tpl = $tplExpired !== '' ? $tplExpired : $tplExpiry;
      if ($tpl === '') { $skipped++; continue; }
      if (sms_reminder_sent_today($slug, $level)) { $skipped++; continue; }
      if (sms_reminder_send($phone, $name, 0, $tpl)) {
        sms_reminder_mark_sent($slug, $level);
        $sent++;
      }
      continue;
    }

    /* یادآوری ۳ روز */
    if ($days <= 3) {
      $level = 'urgent';
      if ($tplExpiry === '') { $skipped++; continue; }
      if (sms_reminder_sent_today($slug, $level)) { $skipped++; continue; }
      if (sms_reminder_send($phone, $name, $days, $tplExpiry)) {
        sms_reminder_mark_sent($slug, $level);
        $sent++;
      }
      continue;
    }

    /* یادآوری ۷ روز */
    if ($days <= 7) {
      $level = 'week';
      if ($tplExpiry === '') { $skipped++; continue; }
      if (sms_reminder_sent_today($slug, $level)) { $skipped++; continue; }
      if (sms_reminder_send($phone, $name, $days, $tplExpiry)) {
        sms_reminder_mark_sent($slug, $level);
        $sent++;
      }
      continue;
    }

    $skipped++;
  }

  return ['sent' => $sent, 'skipped' => $skipped, 'reason' => 'ok'];
}
/* ═══ پایان SMS_REMINDER_V1 ═══ */
