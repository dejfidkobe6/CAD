<?php
// Server-side auth gate — PHP zkontroluje session před odesláním HTML
// Žádný JavaScript cache problém nemůže obejít tuto kontrolu

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

$sessionName = session_name(); // PHPSESSID
$hasCookie = !empty($_COOKIE[$sessionName]);

if ($hasCookie) {
    ini_set('session.gc_maxlifetime', 604800);
    session_set_cookie_params([
        'lifetime' => 604800, 'path' => '/', 'domain' => '.besix.cz',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start(['read_and_close' => true]);
}

$userId = $_SESSION['user_id'] ?? null;

// Remember me fallback
if (!$userId && isset($_COOKIE['besix_remember'])) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $stmt = $pdo->prepare('SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$_COOKIE['besix_remember']]);
        $row = $stmt->fetch();
        if ($row) {
            // Musíme otevřít session pro zápis
            session_start();
            $_SESSION['user_id'] = (int)$row['user_id'];
            session_write_close();
            $userId = (int)$row['user_id'];
        }
    } catch (PDOException $e) {}
}

if (!$userId) {
    header('Location: /login.php');
    exit;
}

// Uživatel je přihlášen — servíruj aplikaci
readfile(__DIR__ . '/app.html');
