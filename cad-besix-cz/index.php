<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

$sessionName = session_name();
$hasCookie = !empty($_COOKIE[$sessionName]);

if ($hasCookie) {
    ini_set('session.gc_maxlifetime', 604800);
    session_set_cookie_params([
        'lifetime' => 604800, 'path' => '/', 'domain' => '.besix.cz',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start(['read_and_close' => true]);
}

$userId        = $_SESSION['user_id']        ?? null;
$userName      = $_SESSION['user_name']      ?? null;
$userEmail     = $_SESSION['user_email']     ?? null;
$userAvatarClr = $_SESSION['user_avatar_color'] ?? null;

// Remember me fallback — DB dotaz jen při absenci session
if (!$userId && isset($_COOKIE['besix_remember'])) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::MYSQL_ATTR_CONNECT_TIMEOUT => 5]);
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.avatar_color
               FROM remember_tokens rt
               JOIN users u ON u.id = rt.user_id
              WHERE rt.token = ? AND rt.expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([$_COOKIE['besix_remember']]);
        $row = $stmt->fetch();
        if ($row) {
            session_start();
            $_SESSION['user_id']        = (int)$row['id'];
            $_SESSION['user_name']      = $row['name'];
            $_SESSION['user_email']     = $row['email'];
            $_SESSION['user_avatar_color'] = $row['avatar_color'];
            session_write_close();
            $userId        = (int)$row['id'];
            $userName      = $row['name'];
            $userEmail     = $row['email'];
            $userAvatarClr = $row['avatar_color'];
        }
    } catch (PDOException $e) {}
}

if (!$userId) {
    header('Location: /login.php');
    exit;
}

// Vloží data uživatele ze session přímo do HTML — bez dalšího DB dotazu
$userData = [
    'id'           => (int)$userId,
    'name'         => $userName,
    'email'        => $userEmail,
    'avatar_color' => $userAvatarClr,
];

$userJson = json_encode($userData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$html = file_get_contents(__DIR__ . '/app.html');
echo str_replace('</head>', '<script>window._USER=' . $userJson . ';</script></head>', $html);
