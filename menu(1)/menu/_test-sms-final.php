<?php
declare(strict_types=1);

$configFile = __DIR__ . '/data/platform-sms.json';
$config = json_decode(file_get_contents($configFile), true);
$apiKey = trim($config['key'] ?? '');

$testPhone = "09198345561";
// تبدیل به فرمت صحیح: حذف 0 و اضافه کردن 98
$formattedPhone = '98' . substr(ltrim($testPhone, '0'), 1);
if (strlen($testPhone) == 11) $formattedPhone = '98' . substr($testPhone, 1);

$testText = "تست پیامک نهایی";
$apiUrl = "https://mydnspanel.com/webservice/server";

echo "📱 شماره فرمت شده: $formattedPhone\n\n";

// یک شماره فرستنده تستی (باید شماره خط خودتان باشد، این فقط برای تست است)
$senderNumber = "9850004988"; 

$params = [
    'action' => 'send',
    'from' => $senderNumber,
    'text' => $testText,
    'receivers' => $formattedPhone
];

echo "پارامترها: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $apiKey]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode | پاسخ سرور: $response\n\n";

$json = json_decode($response, true);
if (isset($json['result']) && $json['result'] == 1) {
    echo "✅ پیامک با موفقیت ارسال شد! (به گوشی خود نگاه کنید)\n";
} else {
    echo "⚠️ خطا. ";
    if (isset($json['result']) && $json['result'] == 0) {
        echo "احتمالاً شماره فرستنده ($senderNumber) برای حساب شما تعریف نشده است.\n";
        echo "لطفاً شماره خط فرستنده خود را در پنل mydnspanel پیدا کنید یا از پشتیبانی بپرسید.\n";
    } elseif (isset($json['error'])) {
        echo "پیام خطا: " . $json['error'] . "\n";
    }
}