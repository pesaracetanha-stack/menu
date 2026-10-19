<?php
declare(strict_types=1);
/* ZarinPal Payment Client */

class ZarinPal {
    private string $merchantId;
    private bool $sandbox;

    public function __construct(string $merchantId, bool $sandbox = true) {
        $this->merchantId = $merchantId;
        $this->sandbox = $sandbox;
    }

    private function baseApi(): string {
        return $this->sandbox
            ? 'https://sandbox.zarinpal.com'
            : 'https://api.zarinpal.com';
    }

    private function basePay(): string {
        return $this->sandbox
            ? 'https://sandbox.zarinpal.com'
            : 'https://www.zarinpal.com';
    }

    public function getPaymentUrl(string $authority): string {
        return $this->basePay() . '/pg/StartPay/' . $authority;
    }

    public function request(int $amountRials, string $description, string $callbackUrl, string $mobile = '', string $email = ''): array {
        $body = [
            'merchant_id'  => $this->merchantId,
            'amount'       => $amountRials,
            'callback_url' => $callbackUrl,
            'description'  => mb_substr($description, 0, 255, 'UTF-8'),
        ];

        $meta = [];
        if ($mobile !== '') $meta['mobile'] = $mobile;
        if ($email !== '') $meta['email'] = $email;
        if ($meta) $body['metadata'] = $meta;

        $res = $this->post($this->baseApi() . '/pg/v4/payment/request.json', $body);
        if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];

        $data = $res['json']['data'] ?? null;
        if (!is_array($data)) return ['ok' => false, 'error' => 'پاسخ نامعتبر از زرین‌پال'];

        $code = (int)($data['code'] ?? 0);
        if ($code !== 100) {
            $err = $res['json']['errors']['message'] ?? '';
            return ['ok' => false, 'error' => 'خطا در درخواست پرداخت (کد ' . $code . ($err ? ': ' . $err : '') . ')'];
        }

        return ['ok' => true, 'authority' => (string)($data['authority'] ?? '')];
    }

    public function verify(int $amountRials, string $authority): array {
        $body = [
            'merchant_id' => $this->merchantId,
            'amount'      => $amountRials,
            'authority'   => $authority,
        ];

        $res = $this->post($this->baseApi() . '/pg/v4/payment/verify.json', $body);
        if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];

        $data = $res['json']['data'] ?? null;
        if (!is_array($data)) return ['ok' => false, 'error' => 'پاسخ نامعتبر در تأیید'];

        $code = (int)($data['code'] ?? 0);
        if ($code === 100 || $code === 101) {
            return [
                'ok'      => true,
                'ref_id'  => (string)($data['ref_id'] ?? ''),
                'card_pan'=> (string)($data['card_pan'] ?? ''),
                'already' => ($code === 101),
            ];
        }

        return ['ok' => false, 'error' => 'تأیید ناموفق (کد ' . $code . ')'];
    }

    private function post(string $url, array $body): array {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        // برای محیط لوکال (ویندوز) بررسی SSL غیرفعال می‌شود
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false || $res === '') {
            return ['ok' => false, 'error' => 'ارتباط با زرین‌پال برقرار نشد: ' . $err];
        }
        
        $j = json_decode((string)$res, true);
        if (!is_array($j)) {
            return ['ok' => false, 'error' => 'پاسخ نامعتبر از زرین‌پال'];
        }
        return ['ok' => true, 'json' => $j];
    }
}

/* ─── Helper functions ─── */

function zp_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $f = dirname(__DIR__, 2) . '/config.php';
    $c = is_file($f) ? require $f : [];
    $z = $c['zarinpal'] ?? [];
    $cfg = [
        'merchant_id' => (string)($z['merchant_id'] ?? ''),
        'sandbox'     => !empty($z['sandbox']),
        'enabled'     => !empty($z['enabled']),
    ];
    return $cfg;
}

function zp_client(): ?ZarinPal {
    $c = zp_config();
    if (!$c['enabled']) return null;
    if ($c['merchant_id'] === '' && !$c['sandbox']) return null;
    /* در حالت sandbox با merchant خالی، مقدار فیک می‌گذاریم */
    $mid = $c['merchant_id'];
    if ($mid === '' && $c['sandbox']) {
        $mid = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
    }
    return new ZarinPal($mid, $c['sandbox']);
}

function zp_tx_file(): string {
    return dirname(__DIR__, 2) . '/data/transactions.json';
}

function zp_tx_load(): array {
    $f = zp_tx_file();
    if (!is_file($f)) return ['nextId' => 1, 'items' => []];
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j)) return ['nextId' => 1, 'items' => []];
    if (!isset($j['items'])) $j['items'] = [];
    if (!isset($j['nextId'])) $j['nextId'] = 1;
    return $j;
}

function zp_tx_save(array $d): void {
    $f = zp_tx_file();
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function zp_tx_create(string $slug, int $amountToman, string $plan, int $days): int {
    $d = zp_tx_load();
    $id = (int)$d['nextId'];
    $d['nextId']++;
    $d['items'][] = [
        'id'           => $id,
        'slug'         => $slug,
        'amount_toman' => $amountToman,
        'amount_rial'  => $amountToman * 10,
        'plan'         => $plan,
        'days'         => $days,
        'authority'    => '',
        'ref_id'       => '',
        'card_pan'     => '',
        'status'       => 'pending',
        'created'      => time(),
        'paid_at'      => 0,
    ];
    zp_tx_save($d);
    return $id;
}

function zp_tx_update(int $id, array $patch): void {
    $d = zp_tx_load();
    foreach ($d['items'] as $i => $it) {
        if ((int)$it['id'] !== $id) continue;
        $d['items'][$i] = array_merge($it, $patch);
        zp_tx_save($d);
        return;
    }
}

function zp_tx_find_by_authority(string $authority): ?array {
    $d = zp_tx_load();
    foreach ($d['items'] as $it) {
        if (($it['authority'] ?? '') === $authority) return $it;
    }
    return null;
}