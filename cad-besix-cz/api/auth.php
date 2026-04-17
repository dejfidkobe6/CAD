<?php
/**
 * BeSix CAD — Auth endpoint
 * Soubor: cad.besix.cz/api/auth.php
 *
 * Autonomní auth pro cad.besix.cz.
 * Sdílí session cookie s board.besix.cz (domain=.besix.cz),
 * ale čte pouze z tabulky users — bez závislosti na board API.
 *
 * Akce:
 *   GET  ?action=me      — vrátí přihlášeného uživatele
 *   POST ?action=logout  — odhlásí uživatele
 */

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

// ── Session (sdílená s board.besix.cz) ──────────────────────────────────────
session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'domain'   => '.besix.cz',
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
session_start();

$action = $_GET['action'] ?? '';

// ── Logout ───────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    $userId = $_SESSION['user_id'] ?? null;
    $_SESSION = [];
    session_destroy();
    // Smaž remember_token cookie + záznam v DB
    if (isset($_COOKIE['besix_remember'])) {
        if ($userId) {
            try {
                $pdo = new PDO(
                    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
                    $DB_USER, $DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
            } catch (PDOException $e) {}
        }
        setcookie('besix_remember', '', ['expires' => time() - 3600, 'path' => '/', 'domain' => '.besix.cz', 'secure' => true, 'httponly' => true]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── Me ───────────────────────────────────────────────────────────────────────
if ($action === 'me') {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
        exit;
    }

    // DB připojení — pouze pro čtení z users
    $DB_HOST = 'localhost';
    $DB_NAME = 'besix_db';
    $DB_USER = 'besix_user';
    $DB_PASS = 'CHANGE_ME';

    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'DB nedostupná']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, email, avatar_color FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Uživatel nenalezen']);
        exit;
    }

    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Neznámá akce']);
