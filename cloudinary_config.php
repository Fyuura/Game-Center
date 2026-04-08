<?php
// Composer'ın oluşturduğu autoload dosyasını dahil ediyoruz.
// Bu, Cloudinary sınıflarını otomatik olarak yükler.
require_once __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Güvenlik için API bilgilerinizi doğrudan koda yazmak yerine
// .env dosyası gibi ortamsal değişkenlerde saklamanız şiddetle tavsiye edilir.
// Bu şekilde, gizli anahtarlarınız versiyon kontrolüne dahil olmaz.

$cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? null;
$api_key = $_ENV['CLOUDINARY_API_KEY'] ?? null;
$api_secret = $_ENV['CLOUDINARY_API_SECRET'] ?? null;

if (!$cloud_name || !$api_key || !$api_secret) {
    throw new RuntimeException('Cloudinary environment variables are not set.');
}

// Cloudinary'ye yapılandırma bilgilerini sağlıyoruz.
// Bu, API ile etkileşim kuracak olan nesneyi ayarlar.
$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => $cloud_name,
        'api_key'    => $api_key,
        'api_secret' => $api_secret
    ],
    'url' => [
        'secure' => true // Her zaman HTTPS linkleri kullanmak için
    ]
]);

?>