<?php
/**
 * SSO callback — validuje token z besix.cz a vytvoří lokální session
 */

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';
define('SSO_SECRET', 'SSO_SECRET_PLACEHOLDER');

$userId = (int)($_GET['user_id'] ?? 0);
$ts     = (int)($_GET['ts'] ?? 0);
$token  = $_GET['token'] ?? '';

// Token nesmí být starší než 5 minut
if (!$userId || !$ts || abs(time() - $ts) > 300) {
    http_response_code(400);
    die('Neplatný nebo expirovaný SSO token.');
}

$expected = hash_hmac('sha256', $userId . ':' . $ts, SSO_SECRET);
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    die('Neplatný SSO token.');
}

// Ověř že user existuje v DB
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) { http_response_code(403); die('Uživatel nenalezen.'); }
} catch (PDOException $e) {
    http_response_code(503); die('DB nedostupná.');
}

// Vytvoř lokální session
session_set_cookie_params(['lifetime'=>604800,'path'=>'/','domain'=>'.besix.cz','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$_SESSION['user_id'] = $userId;
session_write_close();

header('Location: /');
exit;
