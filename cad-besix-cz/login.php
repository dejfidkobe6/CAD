<?php
/**
 * BeSix CAD — Login
 * Soubor: cad.besix.cz/login.php
 */

$GOOGLE_CLIENT_ID     = 'GOOGLE_CLIENT_ID_PLACEHOLDER';
$GOOGLE_CLIENT_SECRET = 'GOOGLE_CLIENT_SECRET_PLACEHOLDER';
$REDIRECT_URI         = 'https://cad.besix.cz/login.php';

$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

session_set_cookie_params([
    'lifetime' => 604800,
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

$error = '';

function getDB(string $host, string $name, string $user, string $pass): PDO {
    return new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// ── Email/heslo přihlášení ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $error = 'Vyplňte e-mail a heslo.';
    } else {
        try {
            $pdo  = getDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
            $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Nesprávný e-mail nebo heslo.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                header('Location: /');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'DB nedostupná.';
        }
    }
}

// ── Google OAuth — callback ───────────────────────────────────────────────────
if (isset($_GET['code'])) {
    $tokenRes = httpPost('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($tokenRes['access_token'])) {
        $error = 'Chyba při získání tokenu od Google.';
    } else {
        $profile = httpGet('https://www.googleapis.com/oauth2/v2/userinfo', $tokenRes['access_token']);

        if (empty($profile['email'])) {
            $error = 'Nepodařilo se načíst profil z Google.';
        } else {
            try {
                $pdo    = getDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
                $email  = $profile['email'];
                $name   = $profile['name'] ?? explode('@', $email)[0];
                $colors = ['#4A5340','#5C6BC0','#26A69A','#EF5350','#AB47BC','#FFA726','#42A5F5'];
                $avatar = $colors[array_rand($colors)];

                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $userId = $user['id'];
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, avatar_color, created_at) VALUES (?, ?, ?, NOW())');
                    $stmt->execute([$name, $email, $avatar]);
                    $userId = $pdo->lastInsertId();
                }

                $_SESSION['user_id'] = $userId;
                header('Location: /');
                exit;
            } catch (PDOException $e) {
                $error = 'DB nedostupná.';
            }
        }
    }
}

// ── Google OAuth URL ──────────────────────────────────────────────────────────
$googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $GOOGLE_CLIENT_ID,
    'redirect_uri'  => $REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'prompt'        => 'select_account',
]);

function httpPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true) ?? [];
}
function httpGet(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true) ?? [];
}
?><!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BeSix CAD — Přihlášení</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Montserrat', sans-serif;
      background: #1e2710;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
    }

    .card {
      background: #151d0b;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 48px 40px 40px;
      width: 100%;
      max-width: 420px;
      position: relative;
      box-shadow: 0 24px 64px rgba(0,0,0,0.5);
    }

    .logo-wrap {
      text-align: center;
      margin-bottom: 16px;
    }
    .logo-wrap img {
      width: 130px;
      height: auto;
      filter: brightness(0) invert(1);
    }

    .app-title {
      text-align: center;
      font-size: 32px;
      font-weight: 800;
      letter-spacing: 8px;
      color: rgba(255,255,255,0.95);
      margin-bottom: 32px;
      text-transform: uppercase;
    }

    .cad-badge {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(201,146,42,0.1);
      border: 1px solid rgba(201,146,42,0.25);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 11px;
      font-weight: 600;
      color: #c9922a;
      letter-spacing: 1px;
      margin: 0 auto 28px;
      text-transform: uppercase;
      width: fit-content;
    }
    .cad-badge::before {
      content: '';
      width: 6px; height: 6px;
      border-radius: 50%;
      background: #c9922a;
      flex-shrink: 0;
    }

    .error {
      background: rgba(255, 59, 48, 0.12);
      border: 1px solid rgba(255, 59, 48, 0.3);
      color: #ff6b6b;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 13px;
      margin-bottom: 18px;
      text-align: center;
    }

    /* ── Formulář ── */
    .form-group {
      margin-bottom: 14px;
    }
    .form-group label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      color: rgba(255,255,255,0.45);
      letter-spacing: 0.5px;
      margin-bottom: 6px;
      text-transform: uppercase;
    }
    .form-group input {
      width: 100%;
      padding: 12px 14px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 8px;
      color: rgba(255,255,255,0.88);
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      outline: none;
      transition: border-color 0.15s, background 0.15s;
    }
    .form-group input::placeholder {
      color: rgba(255,255,255,0.2);
    }
    .form-group input:focus {
      border-color: rgba(201,146,42,0.5);
      background: rgba(255,255,255,0.07);
    }

    .btn-primary {
      width: 100%;
      padding: 13px 20px;
      background: #4a5c2a;
      color: rgba(255,255,255,0.92);
      border: none;
      border-radius: 8px;
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 0.5px;
      transition: background 0.15s, transform 0.1s;
      margin-top: 6px;
    }
    .btn-primary:hover { background: #556830; transform: translateY(-1px); }
    .btn-primary:active { transform: translateY(0); }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(255,255,255,0.1);
    }
    .divider span {
      font-size: 11px;
      color: rgba(255,255,255,0.3);
      font-weight: 500;
      letter-spacing: 1px;
    }

    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 13px 20px;
      background: rgba(255,255,255,0.95);
      color: #1a1a1a;
      border: none;
      border-radius: 8px;
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.15s, transform 0.1s;
    }
    .btn-google:hover { background: #fff; transform: translateY(-1px); }
    .btn-google:active { transform: translateY(0); }
    .btn-google svg { width: 18px; height: 18px; flex-shrink: 0; }

    .footer {
      text-align: center;
      margin-top: 28px;
      font-size: 11px;
      color: rgba(255,255,255,0.2);
      letter-spacing: 0.5px;
    }
  </style>
</head>
<body>
  <div class="card">

    <div class="logo-wrap">
      <img src="/besix_logo_highres_transparent.png" alt="BeSix" onerror="this.style.display='none'">
    </div>

    <div class="app-title">CAD</div>

    <div class="cad-badge">Stavební editor</div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Email / heslo -->
    <form method="POST" action="/login.php">
      <div class="form-group">
        <label>E-mail</label>
        <input type="email" name="email" placeholder="vas@email.cz" autocomplete="email" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Heslo</label>
        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary">Přihlásit se</button>
    </form>

    <div class="divider"><span>nebo</span></div>

    <a href="<?= htmlspecialchars($googleUrl) ?>" class="btn-google">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Přihlásit přes Google
    </a>

    <div class="footer">© <?= date('Y') ?> BeSix s.r.o. &nbsp;·&nbsp; cad.besix.cz</div>
  </div>
</body>
</html>
