<?php
/**
 * BeSix CAD — SSO Login bridge
 * Soubor: cad.besix.cz/login.php
 *
 * Přihlášení probíhá na besix.cz (sdílená platforma).
 * Po přihlášení je uživatel přesměrován zpět na cad.besix.cz.
 * Session cookie má domain=.besix.cz — sdílená napříč subdoménami.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

// Stejné nastavení session jako na besix.cz
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.besix.cz',
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
session_start();

// ── Již přihlášen (session z besix.cz nebo jiné subdomény) ───────────────────
if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// ── Remember me (pro uživatele kteří se přihlásili dříve přes CAD) ───────────
define('REMEMBER_COOKIE', 'besix_remember');

$rememberToken = $_COOKIE[REMEMBER_COOKIE] ?? null;
if ($rememberToken) {
    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $pdo->prepare('SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$rememberToken]);
        $row = $stmt->fetch();
        if ($row) {
            // Obnov token (rolling)
            $newToken = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE remember_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE token = ?')
                ->execute([$newToken, $rememberToken]);
            setcookie(REMEMBER_COOKIE, $newToken, [
                'expires' => time() + 30 * 86400, 'path' => '/',
                'domain' => '.besix.cz', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
            ]);
            $_SESSION['user_id'] = (int)$row['user_id'];
            header('Location: /');
            exit;
        } else {
            // Expirovaný token — smaž cookie
            setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'domain' => '.besix.cz']);
        }
    } catch (PDOException $e) { /* tiché selhání, přesměruj na login */ }
}

// ── Přesměrovat na platformu besix.cz ────────────────────────────────────────
$redirectBack = 'https://cad.besix.cz/';
header('Location: https://besix.cz/login.php?redirect=' . urlencode($redirectBack));
exit;
