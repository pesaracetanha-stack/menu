<?php
declare(strict_types=1);
/* v5: display_errors حذف شد — خطاها فقط از مسیر exception handler بیرون می‌رود */

const SAVE_KEYS = ['menu','tables','settings','promos','users','reservations','shift','customPalettes','setup','invoices'];

function d_save(string $key, $val): array {
  if (!in_array($key, SAVE_KEYS, true)) throw new Exception('کلید ذخیره نامعتبر');
  if (!is_array($val)) throw new Exception('داده نامعتبر');
  return t_db_txn(function (array &$db) use ($key, $val) {
    $db[$key] = $val;
    return t_state_out($db);
  });
}
function save_menu($in)         { return d_save('menu', $in); }
function save_tables($in)       { return d_save('tables', $in); }
function save_settings($in)     { return d_save('settings', $in); }
function save_promos($in)       { return d_save('promos', $in); }
/* v5.0.2: رمز پرسنل هرگز متنی ذخیره نمی‌شود — اینجا هش و متنش حذف می‌شود
   v5.0.5: سیاست رمز — حداقل ۸ با حرف + عدد، رد رمزهای رایج (هم‌راستا با ثبت‌نام) */
function save_users($in): array {
  if (is_array($in)) {
    foreach ($in as $i => $u) {
      if (!is_array($u)) continue;
      if (isset($u['pass']) && is_string($u['pass']) && $u['pass'] !== '') {
        d_check_new_pass($u['pass']);
        $u['ph'] = password_hash($u['pass'], PASSWORD_DEFAULT);
      }
      unset($u['pass']);
      $in[$i] = $u;
    }
  }
  return d_save('users', $in);
}
function save_reservations($in) { return d_save('reservations', $in); }
function save_palettes($in)     { return d_save('customPalettes', $in); }
function save_shift($in)        { return d_save('shift', $in); }
function save_setup($in)        { return d_save('setup', $in); }
/* v5.0.3: فاکتورهای پیامکی صندوق — قبلاً اندپوینت وجود نداشت و فاکتور ذخیره نمی‌شد */
function save_invoices($in)     { return d_save('invoices', $in); }

/* ═══ v5.0.5: سیاست رمز مشترک + هشدار رمز ضعیف ═══
   فهرست رمزهای رایج — همان فهرست ثبت‌نام؛ پایهٔ آزمون ضعیف‌بودن هم هست */
function d_common_pw(): array {
  return ['12345678','123456789','1234567890','1234567891','11223344','11111111','00000000','12121212',
    '12341234','1234567a','123456aa','aa123456','a12345678','qwerty123','1q2w3e4r','q1w2e3r4',
    'password','password1','passw0rd','abcd1234','abcdefgh','iloveyou','admin1234','admin12345',
    '88888888','66666666','99999999','147258369','987654321','9876543210','iran1234','iran12345',
    '0912123456','0912345678','coffee123','cafe12345','gitiarts1'];
}
/* رمز تازهٔ هر حساب (مدیر/پرسنل): حداقل ۸ کاراکتر با حداقل یک حرف و یک عدد؛
   رمزهای رایج جهان رد می‌شوند (حروف فارسی مثل ثبت‌نام پذیرفته است) */
function d_check_new_pass(string $pass): void {
  if (strlen($pass) < 8
    || !preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $pass)
    || !preg_match('/\d/', $pass))
    throw new Exception('رمز باید حداقل ۸ کاراکتر باشد و حداقل یک حرف و یک عدد داشته باشد');
  if (in_array(strtolower($pass), d_common_pw(), true))
    throw new Exception('این رمز جزو رایج‌ترین رمزهای هک‌شده است — رمز قوی‌تر و غیرقابل حدس انتخاب کنید');
}
/* آزمودن حدس‌های رایج روی هش — ضعیف‌بودن را بدون دانستن رمز تشخیص می‌دهد.
   خروجی فقط «ضعیف/نوع» است؛ خود حدس هرگز به کلاینت نمی‌رود */
