<?php
/**
 * BeSix CAD — Login
 * Sdílí users tabulku s besix.cz (stejná DB), vlastní session na cad.besix.cz
 */

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.besix.cz',
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

define('REMEMBER_COOKIE', 'besix_remember');

// ── Remember me ─────────────────────────────────────────────────────────────
function tryRememberToken($pdo) {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? null;
    if (!$token) return false;
    $stmt = $pdo->prepare('SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'domain' => '.besix.cz']);
        return false;
    }
    $newToken = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE remember_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE token = ?')
        ->execute([$newToken, $token]);
    setcookie(REMEMBER_COOKIE, $newToken, [
        'expires' => time() + 30 * 86400, 'path' => '/',
        'domain' => '.besix.cz', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_SESSION['user_id'] = (int)$row['user_id'];
    return true;
}

$error = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Zkus remember token
    if (tryRememberToken($pdo)) {
        header('Location: /');
        exit;
    }

    // ── POST — přihlášení ────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            $error = 'Vyplňte email a heslo.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, avatar_color FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = (int)$user['id'];

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))')
                        ->execute([$user['id'], $token]);
                    setcookie(REMEMBER_COOKIE, $token, [
                        'expires' => time() + 30 * 86400, 'path' => '/',
                        'domain' => '.besix.cz', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
                    ]);
                }

                header('Location: /');
                exit;
            } else {
                $error = 'Nesprávný email nebo heslo.';
            }
        }
    }
} catch (PDOException $e) {
    $error = 'Databáze je dočasně nedostupná.';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BeSix CAD — Přihlášení</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0d1117;
    --surface: #161c24;
    --surface2: #1e2730;
    --border: #2a3a2a;
    --accent: #c8a84b;
    --accent2: #4A7C59;
    --text: #e8e8e8;
    --text2: #8a9a8a;
    --danger: #e05c5c;
    --green: #4A7C59;
  }
  body {
    font-family: 'Montserrat', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .login-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px 36px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
  }
  .logo-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-bottom: 32px;
  }
  .logo-wrap img {
    height: 52px;
    filter: brightness(1.1);
  }
  .logo-wrap span {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 4px;
    color: #fff;
  }
  label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    color: var(--text2);
    text-transform: uppercase;
    margin-bottom: 6px;
  }
  input[type=email], input[type=password] {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: inherit;
    font-size: 14px;
    padding: 10px 12px;
    outline: none;
    transition: border-color .15s;
    margin-bottom: 16px;
  }
  input:focus { border-color: var(--accent); }
  .remember-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
    font-size: 13px;
    color: var(--text2);
    cursor: pointer;
  }
  .remember-row input { width: auto; margin: 0; }
  .btn-primary {
    width: 100%;
    background: var(--accent);
    color: #1a1000;
    border: none;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 12px;
    cursor: pointer;
    transition: opacity .15s;
    text-transform: uppercase;
  }
  .btn-primary:hover { opacity: .85; }
  .divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
    color: var(--text2);
    font-size: 11px;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }
  .btn-sso {
    width: 100%;
    background: transparent;
    color: var(--text2);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-family: inherit;
    font-size: 13px;
    padding: 10px;
    cursor: pointer;
    transition: border-color .15s, color .15s;
    text-decoration: none;
    display: block;
    text-align: center;
  }
  .btn-sso:hover { border-color: var(--accent2); color: var(--text); }
  .error {
    background: rgba(224,92,92,.12);
    border: 1px solid rgba(224,92,92,.3);
    border-radius: 6px;
    color: var(--danger);
    font-size: 13px;
    padding: 10px 12px;
    margin-bottom: 16px;
  }
  .form-group { margin-bottom: 0; }
</style>
</head>
<body>
<div class="login-box">
  <div class="logo-wrap">
    <img src="/besix_logo_highres_transparent.png" alt="BeSix">
    <span>CAD</span>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" required autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="password">Heslo</label>
      <input type="password" id="password" name="password" required>
    </div>
    <label class="remember-row">
      <input type="checkbox" name="remember" <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
      Zapamatovat na 30 dní
    </label>
    <button type="submit" class="btn-primary">Přihlásit se</button>
  </form>

  <div class="divider">nebo</div>

  <a class="btn-sso" href="https://besix.cz/login.php?redirect=<?= urlencode('https://cad.besix.cz/') ?>">
    Přihlásit přes besix.cz platformu
  </a>
</div>
</body>
</html>
