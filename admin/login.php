<?php
session_set_cookie_params(0);
session_start();
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('CSRF token inválido.');
    }
    $user_data = admin_autenticar($_POST['user'], $_POST['pass']);
    if ($user_data) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['admin_user'] = $user_data['username'];
        $_SESSION['admin_nombre'] = $user_data['nombre'];
        $_SESSION['admin_id'] = $user_data['id'];
        $_SESSION['admin_ultimo_acceso'] = time();
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Credenciales incorrectas';
}
$expiro = isset($_GET['expiro']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login — Municipalidad ML</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Segoe UI',system-ui,-apple-system,sans-serif; min-height:100vh; display:flex; background:linear-gradient(135deg,#0f0c29,#302b63,#24243e); align-items:center; justify-content:center; padding:20px; }
        .bg-shape{ position:fixed; width:500px; height:500px; border-radius:50%; background:rgba(102,126,234,.12); filter:blur(80px); pointer-events:none; }
        .bg-shape:nth-child(1){ top:-150px; right:-100px; }
        .bg-shape:nth-child(2){ bottom:-150px; left:-100px; background:rgba(118,75,162,.12); }
        .login-card{ position:relative; background:rgba(255,255,255,.06); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.1); border-radius:24px; padding:48px 40px; width:400px; max-width:100%; box-shadow:0 25px 60px rgba(0,0,0,.4); animation:fadeUp .5s ease; }
        @keyframes fadeUp{ from{ opacity:0; transform:translateY(24px); } to{ opacity:1; transform:translateY(0); } }
        .logo{ text-align:center; margin-bottom:32px; }
        .logo .icon{ width:64px; height:64px; background:linear-gradient(135deg,#667eea,#764ba2); border-radius:18px; display:inline-flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:12px; box-shadow:0 8px 24px rgba(102,126,234,.3); }
        .logo h1{ color:#fff; font-size:20px; font-weight:600; letter-spacing:-.3px; }
        .logo p{ color:rgba(255,255,255,.5); font-size:13px; margin-top:4px; }
        .input-group{ position:relative; margin-bottom:16px; }
        .input-group input{ width:100%; padding:14px 16px 14px 44px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); border-radius:12px; font-size:14px; color:#fff; outline:none; transition:all .25s; }
        .input-group input::placeholder{ color:rgba(255,255,255,.35); }
        .input-group input:focus{ border-color:#667eea; background:rgba(255,255,255,.1); box-shadow:0 0 0 3px rgba(102,126,234,.15); }
        .input-group .ico{ position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:16px; opacity:.4; pointer-events:none; }
        .btn{ width:100%; padding:14px; background:linear-gradient(135deg,#667eea,#764ba2); border:none; border-radius:12px; color:#fff; font-size:15px; font-weight:600; cursor:pointer; transition:all .25s; box-shadow:0 4px 16px rgba(102,126,234,.3); margin-top:8px; }
        .btn:hover{ transform:translateY(-1px); box-shadow:0 8px 28px rgba(102,126,234,.4); }
        .btn:active{ transform:translateY(0); }
        .error-msg{ text-align:center; padding:10px 14px; background:rgba(220,53,69,.12); border:1px solid rgba(220,53,69,.25); border-radius:10px; color:#ff6b7a; font-size:13px; margin-bottom:16px; animation:shake .3s; }
        @keyframes shake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-4px); } 75%{ transform:translateX(4px); } }
    </style>
</head>
<body>
    <div class="bg-shape"></div>
    <div class="bg-shape"></div>
    <div class="login-card">
        <div class="logo">
            <div class="icon">🏛️</div>
            <h1>Acceso Administrativo</h1>
            <p>Ingrese sus credenciales</p>
        </div>
        <?php if (isset($error)): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>
        <?php if ($expiro): ?><div class="error-msg">Sesión expirada. Ingresa de nuevo.</div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="input-group">
                <span class="ico">👤</span>
                <input type="text" name="user" placeholder="Usuario" required autocomplete="username">
            </div>
            <div class="input-group">
                <span class="ico">🔒</span>
                <input type="password" name="pass" placeholder="Contraseña" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>
