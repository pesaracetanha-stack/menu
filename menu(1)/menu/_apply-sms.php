<?php
declare(strict_types=1);
/* _apply-sms.php — Adds SMS settings to Admin Panel and implements mydnspanel API */

$root = __DIR__;
$ts = date('Ymd_His');
$backups = [];

function fail(string $msg, array $backups): void {
    echo "\n❌ ERROR: $msg\n🔄 Rolling back...\n";
    foreach ($backups as $b) if (is_file($b['bak'])) { copy($b['bak'], $b['orig']); echo "  ↺ ".basename($b['orig'])."\n"; }
    exit(1);
}
function backup(string $file, string $ts, array &$backups): void {
    $bak = $file . '.bak.sms.' . $ts;
    if (!is_file($file)) fail("not found: $file", $backups);
    if (!copy($file, $bak)) fail("cannot backup: $file", $backups);
    $backups[] = ['orig' => $file, 'bak' => $bak];
    echo "📦 backup: " . basename($bak) . "\n";
}

echo "═══════════════════════════════════════════════════\n  Apply SMS Integration\n═══════════════════════════════════════════════════\n\n";

// 1. Create platform-sms.json
$smsJson = $root . '/data/platform-sms.json';
if (!is_file($smsJson)) {
    file_put_contents($smsJson, json_encode([
        'provider' => 'mydnspanel',
        'key' => '',
        'sender' => ''
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ Created: data/platform-sms.json\n";
}

// 2. Rewrite sms.php
echo "\n▶ [1/3] api/lib/sms.php\n";
$smsFile = $root . '/api/lib/sms.php';
backup($smsFile, $ts, $backups);

$newSms = <<<'SMSPHP'
<?php
declare(strict_types=1);
/* ════════════════════════════════════════════════════════════
   ارسال کد تأیید پیامکی — پشتیبانی از mydnspanel و Kavenegar
   ════════════════════════════════════════════════════════════ */

function sms_get_config(): array {
    $cfg = require dirname(__DIR__, 2) . '/config.php';
    $s = $cfg['sms'] ?? [];
    $file = dirname(__DIR__, 2) . '/data/platform-sms.json';
    if (is_file($file)) {
        $j = json_decode((string)file_get_contents($file), true);
        if (is_array($j)) {
            $s = array_merge($s, $j);
        }
    }
    return $s;
}

function sms_send_code(string $phone, string $code): bool {
    $s = sms_get_config();
    if (empty($s['key'])) return false;
    $phone = preg_replace('/\D/', '', $phone);

    if ($s['provider'] === 'mydnspanel') {
        $apiUrl = "mydnspanel.com/webservice/server";
        $apiParam = array(
            'action' => 'sendOTP',
            'type' => 2,
            'otpCode' => $code,
            'appID' => 0,
            'receivers' => $phone
        );
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $s['key']]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParam);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $j = json_decode($res, true);
            return is_array($j) && ($j['result'] ?? 0) === 1;
        }
        return false;
    }

    // Kavenegar fallback
    if ($s['provider'] === 'kavenegar') {
        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
             . '/verify/lookup.json?receptor=' . rawurlencode($phone)
             . '&token=' . rawurlencode($code)
             . '&template=' . rawurlencode($s['template'] ?? '');
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return false;
        $j = json_decode($res, true);
        return is_array($j) && ($j['return']['status'] ?? 500) === 200;
    }
    return false;
}

function sms_send_welcome(string $phone, string $cafeName): bool {
    $s = sms_get_config();
    if (empty($s['key'])) return false;
    $phone = preg_replace('/\D/', '', $phone);

    if ($s['provider'] === 'mydnspanel') {
        $apiUrl = "mydnspanel.com/webservice/server";
        $apiParam = array(
            'action' => 'send',
            'text' => "کافه «{$cafeName}» با موفقیت ثبت شد.",
            'receivers' => $phone
        );
        if (!empty($s['sender'])) {
            $apiParam['from'] = $s['sender'];
        }
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $s['key']]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParam);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $j = json_decode($res, true);
            return is_array($j) && ($j['result'] ?? 0) === 1;
        }
        return false;
    }

    // Kavenegar fallback
    if ($s['provider'] === 'kavenegar') {
        if (empty($s['template_welcome'])) return false;
        $token = mb_substr($cafeName, 0, 20, 'UTF-8');
        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($s['key'])
             . '/verify/lookup.json?receptor=' . rawurlencode($phone)
             . '&token=' . rawurlencode($token)
             . '&template=' . rawurlencode($s['template_welcome']);
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return false;
        $j = json_decode($res, true);
        return is_array($j) && ($j['return']['status'] ?? 500) === 200;
    }
    return false;
}

/* تولید و نگه‌داری کد تأیید (۵ دقیقه اعتبار) */
function sms_issue_code(string $phone): bool {
  if (!sec_file_ok()) return false;
  $code = (string)random_int(10000, 99999);
  $d = sec_load();
  $d['otps'][$phone] = ['code'=>$code, 'exp'=>time()+300, 'tries'=>0];
  sec_store($d);
  return sms_send_code($phone, $code);
}
function sms_verify_code(string $phone, string $code): bool {
  $d = sec_load(); $o = $d['otps'][$phone] ?? null;
  if (!$o || $o['exp'] < time()) return false;
  if ($o['tries'] >= 5) return false;
  $d['otps'][$phone]['tries']++;
  if (!hash_equals($o['code'], $code)) { sec_store($d); return false; }
  unset($d['otps'][$phone]); sec_store($d);
  return true;
}
function sec_file_ok(): bool { return is_writable(dirname(PLAT_FILE)) || !file_exists(PLAT_FILE); }
function sec_load(): array { $j = json_decode((string)@file_get_contents(PLAT_FILE), true); return is_array($j)? $j : []; }
function sec_store(array $d): void { file_put_contents(PLAT_FILE, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX); }
SMSPHP;

