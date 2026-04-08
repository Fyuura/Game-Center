<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// 1. Veritabanı bağlantı bilgileri
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'gamecenter';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// 2. DSN (Data Source Name) oluşturma
// Bu dize, PDO'ya hangi veritabanı sürücüsünü kullanacağını ve nasıl bağlanacağını söyler.
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// 3. PDO Bağlantı Seçenekleri
// Bu seçenekler, PDO'nun davranışını ayarlar.
$options = [
    // Hata modunu istisna (exception) olarak ayarla. Bu, hataları yakalamamızı sağlar.
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // Sonuçları her zaman ilişkisel bir dizi (associative array) olarak al.
    // Örn: $row['ad'] şeklinde erişim sağlar, $row[0] yerine.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Hazırlanan sorguların (prepared statements) emülasyonunu kapat.
    // Bu, daha güvenli sorgular için veritabanının kendi mekanizmasını kullanmasını sağlar.
    PDO::ATTR_EMULATE_PREPARES => false,
];

// 4. PDO Bağlantısını Kurma
try {
    // Yukarıdaki bilgilerle yeni bir PDO nesnesi oluşturarak bağlantıyı başlat.
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Bağlantının başarılı olduğunu test etmek için bu satırı kullanabilirsiniz.
    // echo "Veritabanı bağlantısı başarıyla kuruldu.";

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Bu noktadan sonra '$pdo' değişkenini projenizin diğer kısımlarında
// veritabanı işlemleri yapmak için kullanabilirsiniz.
?>

<!-- CLOUD_NAME=dsxbmecwu
API_KEY=124299386993448
API_SECRET=xstCJSlvDbWEhNi4NwmafgbiwCw -->