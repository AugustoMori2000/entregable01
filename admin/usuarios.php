<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$db = (new Database())->getConnection();

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    header('Location: login.php');
    exit;
}

$titulo = 'Usuarios Admin';
require_once __DIR__ . '/header.php';

$mensaje = '';

// Crear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    csrf_verify();
    if ($_POST['accion'] === 'crear') {
        $username = trim($_POST['username'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $pass = $_POST['pass'] ?? '';
        if (!$username || !$pass) {
            $mensaje = '<div class="alert alert-danger">Usuario y contraseña obligatorios.</div>';
        } elseif (strlen($pass) < 6) {
            $mensaje = '<div class="alert alert-danger">La contraseña debe tener al menos 6 caracteres.</div>';
        } else {
            try {
                admin_crear($username, $pass, $nombre);
                $mensaje = '<div class="alert alert-success">Usuario creado.</div>';
            } catch (Exception $e) {
                $mensaje = '<div class="alert alert-danger">El usuario ya existe.</div>';
            }
        }
    }
    // Editar
    if ($_POST['accion'] === 'editar') {
        $id = (int)$_POST['id'];
        $username = trim($_POST['username'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $pass = trim($_POST['pass'] ?? '');
        if (!$username) {
            $mensaje = '<div class="alert alert-danger">Usuario obligatorio.</div>';
        } else {
            admin_actualizar($id, $username, $nombre, $pass ?: null);
            $mensaje = '<div class="alert alert-success">Usuario actualizado.</div>';
        }
    }
    // Eliminar
    if ($_POST['accion'] === 'eliminar') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['admin_id']) {
            $mensaje = '<div class="alert alert-danger">No puedes eliminarte a ti mismo.</div>';
        } else {
            admin_eliminar($id);
            $mensaje = '<div class="alert alert-success">Usuario eliminado.</div>';
        }
    }
}

$usuarios = admin_obtener_todos();
?>
<h1>Usuarios Administradores</h1>
<?= $mensaje ?>

<div class="card">
    <h3>Nuevo usuario</h3>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="username" placeholder="Usuario" required>
        <input type="text" name="nombre" placeholder="Nombre completo">
        <input type="password" name="pass" placeholder="Contraseña" required minlength="6">
        <button type="submit" class="btn btn-success">Crear</button>
    </form>
</div>

<table>
    <thead>
        <tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Creado</th><th>Acciones</th></tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['nombre'] ?: '—') ?></td>
            <td><?= $u['created_at'] ?></td>
            <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este usuario?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" <?= $u['id'] === (int)$_SESSION['admin_id'] ? 'disabled title="No puedes eliminarte"' : '' ?>>Eliminar</button>
                </form>
                <button class="btn btn-primary btn-sm" onclick="editar(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['nombre'] ?: '', ENT_QUOTES) ?>')">Editar</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="modal-editar" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:999;">
    <div style="background:var(--card);padding:25px;border-radius:8px;width:400px;max-width:90%;">
        <h3 style="margin-bottom:15px;">Editar usuario</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit-id">
            <div style="margin-bottom:10px;">
                <input type="text" name="username" id="edit-username" placeholder="Usuario" required style="width:100%;">
            </div>
            <div style="margin-bottom:10px;">
                <input type="text" name="nombre" id="edit-nombre" placeholder="Nombre completo" style="width:100%;">
            </div>
            <div style="margin-bottom:10px;">
                <input type="password" name="pass" placeholder="Nueva contraseña (dejar vacío para mantener)" style="width:100%;">
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('modal-editar').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editar(id, username, nombre) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-nombre').value = nombre;
    document.getElementById('modal-editar').style.display = 'flex';
}
</script>

</div>
</div>
</body>
</html>
