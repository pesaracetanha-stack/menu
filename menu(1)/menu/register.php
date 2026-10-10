<?php
declare(strict_types=1);
/* BUILD: reg-v4 */

error_reporting(E_ALL);
ob_start();

require __DIR__ . '/api/lib/paths.php';
require_once __DIR__ . '/api/lib/platform-auth.php';
require_once __DIR__ . '/api/lib/sms.php';
require_once __DIR__ . '/rate-limit.php';

function p_json_dbg(bool $ok, ?string $err = null, $data = null): void {
  while (ob_get_level() > 0) { ob_end_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => $ok, 'err' => $err, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

/* v5.0.4-test.4: پیام خطای قابل فهم — استثنای فنی هرگز خام به کلاینت نمی‌رود */
set_exception_handler(function (Throwable $e) {
  $msg = $e->getMessage();
  if (!preg_match('/[\x{0600}-\x{06FF}]/u', $msg)) {
    error_log('[gitiarts-register] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = 'خطای غیرمنتظرهٔ سرور — چند لحظه بعد دوباره تلاش کنید؛ اگر تکرار شد با پشتیبانی تماس بگیرید';
  }
  p_json_dbg(false, $msg);
});
set_error_handler(function ($no, $str, $file, $line) {
  throw new ErrorException($str, 0, $no, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') p_json_dbg(false, 'متد نامعتبر');

$raw = (string)file_get_contents('php://input');
$in  = json_decode($raw, true) ?: $_POST;   /* fallback: هاست‌هایی که JSON بدن درست نمی‌رسانند + پروب CLI */
$name  = trim((string)($in['name']  ?? ''));
$slug  = strtolower(trim((string)($in['slug']  ?? '')));
$phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
$pass  = (string)($in['pass'] ?? '');
$cfg   = p_config();

if (mb_strlen($name) < 2 || mb_strlen($name) > 60)
  p_json_dbg(false, 'نام کسب‌وکار را درست وارد کنید');
if (!p_slug_valid($slug))
  p_json_dbg(false, 'آدرس اختصاصی نامعتبر است — فقط حروف انگلیسی کوچک، عدد و خط‌تیره');
if (!preg_match('/^09\d{9}$/', $phone))
  p_json_dbg(false, 'شمارهٔ موبایل معتبر نیست');
/* v5.0.2: رمز باید حرف + عدد + علامت (@#!…) را با هم داشته باشد؛ حروف فارسی هم پذیرفته می‌شود (هم‌راستا با فرم) */
if (strlen($pass) < 8
  || !preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $pass)
  || !preg_match('/\d/', $pass)
  || !preg_match('/[^A-Za-z0-9\s\x{0600}-\x{06FF}]/u', $pass))
  p_json_dbg(false, 'رمز باید حداقل ۸ کاراکتر باشد و حداقل یک حرف، یک عدد و یک علامت مثل @ # ! داشته باشد');

/* v5.0.1: هشدار رمزهای رایج — پرتکرارترین رمزهای هک‌شدهٔ جهان قابل قبول نیستند */
$COMMON_PW = [
  '12345678','123456789','1234567890','1234567891','11223344','11111111','00000000','12121212',
  '12341234','1234567a','123456aa','aa123456','a12345678','qwerty123','1q2w3e4r','q1w2e3r4',
  'password','password1','passw0rd','abcd1234','abcdefgh','iloveyou','admin1234','admin12345',
  '88888888','66666666','99999999','147258369','987654321','9876543210','iran1234','iran12345',
  '0912123456','0912345678','coffee123','cafe12345','gitiarts1',
];
if (in_array(strtolower($pass), $COMMON_PW, true))
  p_json_dbg(false, 'این رمز جزو رایج‌ترین رمزهای هک‌شده است — یک رمز قوی‌تر و غیرقابل حدس انتخاب کنید');

function copy_tree(string $src, string $dst): void {
  if (!is_dir($dst)) mkdir($dst, 0755, true);
  foreach (scandir($src) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $s = "$src/$f"; $d = "$dst/$f";
    if (is_dir($s)) copy_tree($s, $d);
    else copy($s, $d);
  }
}

/* ═══ REGISTER_OTP_V1: تأیید پیامکی ثبت‌نام ═══ */
$otpCode = trim((string)($in['otp'] ?? ''));

if ($otpCode === '') {
  /* ── مرحلهٔ ۱: صدور و ارسال کد ── */
  $clientIp = rate_limit_client_ip();
  if (!rate_limit_check("reg:ip:$clientIp", 5, 3600)) {
    p_json_dbg(false, 'تلاش‌های بیش از حد از این دستگاه — یک ساعت دیگر امتحان کنید');
  }
  if (!rate_limit_check("reg:phone:$phone", 3, 3600)) {
    p_json_dbg(false, 'برای این شماره بیش از حد کد ارسال شد — یک ساعت دیگر امتحان کنید');
  }
  $tCheck = p_tenants_load();
  foreach ($tCheck['tenants'] as $x) {
    if (($x['slug'] ?? '') === $slug) {
      p_json_dbg(false, 'این آدرس قبلاً گرفته شده — یکی دیگر امتحان کنید');
    }
  }
  $smsOk = sms_issue_code($phone);
  $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost:8000','127.0.0.1:8000','localhost','127.0.0.1'], true);
  if (!$smsOk && !$isLocal) {
    p_json_dbg(false, 'ارسال پیامک تأیید ناموفق بود — با پشتیبانی تماس بگیرید');
  }
  $respData = [
    'need_otp'     => true,
    'phone_masked' => substr($phone, 0, 4) . '****' . substr($phone, -2),
  ];
  if ($isLocal) {
    $sec = sec_load();
    $respData['dev_code'] = $sec['otps'][$phone]['code'] ?? null;
  }
  p_json_dbg(true, null, $respData);
}

/* ── مرحلهٔ ۲: تأیید کد و ادامهٔ ساخت ── */
if (!sms_verify_code($phone, $otpCode)) {
  p_json_dbg(false, 'کد تأیید نادرست یا منقضی شده است');
}
/* ═══ پایان REGISTER_OTP_V1 ═══ */

try {
  $dst = p_lock(function (array &$t) use ($name, $slug, $phone, $pass, $cfg) {

    foreach ($t['tenants'] as $x) {
      if (($x['slug'] ?? '') === $slug) {
        throw new Exception('این آدرس قبلاً گرفته شده — یکی دیگر امتحان کنید');
      }
    }

    $src = $cfg['paths']['tenants'] . '/_template';
    $dst = $cfg['paths']['tenants'] . '/' . $slug;
    if (is_dir($dst)) throw new Exception('این آدرس قبلاً گرفته شده (پوشهٔ یتیم موجود است)');
    if (!is_dir($src)) throw new Exception('قالب نصب نشده — پوشهٔ tenants/_template یافت نشد');
    copy_tree($src, $dst);

    $now   = round(microtime(true) * 1000);
    $sched = array_fill(0, 7, ['o' => '10:00', 'c' => '23:00', 'x' => false]);

    $db = [
      'v' => 4,
      'setup' => ['done' => false],
      'auth' => ['user' => $name, 'phone' => $phone, 'ph' => password_hash($pass, PASSWORD_DEFAULT)],
      'menu' => [
        'venue' => 'cafe',
        'brand' => ['name'=>$name,'tagline'=>'','hours'=>'','address'=>'','logo'=>'','sched'=>$sched],
        'theme' => [
          'paletteId' => 'default',
          'colors' => ['bg'=>'#FBF6EB','ink'=>'#271D12','sub'=>'#7D6C54','line'=>'#DFD2B6','acc'=>'#B4531F'],
          'layout' => 'list',
          'bgMode' => 'color', 'bgImg' => '', 'bgVid' => '', 'bgG1' => '', 'bgG2' => '',
          'headMode' => 'plain', 'headImg' => '', 'headG1' => '', 'headG2' => '',
        ],
        'banners' => [], 'cats' => [],
      ],
      'orders' => [], 'customers' => [], 'invoices' => [], 'tables' => [],
      'customPalettes' => [], 'reviews' => [],
      'settings' => ['kds'=>true,'vatOn'=>true,'vatPct'=>9,'tipOn'=>true,'target'=>0,
        'hh'=>['on'=>false,'from'=>16,'to'=>18,'off'=>20]],
      'promos' => [], 'users' => [], 'reservations' => [], 'shift' => null,
      'counters' => ['order' => 1000],
      'license' => [
        'status'  => 'trial',
        'since'   => $now,
        'expires' => $now + $cfg['limits']['trial_days'] * 86400000,
        'key'     => 'SARU-' . strtoupper(bin2hex(random_bytes(4))),
      ],
      'created' => $now,
    ];

    $dd = $dst . '/data';
    if (!is_dir($dd)) mkdir($dd, 0755, true);
    file_put_contents($dd . '/db.json',
      json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    /* v5: بانک دادهٔ کافه ساخته و سند اولیه وارد می‌شود (SQLite خودکار) */
    require_once __DIR__ . '/api/lib/class-gstore.php';
    GStore::for($dst)->ensureSchema();

    if (!is_dir($dst . '/uploads'))      mkdir($dst . '/uploads', 0755, true);
    if (!is_dir($dst . '/data/backups')) mkdir($dst . '/data/backups', 0755, true);

    $t['tenants'][] = [
      'id' => $t['nextId']++, 'slug' => $slug, 'name' => $name, 'phone' => $phone,
      'active' => true, 'created' => $now, 'domain' => '', 'license' => $db['license']['key'],
    ];

    return $dst;
  });
} catch (Throwable $e) {
  $msg = $e->getMessage();
  if (!preg_match('/[\x{0600}-\x{06FF}]/u', $msg)) $msg = 'خطای غیرمنتظرهٔ سرور — چند لحظه بعد دوباره تلاش کنید';
  p_json_dbg(false, $msg);
}

/* ست کردن خودکار نشست مدیر تا مستقیماً وارد ویزارد شود */
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
session_regenerate_id(true);
$_SESSION['tenant'] = $slug;
$_SESSION['tenant_user'] = $name;

/* v5: مقصد از خود درخواست محاسبه می‌شود (قابل‌حمل) → صفحهٔ دسترسی‌های کافه */
$schR  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dirbR = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
p_json_dbg(true, null, ['url' => $schR . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $dirbR . '/tenants/' . $slug . '/hub.php']);
