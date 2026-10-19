<?php
declare(strict_types=1);

// بررسی وجود فایل تنظیمات
$smsConfigFile = __DIR__ . '/data/platform-sms.json';
if (!file_exists($smsConfigFile)) {
    die("❌ فایل platform-sms.json پیدا نشد. اول باید در پنل ادمین ذخیره کنی.\n");
}

$config = json_decode(file_get_contents($smsConfigFile), true);
$apiKey = $config['key'] ?? '';

if (empty($apiKey)) {
    die("❌ API Key در فایل تنظیمات خالی است. اول در پنل ادمین وارد و ذخیره کن.\n");
}

echo "🔑 API Key (۵ کاراکتر اول): " . substr($apiKey, 0, 5) . "...\n";

$testPhone = "09198345561"; // شماره خودت
echo "📱 ارسال به شماره: $testPhone\n\n";

// آدرس دقیق وب‌سرویس
$apiUrl = "https://mydnspanel.com/webservice/server";
$apiParam = array(
    'action' => 'send',
    'text' => "تست پیامک از سیستم GitiArts",
    'receivers' => $testPhone
);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $apiKey]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParam);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای جلوگیری از خطای SSL در لوکال

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ خطای CURL:\n$curlError\n";
    echo "💡 احتمالاً اینترنت یا فایروال اجازه نمی‌دهد، یا دامنه mydnspanel.com در دسترس نیست.\n";
} else {
    echo "✅ پاسخ سرور (HTTP $httpCode):\n";
    echo $response . "\n\n";
    
    $json = json_decode($response, true);
    if (isset($json['result']) && $json['result'] == 1) {
        echo "🎉 پیامک با موفقیت ارسال شد! (به گوشی‌ات نگاه کن)\n";
    } else {
        echo "⚠️ خطا در ارسال. پیام دقیق سرور:\n";
        print_r($json);
    }
}