function d_pw_guess(string $ph, string $phone, int $maxDict): array {
  if ($ph === '') return ['weak' => false, 'kind' => ''];
  $d = preg_replace('/\D/', '', $phone);
  $cands = array_slice(d_common_pw(), 0, $maxDict);
  if ($d !== '') { $cands[] = $d; if (strlen($d) === 11 && $d[0] === '0') $cands[] = substr($d, 1); }
  foreach ($cands as $c) {
    if ($c !== '' && password_verify($c, $ph))
      return ['weak' => true,
              'kind' => ($d !== '' && ($c === $d || (strlen($d) === 11 && substr($d, 1) === $c))) ? 'phone' : 'dict'];
  }
  return ['weak' => false, 'kind' => ''];
}
/* ═══ v5.0.5: بررسی رمزهای ضعیف — فقط مدیر (adminPost) ═══
   نتیجه در auth.pwaudit با «اثر انگشت هش» کش می‌شود؛ تا رمز عوض نشود دوباره
   محاسبه نمی‌شود (bcrypt کند است). force=true → همه از نو.
   خروجی: {ts, admin:{fp,weak,kind}, users:{id:{fp,weak,kind}}} */
function pw_audit($in): array {
  $force = !empty($in['force']);
  return t_db_txn(function (array &$db) use ($force) {
    $cache = is_array($db['auth']['pwaudit'] ?? null) ? $db['auth']['pwaudit'] : [];
    $fresh = ['ts' => time(), 'admin' => null, 'users' => []];

    /* رمز مدیر */
    $ph = (string)($db['auth']['ph'] ?? '');
    $fp = $ph === '' ? '' : hash('sha256', $ph);
    $old = is_array($cache['admin'] ?? null) ? $cache['admin'] : [];
    if (!$force && $fp !== '' && ($old['fp'] ?? '') === $fp && array_key_exists('weak', $old)) {
      $fresh['admin'] = $old;
    } else {
      $g = d_pw_guess($ph, (string)($db['auth']['phone'] ?? ''), 60);
      $fresh['admin'] = ['fp' => $fp, 'weak' => $g['weak'], 'kind' => $g['kind']];
    }

    /* رمزهای پرسنل — حساب بدون رمز (فقط پین) جدا گزارش می‌شود */
    foreach (($db['users'] ?? []) as $u) {
      if (!is_array($u)) continue;
      $uid = (string)($u['id'] ?? '');
      if ($uid === '') continue;
      $ph = (string)($u['ph'] ?? '');
      if ($ph === '') { $fresh['users'][$uid] = ['fp' => '', 'weak' => false, 'kind' => 'nopass']; continue; }
      $fp  = hash('sha256', $ph);
      $old = is_array($cache['users'][$uid] ?? null) ? $cache['users'][$uid] : [];
      if (!$force && ($old['fp'] ?? '') === $fp && array_key_exists('weak', $old)) {
        $fresh['users'][$uid] = $old;
      } else {
        $g = d_pw_guess($ph, (string)($u['phone'] ?? ''), 20);
        $fresh['users'][$uid] = ['fp' => $fp, 'weak' => $g['weak'], 'kind' => $g['kind']];
      }
    }
    $db['auth']['pwaudit'] = ['ts' => $fresh['ts'], 'admin' => $fresh['admin'], 'users' => $fresh['users']];
    return $fresh;
  });
}

/* وضعیت کامل — فقط مدیر */
function state($in): array {
  return t_state_out(t_db_get());
}
function t_state_admin(array $db): array {
  unset($db['auth']['ph']);                       // هش رمز هرگز بیرون نمی‌رود
  $db['lic']  = t_license($db);
  $db['slug'] = T_SLUG;
  $db['eta']  = t_eta($db);
  return $db;
}
/* v5.0.3: خروجی بر اساس نقش — پرسنل صندوق/آشپزخانه هرگز auth و users (فهرست و هش رمز پرسنل)
   را دریافت نمی‌کند؛ همهٔ پاسخ‌های state و save_* و order_* از این مسیر می‌روند */
