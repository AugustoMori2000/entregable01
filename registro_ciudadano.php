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
        $nombre = trim($_POST['nombre'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $pass = $_POST['pass'] ?? '';
        $pass2 = $_POST['pass2'] ?? '';

        if (!$nombre || !$email || !$pass) {
            $error = 'Complete nombre, email y contraseña.';
        } elseif ($pass !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (strlen($pass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($dni && !preg_match('/^\d{8}$/', $dni)) {
            $error = 'DNI inválido (8 dígitos).';
        } else {
            $check = $db->prepare("SELECT COUNT(*) FROM ciudadanos WHERE email = :e");
            $check->execute([':e' => $email]);
            if ($check->fetchColumn() > 0) {
                $error = 'Ya existe una cuenta con ese email.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO ciudadanos (email, password_hash, nombre, dni, telefono) VALUES (:e, :p, :n, :d, :t)");
                $stmt->execute([':e' => $email, ':p' => $hash, ':n' => $nombre, ':d' => $dni ?: null, ':t' => $telefono ?: null]);
                $exito = 'Cuenta creada exitosamente. <a href="login_ciudadano.php" style="color:#155724;">Inicia sesión aquí</a>.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrarse — Ciudadano</title>
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
        .row2{ display:flex; gap:10px; }
        .row2 .form-group{ flex:1; }
        .hint{ font-size:11px; color:#999; margin-top:4px; }
    </style>
</head>
<body>
<div class="card">
    <h2>📝 Registrarse</h2>
    <div class="sub">Cree una cuenta para dar seguimiento a sus trámites</div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="exito"><?= $exito ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <input type="text" name="nombre" placeholder="Nombre completo" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
        </div>
        <div class="row2">
            <div class="form-group">
                <input type="text" name="dni" placeholder="DNI (opcional)" value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>" maxlength="8">
            </div>
            <div class="form-group">
                <input type="text" name="telefono" placeholder="Teléfono (opcional)" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <input type="email" name="email" placeholder="Correo electrónico" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
        </div>
        <div class="form-group">
            <input type="password" name="pass" placeholder="Contraseña (mín. 6 caracteres)" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-group">
            <input type="password" name="pass2" placeholder="Repetir contraseña" required minlength="6" autocomplete="new-password">
        </div>
        <button type="submit" class="btn">Crear cuenta</button>
    </form>
    <div class="link">
        ¿Ya tienes cuenta? <a href="login_ciudadano.php">Inicia sesión</a><br>
        <a href="index.php">← Volver al inicio</a>
    </div>
</div>
</body>
</html>
