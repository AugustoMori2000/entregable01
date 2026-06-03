<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$titulo = 'Exportar Modelo';
require __DIR__ . '/header.php';

if (isset($_GET['descargar'])) {
    $tmp = sys_get_temp_dir() . '/modelo_portable_' . uniqid();
    @mkdir($tmp);

    $files = [
        'preprocesar.py' => __DIR__ . '/../ia/preprocesar.py',
        'modelo_tramites.pkl' => __DIR__ . '/../ia/modelo_tramites.pkl',
    ];

    foreach ($files as $name => $path) {
        if (file_exists($path)) copy($path, "$tmp/$name");
    }

    $portable_predictor = <<<'PY'
import sys, pickle, os
from preprocesar import limpiar

ruta = os.path.dirname(__file__)
with open(os.path.join(ruta, "modelo_tramites.pkl"), "rb") as f:
    modelo = pickle.load(f)

texto = sys.argv[1]
texto_limpio = limpiar(texto)
pred = modelo.predict([texto_limpio])[0]
probas = modelo.predict_proba([texto_limpio])[0]
idx = list(modelo.classes_).index(pred)
conf = round(float(probas[idx]) * 100, 1)

print(f"Area: {pred}")
print(f"Confianza: {conf}%")
PY;
    file_put_contents("$tmp/predecir.py", $portable_predictor);

    file_put_contents("$tmp/predecir.bat", "@echo off\r\npython predecir.py \"%1\"\r\npause\r\n");

    $readme = "=== PREDICTOR PORTABLE - Municipalidad ML ===\r\n\r\n";
    $readme .= "Requisitos: Python 3.7+ con scikit-learn y pandas\r\n\r\n";
    $readme .= "Instalación:\r\n";
    $readme .= "  pip install scikit-learn pandas\r\n\r\n";
    $readme .= "Uso:\r\n";
    $readme .= "  predecir.bat \"Solicitud de reparación de pistas\"\r\n";
    $readme .= "  python predecir.py \"Solicitud de reparación de pistas\"\r\n\r\n";
    $readme .= "Salida: Muestra el área asignada y el % de confianza.\r\n";
    file_put_contents("$tmp/LEEME.txt", $readme);

    $zip_path = sys_get_temp_dir() . '/modelo_portable_' . uniqid() . '.zip';
    shell_exec("powershell -NoProfile -Command \"Compress-Archive -Path '$tmp\\*' -DestinationPath '$zip_path' -Force\" 2>&1");

    if (file_exists($zip_path)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="modelo_portable.zip"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        unlink($zip_path);
        array_map('unlink', glob("$tmp/*"));
        rmdir($tmp);
        exit;
    }

    array_map('unlink', glob("$tmp/*"));
    rmdir($tmp);
    $error_zip = 'No se pudo generar el ZIP.';
}

$modelo_path = __DIR__ . '/../ia/modelo_tramites.pkl';
$info = [];
if (file_exists($modelo_path)) {
    $size = filesize($modelo_path);
    $info['tamaño'] = round($size / 1024, 1) . ' KB';
    $info['modificado'] = date('d/m/Y H:i', filemtime($modelo_path));
    $info['archivos'] = 'predictor.py, preprocesar.py, modelo_tramites.pkl, predecir.bat, LEEME.txt';
}

$stats = [];
if ($db) {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM tramites")->fetchColumn();
    $stats['feedback'] = $db->query("SELECT COUNT(*) FROM tramites WHERE area_destino != ''")->fetchColumn();
    $stats['clases'] = $db->query("SELECT COUNT(*) FROM areas")->fetchColumn();
}
?>
<div class="bar"><h1>Exportar Modelo Portable</h1></div>

<?php if (isset($error_zip)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error_zip) ?></div>
<?php endif; ?>

<div style="display:flex;gap:20px;flex-wrap:wrap;">
<div class="card" style="flex:1;min-width:300px;">
    <h3 style="margin-bottom:15px;">Modelo Actual</h3>
    <table>
        <?php foreach ($info as $k => $v): ?>
        <tr><td style="font-weight:600;width:140px;"><?= ucfirst($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
        <?php endforeach; ?>
        <tr><td style="font-weight:600;">Registros</td><td><?= $stats['total'] ?? '—' ?> trámites</td></tr>
        <tr><td style="font-weight:600;">Áreas</td><td><?= $stats['clases'] ?? '—' ?> clases</td></tr>
    </table>
</div>

<div class="card" style="flex:1;min-width:300px;">
    <h3 style="margin-bottom:15px;">Paquete Portable</h3>
    <p style="color:var(--text2);font-size:14px;margin-bottom:15px;">
        Descargá el modelo entrenado + scripts para usarlo en cualquier PC con Python, sin necesidad de PHP ni MySQL.
    </p>
    <p style="color:var(--text2);font-size:13px;margin-bottom:15px;">
        <strong>Incluye:</strong> predictor.py, preprocesar.py, modelo_tramites.pkl, predecir.bat, LEEME.txt
    </p>
    <a href="?descargar=1" class="btn btn-success" style="padding:12px 30px;font-size:16px;">⬇ Descargar ZIP</a>
</div>
</div>

</div></body></html>