function t_state_out(array $db): array {
  $db = t_state_admin($db);
  /* v5.0.5-test.5: پنل پیامکی — کلید/رمز هرگز خام به مرورگر نمی‌رود (حتی برای مدیر)؛
     پرسنل فقط پرچم فعال بودن را می‌بینند (برای دکمهٔ پیامک فاکتور) */
  if (isset($db['sms']) && is_array($db['sms'])) {
    if (function_exists('t_is_staff') && t_is_staff() && function_exists('sms_cfg')) {
      /* پرسنل: فقط پرچم فعال بودن + درایور (برای دکمهٔ پیامک فاکتور) */
      $db['sms'] = ['en' => !empty($db['sms']['en']), 'prov' => (string)($db['sms']['prov'] ?? '')];
    } elseif (function_exists('sms_cfg')) {
      $db['sms'] = sms_pub(sms_cfg($db['sms']));
    }
  }
  if (function_exists('t_is_staff') && t_is_staff()) {
    unset($db['auth']);
    unset($db['users']);
  }
  return $db;
}
/* منوی عمومی — برای مشتری، بدون دادهٔ خصوصی */
function menu($in): array {
  $db  = t_db_get();
  $lic = t_license($db);
  return [
    'slug'     => T_SLUG,
    'menu'     => $db['menu'] ?? [],
    'settings' => $db['settings'] ?? [],
    'setup'    => $db['setup'] ?? ['done' => true],
    'open'     => $lic['ok'],
    'lic'      => ['status' => $lic['status'], 'days_left' => $lic['days_left']],
    'eta'      => t_eta($db),
  ];
}

/* ═══ بازیابی رمز مدیر با موبایل ثبت‌شده (عمومی — v5.0.1) ═══
   پیش‌تر فلوی «رمز را فراموش کرده‌ام» در سرور وجود نداشت؛ با باگ txn هش رمز
   برخی کافه‌ها سوخته — این اندپوینت راه بازگشت است. کلید پیامک هنوز خالی است،
   لذا تأیید = تطابق شمارهٔ موبایل ثبت‌شدهٔ کافه. */
function reset_pass(array $in): array {
  $phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
  $pass  = (string)($in['pass'] ?? '');
  if (!preg_match('/^09\d{9}$/', $phone)) throw new Exception('شمارهٔ موبایل معتبر نیست');
  d_check_new_pass($pass);   /* v5.0.5: هم‌راستا با ثبت‌نام — رد رمزهای رایج هم اضافه شد */
  return t_db_txn(function (array &$db) use ($phone, $pass) {
    $dbPhone = preg_replace('/\D/', '', (string)($db['auth']['phone'] ?? ''));
    if ($dbPhone === '' || $dbPhone !== $phone)
      throw new Exception('این شماره با موبایل ثبت‌شدهٔ کافه یکسان نیست');
    $db['auth']['ph'] = password_hash($pass, PASSWORD_DEFAULT);
    return ['ok' => true];
  });
}

/* پشتیبان کامل داده‌ها — فقط مدیر کافه */
function backup($in) {
  if (ob_get_level() > 0) { ob_end_clean(); }
  $raw = t_db_raw();
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="backup-' . T_SLUG . '-' . date('ymd_His') . '.json"');
  header('Content-Length: ' . strlen($raw));
  echo $raw;
  exit;
}

/* ═══ درخواست فایل نصبی (تیکت) — فقط مدیر کافه ═══
   روی پلتفرم: تیکت برای مدیر پلتفرم ثبت می‌شود؛
   در بستهٔ مستقل: خطای راهنما برمی‌گردد. */
