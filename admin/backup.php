<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }

$titulo = 'Respaldo';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
require __DIR__ . '/header.php';

$mensaje = '';
$archivos = [];

if (isset($_GET['crear'])) {
    $backup_dir = __DIR__ . '/../backups';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);

    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = "$backup_dir/$filename";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- Backup generado el " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $columns = array_keys($rows[0]);
            $col_list = '`' . implode('`,`', $columns) . '`';
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return "'" . str_replace("'", "\\'", $v) . "'";
                }, array_values($row));
                $sql .= "INSERT INTO `$table` ($col_list) VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
    }

    file_put_contents($filepath, $sql);
    $mensaje = '<div class="alert alert-success">Respaldo creado: <strong>' . htmlspecialchars($filename) . '</strong></div>';
}

// Listar backups
$backup_dir = __DIR__ . '/../backups';
if (is_dir($backup_dir)) {
    $archivos = array_diff(scandir($backup_dir), ['.', '..']);
    $archivos = array_reverse($archivos);
}

// Eliminar backup
if (isset($_GET['eliminar']) && $_GET['eliminar']) {
    $file = basename($_GET['eliminar']);
    $path = __DIR__ . '/../backups/' . $file;
    if (file_exists($path)) {
        unlink($path);
        $mensaje = '<div class="alert alert-success">Backup eliminado.</div>';
    }
    header('Location: backup.php');
    exit;
}
?>
<h1>💾 Respaldo de Base de Datos</h1>
<?= $mensaje ?>

<div class="card">
    <a href="?crear=1" class="btn btn-success">📥 Crear nuevo respaldo</a>
    <p style="margin-top:10px;font-size:13px;color:#666;">Genera un archivo SQL con la estructura y datos de todas las tablas.</p>
</div>

<?php if ($archivos): ?>
<div class="card" style="padding:0;overflow-x:auto;">
<table>
    <thead><tr><th>Archivo</th><th>Tamaño</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($archivos as $a): $path = __DIR__ . '/../backups/' . $a; ?>
        <tr>
            <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($a) ?></td>
            <td><?= round(filesize($path) / 1024, 1) ?> KB</td>
            <td>
                <a href="../backups/<?= urlencode($a) ?>" class="btn btn-primary btn-sm" download>Descargar</a>
                <a href="?eliminar=<?= urlencode($a) ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este backup?')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<div style="text-align:center;padding:30px;color:#999;">No hay respaldos aún.</div>
<?php endif; ?>
</div></body></html>
