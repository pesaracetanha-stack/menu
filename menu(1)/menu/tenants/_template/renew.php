<?php
declare(strict_types=1);
/* صفحه تمدید اشتراک — کافه‌دار */

$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}

/* CSRF_V1 */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$root = dirname(__DIR__, 2);
require_once $root . '/api/lib/zarinpal.php';
require_once $root . '/api/lib/paths.php';

/* کانفیگ پلتفرم */
$cfg = require $root . '/config.php';
$plans = giti_get_plans();
$zp = $cfg['zarinpal'] ?? [];
$zpEnabled = !empty($zp['enabled']);

/* لایسنس فعلی */
$lic = ['status'=>'trial', 'days_left'=>0, 'plan'=>'free', 'expires'=>0];
try {
  $dbf = __DIR__ . '/data/db.sqlite';
  if (is_file($dbf)) {
    $pdo = new PDO('sqlite:' . $dbf);
    $stmt = $pdo->prepare("SELECT v FROM doc WHERE scope=? AND k='db' LIMIT 1");
    $stmt->execute([$slug]);
    $raw = $stmt->fetchColumn();
    if ($raw) {
      $j = json_decode($raw, true);
      if (is_array($j['license'] ?? null)) {
        $l = $j['license'];
        $exp = (float)($l['paid_until'] ?? $j['license']['expires'] ?? 0);
        $lic = [
          'status' => (string)($l['status'] ?? 'trial'),
          'plan' => (string)($l['plan'] ?? 'free'),
          'expires' => $exp,
          'days_left' => $exp > 0 ? (int)ceil(($exp - (time() * 1000)) / 86400000) : 0,
        ];
      }
    }
  }
} catch (Throwable $e) {}

$currentPlan = $lic['plan'] ?? 'free';
$daysLeft = max(0, (int)($lic['days_left'] ?? 0));
$user = (string)($_SESSION['tenant_user'] ?? 'مدیر');

$msg = ''; $err = '';
$act = (string)($_POST['act'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act === 'start') {
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    $err = 'نشست منقضی شده — صفحه را رفرش کنید';
  } elseif (!$zpEnabled) {
    $err = 'پرداخت آنلاین هنوز فعال نشده — با پشتیبانی تماس بگیرید';
  } else {
    $plan = (string)($_POST['plan'] ?? '');
    $months = (int)($_POST['months'] ?? 1);
    if (!isset($plans[$plan])) {
      $err = 'پلن نامعتبر';
    } elseif ($months < 1 || $months > 12) {
      $err = 'مدت نامعتبر';
    } elseif ((int)($plans[$plan]['price_toman'] ?? 0) <= 0) {
      $err = 'این پلن رایگان است و نیازی به پرداخت ندارد';
    } else {
      $amountToman = (int)$plans[$plan]['price_toman'] * $months;
      $amountRial = $amountToman * 10;
      $days = $months * 30;

      /* محاسبه callback URL */
      $sch = $secure ? 'https' : 'http';
      $host = $_SERVER['HTTP_HOST'] ?? '';
      $dirb = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
      $callback = $sch . '://' . $host . $dirb . '/payment-callback.php';

      $txId = zp_tx_create($slug, $amountToman, $plan, $days);
      $client = zp_client();
      if ($client === null) {
        $err = 'کلاینت زرین‌پال آماده نیست';
      } else {
        $r = $client->request($amountRial, "تمدید اشتراک $months ماهه - پلن $plan", $callback, (string)($_SESSION['tenant_user'] ?? ''));
        if (!$r['ok']) {
          zp_tx_update($txId, ['status' => 'failed_request', 'error' => $r['error'] ?? '']);
          $err = 'خطا در ایجاد پرداخت: ' . ($r['error'] ?? 'نامشخص');
        } else {
          $auth = (string)$r['authority'];
          zp_tx_update($txId, ['status' => 'redirected']);
          header('Location: ' . $client->getPaymentUrl($auth));
          exit;
        }
      }
    }
  }
}

$typeFa = ['free' => '🌱 رایگان', 'basic' => '⚡ پایه', 'pro' => '👑 حرفه‌ای'];