if (file_put_contents($smsFile, $newSms) === false) fail("cannot write sms.php", $backups);
echo "  ✅ sms.php rewritten\n";

// 3. Patch panel.php
echo "\n▶ [2/3] panel/panel.php\n";
$panelFile = $root . '/panel/panel.php';
backup($panelFile, $ts, $backups);
$c = file_get_contents($panelFile);

// Add POST case
if (strpos($c, "case 'save_sms':") === false) {
    $anchor = "      case 'suspend':";
    $case = <<<'SAVESMS'
      case 'save_sms':
        $smsFile = __DIR__ . '/../data/platform-sms.json';
        $incoming = $_POST['sms'] ?? [];
        $clean = [
            'provider' => trim((string)($incoming['provider'] ?? 'mydnspanel')),
            'key' => trim((string)($incoming['key'] ?? '')),
            'sender' => trim((string)($incoming['sender'] ?? '')),
        ];
        file_put_contents($smsFile, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $ok = '✅ تنظیمات پیامک ذخیره شد';
        plat_log('save_sms', ['provider' => $clean['provider']]);
        break;
SAVESMS;
    $c = str_replace($anchor, $case . "\n" . $anchor, $c);
    echo "  ✅ save_sms case added\n";
}

// Add View
if (strpos($c, 'id="view-sms"') === false) {
    $anchor = "      <!-- View: Security & Logs -->";
    $view = <<<'VIEWSMS'
      <!-- View: SMS Settings -->
      <section id="view-sms" class="view <?= $currentView === 'sms' ? 'active' : '' ?>">
        <div class="card" style="max-width:600px">
          <div class="card-header"><div class="card-title">📱 تنظیمات پنل پیامکی</div></div>
          <p style="margin-bottom:16px;color:#7D6C54;font-size:13px">
            API Key خود را از پنل (mydnspanel) دریافت و اینجا وارد کنید. تغییرات فوراً اعمال می‌شود.
          </p>
          <?php $__sms = json_decode((string)@file_get_contents(__DIR__.'/../data/platform-sms.json'), true) ?: []; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="act" value="save_sms">
            <label style="display:block;margin-bottom:12px;font-size:13px;font-weight:700">سرویس‌دهنده
              <select name="sms[provider]" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
                <option value="mydnspanel" <?= ($__sms['provider'] ?? '') === 'mydnspanel' ? 'selected' : '' ?>>mydnspanel</option>
                <option value="kavenegar" <?= ($__sms['provider'] ?? '') === 'kavenegar' ? 'selected' : '' ?>>Kavenegar</option>
              </select>
            </label>
            <label style="display:block;margin-bottom:12px;font-size:13px;font-weight:700">کلید API (API Key)
              <input type="text" name="sms[key]" value="<?= htmlspecialchars($__sms['key'] ?? '') ?>" dir="ltr" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
            </label>
            <label style="display:block;margin-bottom:20px;font-size:13px;font-weight:700">شماره فرستنده (اختیاری)
              <input type="text" name="sms[sender]" value="<?= htmlspecialchars($__sms['sender'] ?? '') ?>" dir="ltr" placeholder="اگر خالی باشد، از خط پیش‌فرض پنل استفاده می‌شود" style="width:100%;padding:10px;border:1px solid #DFD2B6;border-radius:8px;margin-top:6px">
            </label>
            <button type="submit" class="btn btn-primary" style="padding:10px 24px">💾 ذخیره تنظیمات</button>
          </form>
        </div>
      </section>

VIEWSMS;
    $c = str_replace($anchor, $view . $anchor, $c);
    echo "  ✅ view-sms section added\n";
}
file_put_contents($panelFile, $c);

// 4. Patch sidebar.php
echo "\n▶ [3/3] panel/sidebar.php\n";
$sideFile = $root . '/panel/sidebar.php';
backup($sideFile, $ts, $backups);
$c = file_get_contents($sideFile);
if (strpos($c, 'view=sms') === false) {
    $anchor = "    <div class=\"nav-divider\"></div>";
    $item = "    <li class=\"nav-item <?= (\$_GET['view'] ?? '') === 'sms' ? 'active' : '' ?>\" onclick=\"window.location.href='panel.php?view=sms'\">\n      <span class=\"icon\">📱</span> تنظیمات پیامک\n    </li>\n\n";
    $c = str_replace($anchor, $item . $anchor, $c);
    file_put_contents($sideFile, $c);
    echo "  ✅ sms nav item added\n";
}

// Syntax check
echo "\n═══════════════════════════════════════════════════\n  Syntax check\n═══════════════════════════════════════════════════\n";
$toCheck = [$smsFile, $panelFile, $sideFile];
$failCount = 0;
foreach ($toCheck as $f) {
    $out = shell_exec('php -l ' . escapeshellarg($f) . ' 2>&1');
    if (strpos((string)$out, 'No syntax errors') === false) {
        echo "  ❌ " . basename($f) . ":\n     $out\n";
        $failCount++;
    } else {
        echo "  ✅ " . basename($f) . "\n";
    }
}
if ($failCount > 0) fail("$failCount file(s) failed syntax check", $backups);

echo "\n═══════════════════════════════════════════════════\n  ✅ DONE\n═══════════════════════════════════════════════════\n";
echo "Backups: *.bak.sms.$ts\n";
echo "Rollback: git restore .\n";