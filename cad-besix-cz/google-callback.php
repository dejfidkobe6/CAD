<?php
/**
 * Google OAuth callback — ověří token, najde uživatele v sdílené users tabulce
 */

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';
define('GOOGLE_CLIENT_ID',     'GOOGLE_CLIENT_ID_PLACEHOLDER');
define('GOOGLE_CLIENT_SECRET', 'GOOGLE_CLIENT_SECRET_PLACEHOLDER');
define('REDIRECT_URI', 'https://cad.besix.cz/google-callback.php');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.besix.cz',
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax',
]);
session_start();

// Uživatel již přihlášen
if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Uživatel zrušil přihlášení
if (isset($_GET['error'])) {
    header('Location: /login.php?error=google_cancelled');
    exit;
}

// CSRF — ověř state
$state = $_GET['state'] ?? '';
if (!$state || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    http_response_code(400);
    die('Neplatný OAuth state. Vraťte se a zkuste znovu.');
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: /login.php?error=google_cancelled');
    exit;
}

// Vyměň code za access token
$tokenData = googlePost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (empty($tokenData['access_token'])) {
    header('Location: /login.php?error=google_token');
    exit;
}

// Získej user info
$userInfo = googleGet('https://www.googleapis.com/oauth2/v3/userinfo', $tokenData['access_token']);

$email = trim(strtolower($userInfo['email'] ?? ''));
if (!$email || empty($userInfo['email_verified'])) {
    header('Location: /login.php?error=google_email');
    exit;
}

// Najdi uživatele v sdílené BeSix DB podle emailu
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare('SELECT id, name, email, avatar_color FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: /login.php?error=google_not_registered');
        exit;
    }

    $_SESSION['user_id']           = (int)$user['id'];
    $_SESSION['user_name']         = $user['name'];
    $_SESSION['user_email']        = $user['email'];
    $_SESSION['user_avatar_color'] = $user['avatar_color'];
    session_write_close();
    header('Location: /');
    exit;

} catch (PDOException $e) {
    header('Location: /login.php?error=db');
    exit;
}

function googlePost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result ?: '{}', true) ?? [];
}

function googleGet(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result ?: '{}', true) ?? [];
}
