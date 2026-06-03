<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: admin/login.php'); exit; }
header('Content-Type: text/html; charset=UTF-8');

require_once "config/database.php";

$db = new Database();
$conn = $db->getConnection();

$output = "";
$success = false;

if($conn){
    $stmt = $conn->query("SELECT COUNT(*) FROM tramites WHERE area_destino IS NOT NULL AND area_destino != ''");
    $total_feedback = $stmt->fetchColumn();

    if($total_feedback < 1){
        $output = "No hay registros con feedback para reentrenar.";
    } else {
        $sintetico = __DIR__ . "/ia/tramites.csv";
        $csv_path = __DIR__ . "/ia/datos_combinados.csv";

        file_put_contents($csv_path, "\xEF\xBB\xBFasunto,area_destino\n");

        $lineas = file($sintetico);
        array_shift($lineas);
        foreach($lineas as $linea){
            file_put_contents($csv_path, trim($linea) . "\n", FILE_APPEND);
        }

        $stmt = $conn->query("SELECT asunto, area_destino FROM tramites WHERE area_destino IS NOT NULL AND area_destino != '' ORDER BY id");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $asunto = str_replace('"', '""', $row['asunto']);
            $area = str_replace('"', '""', $row['area_destino']);
            file_put_contents($csv_path, "\"$asunto\",\"$area\"\n", FILE_APPEND);
        }

        $python = "C:\\Users\\AUGUSTO\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe";
        $script = __DIR__ . "/ia/entrenar_modelo.py";
        $export_script = __DIR__ . "/ia/exportar_modelo.py";
        putenv('PYTHONIOENCODING=utf-8');
        $comando = "\"$python\" \"$script\" \"$csv_path\" 2>&1";
        shell_exec($comando);
        $comando_export = "\"$python\" \"$export_script\" 2>&1";
        shell_exec($comando_export);

        $modelo_path = __DIR__ . "/ia/modelo_export.json";
        if(file_exists($modelo_path)){
            $output = "Reentrenamiento completado exitosamente con $total_feedback registros reales + sintéticos.";
            $success = true;
        } else {
            $output = "Error durante el reentrenamiento.";
        }
    }
} else {
    $output = "Error de conexión a la base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reentrenar Modelo</title>
    <style>
        *{ box-sizing:border-box; }
        body{ font-family:Arial; background:#f4f4f4; padding:40px; }
        .container{ background:white; width:600px; margin:auto; padding:30px; border-radius:10px; text-align:center; }
        .success{ background:#d4edda; padding:20px; border-radius:5px; font-size:18px; }
        .error{ background:#f8d7da; padding:20px; border-radius:5px; color:#721c24; font-size:18px; }
        a{ display:inline-block; margin-top:20px; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px; }
        a:hover{ opacity:0.9; }
    </style>
</head>
<body>
<div class="container">
    <h2>Reentrenar Modelo</h2>
    <?php if($success): ?>
    <div class="success"><?= htmlspecialchars($output) ?></div>
    <?php else: ?>
    <div class="error"><?= htmlspecialchars($output) ?></div>
    <?php endif; ?>
    <a href="index.php">&larr; Volver al inicio</a>
</div>
</body>
</html>
