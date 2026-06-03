<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once "config/database.php";
$db = (new Database())->getConnection();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Token inválido.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['pass'] ?? '';
        if ($email && $pass) {
            $stmt = $db->prepare("SELECT * FROM ciudadanos WHERE email = :e");
            $stmt->execute([':e' => $email]);
            $ciu = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ciu && password_verify($pass, $ciu['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['ciudadano_id'] = $ciu['id'];
                $_SESSION['ciudadano_email'] = $ciu['email'];
                $_SESSION['ciudadano_nombre'] = $ciu['nombre'];
                header('Location: mis_tramites.php');
                exit;
            }
            $error = 'Email o contraseña incorrectos.';
        } else {
            $error = 'Complete todos los campos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión — Ciudadano</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{ box-sizing:border-box; }
        body{ font-family:'Segoe UI',Arial,sans-serif; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; padding:20px; margin:0; display:flex; align-items:center; justify-content:center; }
        .card{ background:#fff; border-radius:12px; padding:30px; width:420px; max-width:100%; box-shadow:0 10px 40px rgba(0,0,0,.15); }
        h2{ color:#333; margin:0 0 5px; font-size:20px; text-align:center; }
        .sub{ color:#888; font-size:13px; text-align:center; margin-bottom:20px; }
        .form-group{ margin-bottom:14px; }
        .form-group input{ width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; transition:border .2s; }
        .form-group input:focus{ border-color:#667eea; }
        .btn{ width:100%; padding:12px; background:#667eea; color:#fff; border:none; border-radius:8px; font-size:14px; cursor:pointer; transition:background .2s; }
        .btn:hover{ background:#5a6fd6; }
        .err{ padding:10px; background:#f8d7da; border-radius:6px; color:#721c24; font-size:13px; margin-bottom:14px; }
        .exito{ padding:10px; background:#d4edda; border-radius:6px; color:#155724; font-size:13px; margin-bottom:14px; }
        .link{ text-align:center; margin-top:14px; font-size:13px; }
        .link a{ color:#667eea; text-decoration:none; }
        .link a:hover{ text-decoration:underline; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔑 Iniciar Sesión</h2>
    <div class="sub">Acceda a sus trámites registrados</div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="email">
        </div>
        <div class="form-group">
            <input type="password" name="pass" placeholder="Contraseña" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Ingresar</button>
    </form>
    <div class="link">
        ¿No tienes cuenta? <a href="registro_ciudadano.php">Regístrate aquí</a><br>
        <a href="index.php">← Volver al inicio</a>
    </div>
</div>
</body>
</html>