function export_request($in) {
  if (xc_is_standalone())
    return ['mode' => 'standalone',
            'msg'  => 'این نسخه مستقل است — برای دریافت مجدد فایل نصبی با پشتیبانی تلگرام در تماس باشید'];
  $db  = t_db_get();
  $res = xc_client_request(T_SLUG, (string)($in['note'] ?? ''),
                           (string)($db['auth']['user'] ?? T_SLUG));
  return ['mode' => 'platform', 'id' => $res['id'], 'dup' => $res['dup'],
          'msg'  => $res['dup'] ? 'درخواست شما قبلاً ثبت شده و در انتظار تأیید است'
                                : 'درخواست شما ثبت شد — پس از تأیید مدیر، لینک دانلود همین‌جا نمایش داده می‌شود'];
}
/* وضعیت آخرین درخواست — فقط مدیر کافه */
function export_status($in): array {
  $s = xc_client_status(T_SLUG);
  if (($s['req'] ?? null) && ($s['req']['status'] ?? '') === 'approved')
    unset($s['req']['token']);              // هش توکن هرگز بیرون نمی‌رود
  return $s;
}

/* ═══ v5.0.5-test.1: سیستم نوتیفیکیشن — ارسال دستی مدیر + خواندن + کانال عمومی مشتری ═══ */

/* ارسال پیام دستی مدیر به یک نقش (یا همه) */
function notify_send(array $in): array {
  $to   = (string)($in['to'] ?? '');
  $title = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['title'] ?? ''))), 0, 80);
  $body  = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($in['body'] ?? ''))), 0, 200);
  if (!in_array($to, ['admin', 'cashier', 'kitchen', 'all'], true)) throw new Exception('مقصد پیام نامعتبر است');
  if (mb_strlen($title) < 2) throw new Exception('متن پیام را بنویسید');
  return t_db_txn(function (array &$db) use ($to, $title, $body) {
    $targets = $to === 'all' ? ['admin', 'cashier', 'kitchen'] : [$to];
    foreach ($targets as $t)
      t_notify($db, $t, $title, $body);
    return t_state_out($db);
  });
}

/* علامت‌گذاری پیام‌های یک نقش به «خوانده‌شده» — مدیر همه را می‌تواند، پرسنل فقط صندوق/آشپزخانه */
function notifs_read(array $in): array {
  $to = (string)($in['to'] ?? '');
  if (!in_array($to, ['admin', 'cashier', 'kitchen'], true)) throw new Exception('مقصد نامعتبر است');
  if (!t_is_admin() && !in_array($to, ['cashier', 'kitchen'], true))
    throw new Exception('دسترسی فقط برای مدیر کافه');
  return t_db_txn(function (array &$db) use ($to) {
    $n = 0;
    foreach ($db['notifs'] as &$x)
      if (($x['to'] ?? '') === $to && empty($x['read'])) { $x['read'] = true; $n++; }
    unset($x);
    return ['state' => t_state_out($db), 'marked' => $n];
  });
}

/* کانال عمومی مشتری — پیام‌های همبان موبایل + وضعیت رزروهای او (بدون هیچ دیتای خصوصی دیگر) */
function notifs_pub(array $in): array {
  $phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
  if (!preg_match('/^09\d{9}$/', $phone)) throw new Exception('شمارهٔ موبایل معتبر نیست');
  $db = t_db_get();
  $notifs = array_values(array_filter($db['notifs'] ?? [],
    fn($n) => (($n['to'] ?? '') === 'customer' && ($n['phone'] ?? '') === $phone)));
  $notifs = array_slice($notifs, -15);
  $t0 = strtotime('today') * 1000;
  $resv = array_values(array_filter($db['reservations'] ?? [],
    fn($r) => (($r['phone'] ?? '') === $phone && ($r['ts'] ?? 0) >= $t0 - 86400e3 && in_array($r['status'] ?? '', ['pending', 'active'], true))));
  $resv = array_map(fn($r) => ['ts' => $r['ts'], 'persons' => $r['persons'] ?? 0,
    'status' => $r['status'], 'tableNo' => $r['tableNo'] ?? 0], $resv);
  return ['notifs' => $notifs, 'reservations' => $resv];
}
