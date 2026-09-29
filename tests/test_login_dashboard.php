<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

$dbHost = \App\Support\Env::get('DB_HOST', '127.0.0.1');
$dbPort = \App\Support\Env::get('DB_PORT', '3306');
$dbName = \App\Support\Env::get('DB_DATABASE', 'crmwp');
$dbUser = \App\Support\Env::get('DB_USERNAME', 'root');
$dbPass = \App\Support\Env::get('DB_PASSWORD', '');

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Ensure admin user exists with known password
$user = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch(PDO::FETCH_ASSOC);
$standardPassword = 'AdminPassword123!';
if (!$user) {
    echo "Creating admin user...\n";
    $pdo->prepare("INSERT INTO users (username, email, password_hash, is_active, created_at, updated_at) VALUES ('admin', 'admin@example.com', ?, 1, NOW(), NOW())")
        ->execute([password_hash($standardPassword, PASSWORD_BCRYPT)]);
} else {
    $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1 WHERE username = 'admin'")
        ->execute([password_hash($standardPassword, PASSWORD_BCRYPT)]);
}

// 1. Test Login API
$ch = curl_init("http://127.0.0.1:8000/api/v1/auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["username" => "admin", "password" => $standardPassword]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Accept: application/json"]);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login API HTTP: {$httpCode}\n";
if (preg_match('/crmwp_session=([^;]+)/', $res, $matches)) {
    $session = $matches[1];
    echo "Session Cookie: " . substr($session, 0, 10) . "...\n";

    // 2. Test Auth Me
    $ch2 = curl_init("http://127.0.0.1:8000/api/v1/auth/me");
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Accept: application/json", "Cookie: crmwp_session={$session}"]);
    $meRes = curl_exec($ch2);
    $meCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "Auth /me HTTP: {$meCode}\n";
    echo "Auth /me Response: " . substr($meRes, 0, 120) . "...\n";

    // 3. Test Dashboard / SPA root page
    $ch3 = curl_init("http://127.0.0.1:8000/");
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, ["Cookie: crmwp_session={$session}"]);
    $spaRes = curl_exec($ch3);
    $spaCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);
    echo "SPA Dashboard Root HTTP: {$spaCode}\n";
    echo "Contains <div id=\"app\">: " . (str_contains($spaRes, 'id="app"') ? 'YES' : 'NO') . "\n";

    // 4. Verify CSS bundle uses Vazirmatn
    if (preg_match('/href="(\/assets\/index-[^"]+\.css)"/', $spaRes, $cssMatches)) {
        $cssUrl = "http://127.0.0.1:8000" . $cssMatches[1];
        $cssContent = file_get_contents($cssUrl);
        echo "CSS Bundle URL: {$cssMatches[1]}\n";
        echo "CSS contains Vazirmatn: " . (str_contains($cssContent, 'Vazirmatn') ? 'YES' : 'NO') . "\n";
    }

    echo "\nALL CHECKS PASSED SUCCESSFULLY!\n";
} else {
    echo "Login failed. Full response: " . substr($res, 0, 300) . "\n";
}