$success = (string)($_GET['success'] ?? '') === '1';
$failed = (string)($_GET['failed'] ?? '') === '1';
$refId = (string)($_GET['ref'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تمدید اشتراک — <?= htmlspecialchars($slug) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ── پالت رنگی اختصاصی شما ── */
:root {
  --bg-canvas: #EFE7D6; --bg-surface: #FBF6EB; --bg-surface-hover: #F2EADA;
  --bg-sidebar: #271D12; --border-subtle: #DFD2B6; --text-primary: #271D12;
  --text-secondary: #7D6C54; --text-inverse: #FBF6EB;
  --accent-500: #B4531F; --accent-600: #9A4517;
  --success-500: #4F7A3D; --warning-500: #B07C1F; --danger-500: #A93B2A;
  --radius-md: 10px; --radius-lg: 16px; --shadow-sm: 0 1px 3px rgba(39, 29, 18, 0.05);
  --font-family: 'Vazirmatn', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); background: var(--bg-canvas); color: var(--text-primary); display: flex; min-height: 100vh; line-height: 1.8; }

/* ── Layout ── */
.app-layout { display: flex; width: 100%; }
.main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; }
.topbar { height: 64px; background: var(--bg-surface); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; }
.content-area { padding: 24px; flex: 1; overflow-y: auto; }

/* ── Sidebar ── */
.sidebar { width: 260px; background: var(--bg-sidebar); color: var(--text-inverse); display: flex; flex-direction: column; flex-shrink: 0; }
.brand { padding: 24px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--bg-surface); }
.nav-menu { list-style: none; padding: 16px 0; flex: 1; }
.nav-item { padding: 12px 24px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background 0.2s; color: #CBBB99; font-weight: 500; text-decoration: none; position: relative; }
.nav-item:hover { background: rgba(255,255,255,0.05); color: var(--bg-surface); }
.nav-item.active { background: var(--accent-500); color: white; border-right: 4px solid #E59A6B; }
.nav-item .icon { font-size: 18px; }
.nav-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 16px 24px; }
.sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); }
.btn-outline-sidebar { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: var(--radius-md); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: white; width: 100%; transition: all 0.2s; text-decoration:none; }
.btn-outline-sidebar:hover { background: rgba(255,255,255,0.1); }

/* ── Components ── */
.card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.card h2 { font-size: 16px; font-weight: 800; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px dashed var(--border-subtle); }
.page-title { font-size: 18px; font-weight: 700; }
.user-badge { background: var(--bg-canvas); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-subtle); }

