<?php
/**
 * BeSix CAD — Google OAuth login
 * Soubor: cad.besix.cz/login.php
 *
 * Vlastní přihlášení přes Google pro cad.besix.cz.
 * Sdílí tabulku users s board.besix.cz — účty jsou společné.
 */

// ── Konfigurace ──────────────────────────────────────────────────────────────
$GOOGLE_CLIENT_ID     = 'GOOGLE_CLIENT_ID_PLACEHOLDER';
$GOOGLE_CLIENT_SECRET = 'GOOGLE_CLIENT_SECRET_PLACEHOLDER';
$REDIRECT_URI         = 'https://cad.besix.cz/login.php';

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

// ── Session ───────────────────────────────────────────────────────────────────
session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'domain'   => '.besix.cz',
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
session_start();

// Už přihlášen → rovnou na app
if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// ── Krok 2: Google vrátil code ────────────────────────────────────────────────
if (isset($_GET['code'])) {
    // Vyměnit code za access token
    $tokenRes = httpPost('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($tokenRes['access_token'])) {
        die('Chyba při získání tokenu od Google.');
    }

    // Načíst profil uživatele
    $profile = httpGet(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        $tokenRes['access_token']
    );

    if (empty($profile['email'])) {
        die('Nepodařilo se načíst profil z Google.');
    }

    // ── DB ────────────────────────────────────────────────────────────────────
    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        die('DB nedostupná.');
    }

    $email  = $profile['email'];
    $name   = $profile['name'] ?? explode('@', $email)[0];
    $avatar = randomColor();

    // Najít nebo vytvořit uživatele (sdílená tabulka users)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = $user['id'];
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, avatar_color, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$name, $email, $avatar]);
        $userId = $pdo->lastInsertId();
    }

    $_SESSION['user_id'] = $userId;
    header('Location: /');
    exit;
}

// ── Krok 1: Přesměrovat na Google ────────────────────────────────────────────
$params = http_build_query([
    'client_id'     => $GOOGLE_CLIENT_ID,
    'redirect_uri'  => $REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;

// ── Helpers ───────────────────────────────────────────────────────────────────
function httpPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function httpGet(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function randomColor(): string {
    $colors = ['#4A5340', '#5C6BC0', '#26A69A', '#EF5350', '#AB47BC', '#FFA726', '#42A5F5'];
    return $colors[array_rand($colors)];
}

