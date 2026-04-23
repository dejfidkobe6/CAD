<?php
/**
 * BeSix CAD — Login
 * Sdílí users tabulku s besix.cz (stejná DB), vlastní session na cad.besix.cz
 */

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';
define('GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_ID_PLACEHOLDER');

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

// ── Google OAuth state ────────────────────────────────────────────────────────
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => 'https://cad.besix.cz/google-callback.php',
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['oauth_state'],
    'prompt'        => 'select_account',
]);

$error = '';

// Chybové hlášky z Google OAuth callbacku
$error = match($_GET['error'] ?? '') {
    'google_not_registered' => 'Váš Google účet není registrován v BeSix systému. Přihlaste se emailem a heslem.',
    'google_cancelled'      => 'Přihlášení přes Google bylo zrušeno.',
    'google_token', 'google_email' => 'Chyba při ověření Google účtu, zkuste to znovu.',
    'db'                    => 'Databáze je dočasně nedostupná.',
    default                 => '',
};

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
    --text: #e8e8e8;
    --text2: #8a9a8a;
    --danger: #e05c5c;
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
  .logo-wrap img { height: 52px; filter: brightness(1.1); }
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
  .btn-google {
    width: 100%;
    background: #fff;
    color: #3c4043;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    padding: 11px 16px;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: box-shadow .15s, background .15s;
  }
  .btn-google:hover { background: #f8f9fa; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
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
  <p style="text-align:center;color:var(--text2);font-size:13px;margin-bottom:20px;">Přihlaste se svým BeSix účtem</p>

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

  <a class="btn-google" href="<?= htmlspecialchars($googleAuthUrl) ?>">
    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
      <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
      <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
      <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
      <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 6.29C4.672 4.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
    </svg>
    Přihlásit přes Google
  </a>
</div>
</body>
</html>
