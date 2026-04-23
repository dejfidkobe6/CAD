<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://cad.besix.cz', 'https://board.besix.cz', 'http://localhost'];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

$action = $_GET['action'] ?? '';

// ── Me — čte pouze ze session, žádný DB dotaz ────────────────────────────────
if ($action === 'me') {
    $sessionName = session_name();
    if (empty($_COOKIE[$sessionName])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
        exit;
    }

    ini_set('session.gc_maxlifetime', 604800);
    session_set_cookie_params([
        'lifetime' => 604800, 'path' => '/', 'domain' => '.besix.cz',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start(['read_and_close' => true]);

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
        exit;
    }

    echo json_encode(['success' => true, 'user' => [
        'id'           => (int)$userId,
        'name'         => $_SESSION['user_name']         ?? null,
        'email'        => $_SESSION['user_email']        ?? null,
        'avatar_color' => $_SESSION['user_avatar_color'] ?? null,
    ]]);
    exit;
}

// ── Logout ───────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    ini_set('session.gc_maxlifetime', 604800);
    session_set_cookie_params([
        'lifetime' => 604800, 'path' => '/', 'domain' => '.besix.cz',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    $_SESSION = [];
    session_destroy();

    if (isset($_COOKIE['besix_remember']) && $userId) {
        try {
            $pdo = new PDO(
                "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
                $DB_USER, $DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
        } catch (PDOException $e) {}
        setcookie('besix_remember', '', ['expires' => time() - 3600, 'path' => '/', 'domain' => '.besix.cz', 'secure' => true, 'httponly' => true]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Neznámá akce']);
