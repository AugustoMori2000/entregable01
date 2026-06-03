<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$titulo = 'Carga Masiva';
require __DIR__ . '/header.php';

$python = "C:\\Users\\AUGUSTO\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe";
$script = __DIR__ . "/../ia/predictor.py";
$resultados = [];
$procesados = 0;

if ($_FILES && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    csrf_verify();
    $tmp = $_FILES['csv']['tmp_name'];
    $fh = fopen($tmp, 'r');
    $headers = fgetcsv($fh);
    $col_asunto = 0;

    putenv('PYTHONIOENCODING=utf-8');

    while (($linea = fgetcsv($fh)) !== false) {
        $asunto = trim($linea[$col_asunto] ?? '');
        if (!$asunto) continue;

        $comando = "\"$python\" \"$script\" " . escapeshellarg($asunto) . " 2>&1";
        $salida = shell_exec($comando);

        if ($salida) {
            $datos = json_decode(base64_decode(trim($salida)), true);
            if ($datos) {
                $resultados[] = [
                    'asunto' => $asunto,
                    'area' => $datos['area'],
                    'confianza' => $datos['confianza']
                ];

                if ($db) {
                    $stmt = $db->prepare("INSERT INTO tramites (asunto, area_predicha, creado_por) VALUES (:a, :ar, 'sistema')");
                    $stmt->execute([':a' => $asunto, ':ar' => $datos['area']]);
                }
                $procesados++;
            }
        }
    }
    fclose($fh);
}
?>
<div class="bar"><h1>Carga Masiva de Trámites</h1></div>

<div class="card">
    <h3 style="margin-bottom:15px;">Subir archivo CSV</h3>
    <p style="margin-bottom:15px;color:#666;">El CSV debe tener una columna <strong>asunto</strong> con los textos a predecir.</p>
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="file" name="csv" accept=".csv" required style="margin-bottom:15px;">
        <button type="submit" class="btn btn-primary">Procesar</button>
    </form>
</div>

<?php if ($resultados): ?>
<div class="card" style="padding:0;overflow-x:auto;">
    <h3 style="padding:15px;margin:0;">Resultados (<?= $procesados ?> procesados)</h3>
    <table>
        <thead><tr><th>#</th><th>Asunto</th><th>Área</th><th>Confianza</th></tr></thead>
        <tbody>
            <?php foreach ($resultados as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($r['asunto']) ?></td>
                <td><?= htmlspecialchars($r['area']) ?></td>
                <td><?= $r['confianza'] ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
</div></body></html>
