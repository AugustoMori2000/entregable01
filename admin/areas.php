<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$titulo = 'Áreas';
require __DIR__ . '/header.php';
$mensaje = '';

// CRUD
if ($_POST) {
    csrf_verify();
    try {
        if ($_POST['accion'] === 'crear') {
            $stmt = $db->prepare("INSERT INTO areas (nombre) VALUES (:n)");
            $stmt->execute([':n' => trim($_POST['nombre'])]);
            $mensaje = 'Área creada';
        } elseif ($_POST['accion'] === 'editar') {
            $stmt = $db->prepare("UPDATE areas SET nombre = :n WHERE id = :id");
            $stmt->execute([':n' => trim($_POST['nombre']), ':id' => $_POST['id']]);
            $mensaje = 'Área actualizada';
        } elseif ($_POST['accion'] === 'eliminar') {
            $db->prepare("DELETE FROM areas WHERE id = :id")->execute([':id' => $_POST['id']]);
            $mensaje = 'Área eliminada';
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
    }
}
$areas = $db->query("SELECT * FROM areas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bar"><h1>Áreas Municipales</h1></div>
<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

<div class="card">
    <form method="POST" style="display:flex;gap:10px;margin-bottom:15px;">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" placeholder="Nueva área" required style="flex:1;">
        <button type="submit" class="btn btn-success">Agregar</button>
    </form>
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Creado</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($areas as $a): ?>
            <tr>
                <td><?= $a['id'] ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:5px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <input type="text" name="nombre" value="<?= htmlspecialchars($a['nombre']) ?>" required>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </form>
                </td>
                <td><?= $a['created_at'] ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('¿Eliminar?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div></body></html>