/* ── Plan Cards ── */
.status-box { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.status-box .info { display: flex; gap: 32px; flex-wrap: wrap; font-size: 14px; }
.status-box .info b { color: var(--text-primary); font-size: 20px; font-weight: 900; }
.pill { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; }
.pill.ok { background: #E8F4E3; color: var(--success-500); }
.pill.warn { background: #FBEAE4; color: var(--danger-500); }

.plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 8px; }
.plan-card { background: #fff; border: 1.5px solid var(--border-subtle); border-radius: 14px; padding: 20px; cursor: pointer; transition: all .2s; position: relative; }
.plan-card:hover { border-color: var(--accent-500); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.plan-card.on { border-color: var(--accent-500); border-width: 2px; box-shadow: 0 4px 12px rgba(180,83,31,.12); }
.plan-card .pname { font-size: 16px; font-weight: 800; margin-bottom: 10px; }
.plan-card .pprice { font-size: 24px; font-weight: 900; color: var(--accent-500); }
.plan-card .pprice span { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.plan-card.current::before { content: 'پلن فعلی'; position: absolute; top: -10px; right: 16px; background: var(--success-500); color: #fff; padding: 2px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }

.dur-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
.dur-btn { padding: 12px 24px; border-radius: 12px; border: 1.5px solid var(--border-subtle); background: #fff; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 700; transition: all .15s; }
.dur-btn:hover { border-color: var(--accent-500); }
.dur-btn.on { background: var(--text-primary); color: #fff; border-color: var(--text-primary); }

.total { background: var(--bg-surface-hover); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
.total b { font-size: 24px; font-weight: 900; color: var(--accent-500); }

.btn { display: inline-block; padding: 14px 32px; background: var(--accent-500); color: #fff; border: 0; border-radius: 12px; font-family: inherit; font-size: 16px; font-weight: 800; cursor: pointer; transition: all .2s; width: 100%; text-align: center; }
.btn:hover { background: var(--accent-600); transform: translateY(-2px); }
.btn:disabled { background: #ccc; cursor: not-allowed; transform: none; }

.alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; line-height: 1.8; }
.alert.ok { background: #E8F4E3; border: 1px solid #BFDCA8; color: var(--success-500); }
.alert.err { background: #FBEAE4; border: 1px solid #EFC5B6; color: var(--danger-500); }
.warn-box { background: #FFF8E1; border: 1px solid #F4E4A1; border-radius: 12px; padding: 16px 20px; font-size: 14px; color: #7A5C00; line-height: 1.9; margin-bottom: 16px; }
</style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">💳 تمدید اشتراک</div>
      <div class="user-badge"><?= htmlspecialchars($user) ?></div>
    </header>

    <div class="content-area">
      <?php if ($success): ?>
        <div class="alert ok">✅ پرداخت شما با موفقیت انجام شد — اشتراک تمدید گردید.
          <?php if ($refId !== ''): ?><br>شماره پیگیری: <b dir="ltr"><?= htmlspecialchars($refId) ?></b><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($failed): ?>
        <div class="alert err">⚠️ پرداخت لغو شد یا ناموفق بود. می‌توانید دوباره تلاش کنید.</div>
      <?php endif; ?>
      <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

      <!-- وضعیت فعلی -->
      <div class="card">
        <h2>وضعیت فعلی اشتراک</h2>
        <div class="status-box">
          <div class="info">
            <div>
              <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px">پلن فعلی</div>
              <b><?= $typeFa[$currentPlan] ?? $currentPlan ?></b>
            </div>
            <div>
              <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px">روزهای باقی‌مانده</div>
              <b style="color:<?= $daysLeft > 7 ? 'var(--success-500)' : 'var(--danger-500)' ?>"><?= $daysLeft ?> روز</b>
            </div>
          </div>
          <?php if ($daysLeft > 7): ?>
            <span class="pill ok">فعال</span>
          <?php elseif ($daysLeft > 0): ?>
            <span class="pill warn">نزدیک انقضا</span>
          <?php else: ?>
            <span class="pill warn">منقضی</span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$zpEnabled): ?>
        <div class="warn-box">
          ⚠️ <b>پرداخت آنلاین هنوز فعال نشده است.</b><br>
          برای تمدید اشتراک، با پشتیبانی GitiArts تماس بگیرید:
          <br>📱 <span dir="ltr">+98 919 834 5661</span>
        </div>
      <?php else: ?>
        <!-- انتخاب پلن -->
        <form method="post" id="renewForm">
          <input type="hidden" name="act" value="start">
          <input type="hidden" name="plan" id="planInput" value="<?= $currentPlan !== 'free' ? $currentPlan : 'basic' ?>">
          <input type="hidden" name="months" id="monthsInput" value="1">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

          <div class="card">
            <h2>۱. پلن مورد نظر</h2>
            <div class="plan-grid">
              <?php foreach ($plans as $key => $p): ?>
                <?php if ((int)($p['price_toman'] ?? 0) <= 0) continue; ?>
                <div class="plan-card <?= $currentPlan === $key ? 'current' : '' ?>" data-plan="<?= htmlspecialchars($key) ?>" data-price="<?= (int)$p['price_toman'] ?>">
                  <div class="pname"><?= htmlspecialchars($p['name_fa']) ?></div>
                  <div class="pprice"><?= number_format((int)$p['price_toman']) ?><span> تومان / ماه</span></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card">
            <h2>۲. مدت اشتراک</h2>
            <div class="dur-row">
              <button type="button" class="dur-btn on" data-m="1">۱ ماه</button>
              <button type="button" class="dur-btn" data-m="3">۳ ماه</button>
              <button type="button" class="dur-btn" data-m="6">۶ ماه</button>
              <button type="button" class="dur-btn" data-m="12">۱۲ ماه</button>
            </div>

            <div class="total">
              <span style="font-size:15px;color:var(--text-secondary);font-weight:600">مبلغ قابل پرداخت:</span>
              <b id="totalPrice">—</b>
            </div>
          </div>

          <button type="submit" class="btn">🔒 انتقال به درگاه پرداخت</button>
          <p style="font-size:13px;color:var(--text-secondary);text-align:center;margin-top:16px">پرداخت امن از طریق زرین‌پال</p>
        </form>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php if ($zpEnabled): ?>
<script>
'use strict';
var selectedPlan = document.getElementById('planInput').value;
var selectedMonths = 1;
var planPrices = {};

document.querySelectorAll('.plan-card').forEach(function(c) {
  planPrices[c.dataset.plan] = parseInt(c.dataset.price, 10) || 0;
  if (c.dataset.plan === selectedPlan) c.classList.add('on');
  c.addEventListener('click', function() {
    document.querySelectorAll('.plan-card').forEach(function(x) { x.classList.remove('on'); });
    c.classList.add('on');
    selectedPlan = c.dataset.plan;
    document.getElementById('planInput').value = selectedPlan;
    updateTotal();
  });
});

document.querySelectorAll('.dur-btn').forEach(function(b) {
  b.addEventListener('click', function() {
    document.querySelectorAll('.dur-btn').forEach(function(x) { x.classList.remove('on'); });
    b.classList.add('on');
    selectedMonths = parseInt(b.dataset.m, 10);
    document.getElementById('monthsInput').value = selectedMonths;
    updateTotal();
  });
});

function updateTotal() {
  var price = planPrices[selectedPlan] || 0;
  var total = price * selectedMonths;
  var el = document.getElementById('totalPrice');
  if (el) el.textContent = total.toLocaleString('fa-IR') + ' تومان';
}

updateTotal();
</script>
<?php endif; ?>
</body>
</html>