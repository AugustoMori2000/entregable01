<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once "config/database.php";
$db = (new Database())->getConnection();

function notificar_ciudadano($email, $codigo, $asunto, $area, $formato = '') {
    if (!$email) return;
    $asunto_mail = "Código de seguimiento - Municipalidad";
    $cuerpo = "Estimado ciudadano,\n\nSu trámite ha sido registrado:\n\n";
    $cuerpo .= "Código: $codigo\nAsunto: $asunto\n";
    if ($formato) $cuerpo .= "Formato: $formato\n";
    $cuerpo .= "Área derivada: $area\n\n";
    $cuerpo .= "Puede consultar el estado en: http://localhost/tramite_documentario/?codigo=$codigo\n\n";
    $cuerpo .= "Atentamente,\nMunicipalidad";
    @mail($email, $asunto_mail, $cuerpo, "From: notificaciones@municipalidad.gob.pe");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$prediccion = null;
$error = null;
$ultimo_id = null;
$mensaje = null;
$confianza = null;
$consulta = null;

// --- Consulta por código ---
if (isset($_GET['codigo'])) {
    $stmt = $db->prepare("SELECT * FROM tramites WHERE codigo = :c");
    $stmt->execute([':c' => strtoupper(trim($_GET['codigo']))]);
    $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Feedback ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_feedback'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { $error = 'CSRF inválido.'; }
    else {
    $tramite_id = (int)$_POST['tramite_id'];
    if ($_POST['accion_feedback'] === 'confirmar') {
        $db->prepare("UPDATE tramites SET area_destino = area_predicha WHERE id = :id")->execute([":id" => $tramite_id]);
        $mensaje = "Predicción confirmada. ¡Gracias!";
    }
    if ($_POST['accion_feedback'] === 'corregir') {
        $db->prepare("UPDATE tramites SET area_destino = :area WHERE id = :id")->execute([":area" => $_POST['area_correcta'], ":id" => $tramite_id]);
        $mensaje = "Área corregida a: " . htmlspecialchars($_POST['area_correcta']) . ". ¡Gracias!";
    }
}
}

// --- Predicción ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['accion_feedback'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { $error = 'CSRF inválido.'; }
    else {
    $asunto = trim($_POST['asunto'] ?? '');
    $ciu_nombre = trim($_POST['ciu_nombre'] ?? '');
    $ciu_dni = trim($_POST['ciu_dni'] ?? '');
    $ciu_email = trim($_POST['ciu_email'] ?? '');
    $ciu_telefono = trim($_POST['ciu_telefono'] ?? '');
    $python = "C:\\Users\\AUGUSTO\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe";
    putenv('PYTHONIOENCODING=utf-8');

    $pdf_subido = null;
    $hay_pdf = !empty($_FILES['pdf']['name']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK;

    if (!$asunto) {
        $error = "Debes escribir el asunto del documento.";
    } elseif (!$hay_pdf) {
        $error = "Debes adjuntar un archivo PDF.";
    } else {
        $ext = strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $error = "Solo se permiten archivos PDF.";
        } elseif ($_FILES['pdf']['size'] > 10485760) {
            $error = "El PDF no debe superar los 10 MB.";
        } else {
            require_once __DIR__ . '/ia/analizar_pdf.php';
            require_once __DIR__ . '/ia/analisis.php';
            $tmp_path = $_FILES['pdf']['tmp_name'];

            // Extraer texto del PDF
            $texto_pdf = extraer_texto_pdf($tmp_path);

            if (!$texto_pdf || strlen(trim($texto_pdf)) < 10) {
                $error = "El PDF está vacío o no contiene texto legible.";
            } else {
                // Detectar contenido ofensivo
                $ofensivo = detectar_ofensivo($texto_pdf);
                if ($ofensivo) {
                    $error = "El documento contiene lenguaje inapropiado y no puede ser procesado.";
                } else {
                    // Verificar coincidencia con asunto
                    $score = similitud_textos($asunto, $texto_pdf);
                    if ($score < 0.3) {
                        $error = "El contenido del PDF no coincide con el asunto ingresado. Por favor verifique el documento.";
                    } else {
                        // Detectar formato
                        $formato = detectar_formato($texto_pdf);
                        if (!$formato) {
                            $error = "El documento no tiene un formato válido. Solo se aceptan: Constancia, Orden de Compra, Declaración Jurada, Resolución, Carta, Memorándum, Informe, Solicitud, Oficio, Proveído.";
                        } else {
                            // Verificar que el formato coincida con el asunto
                            $formatos_asunto = formatos_en_texto($asunto);
                            if ($formatos_asunto && !in_array($formato, $formatos_asunto)) {
                                $lista = implode(', ', $formatos_asunto);
                                $error = "El asunto indica que el documento debería ser: $lista. Pero el PDF adjunto tiene formato $formato. Verifique el documento.";
                            } else {
                                // Predecir área
                                $texto_limpio = limpiar_texto($texto_pdf);
                                $pred = predecir_area($texto_limpio);
                                $prediccion = $pred['area'];
                                $confianza = $pred['confianza'];
                                $asunto_pdf = mb_substr($texto_pdf, 0, 500);
                                if ($db) {
                                    $stmt = $db->prepare("INSERT INTO tramites (asunto, area_predicha, formato_documento, creado_por, ciudadano_nombre, ciudadano_dni, ciudadano_email, ciudadano_telefono) VALUES (:a, :ar, :fd, 'sistema', :cn, :cd, :ce, :ct)");
                                    $stmt->execute([":a" => $asunto_pdf, ":ar" => $prediccion, ":fd" => $formato, ":cn" => $ciu_nombre, ":cd" => $ciu_dni, ":ce" => $ciu_email, ":ct" => $ciu_telefono]);
                                    $ultimo_id = (int)$db->lastInsertId();
                                    $codigo = 'TRA-' . date('Y') . '-' . str_pad($ultimo_id, 5, '0', STR_PAD_LEFT);
                                    $db->prepare("UPDATE tramites SET codigo = :c WHERE id = :id")->execute([':c' => $codigo, ':id' => $ultimo_id]);
                                    $log = $db->prepare("INSERT INTO tramite_log (tramite_id, accion, usuario, detalle) VALUES (:id, 'creado', 'ciudadano', :d)");
                                    $log->execute([':id' => $ultimo_id, ':d' => "Trámite creado por ciudadano. Asunto: " . substr($asunto_pdf, 0, 200)]);
                                    notificar_ciudadano($ciu_email, $codigo, $asunto_pdf, $prediccion, $formato);
                                    $pdf_name = $codigo . '.pdf';
                                    $pdf_subido = 'uploads/' . $pdf_name;
                                    move_uploaded_file($tmp_path, __DIR__ . '/' . $pdf_subido);
                                    $db->prepare("UPDATE tramites SET pdf_path = :p WHERE id = :id")->execute([':p' => $pdf_subido, ':id' => $ultimo_id]);
                            }
                        }
                    }
                }
            }
        }
    }
    }
}
}

$areas = $db ? $db->query("SELECT nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN) : [];
$stats_total = $db ? $db->query("SELECT COUNT(*) FROM tramites")->fetchColumn() : 0;
$stats_feedback = $db ? $db->query("SELECT COUNT(*) FROM tramites WHERE area_destino != ''")->fetchColumn() : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Predictor Inteligente — Municipalidad</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{ box-sizing:border-box; }
        body{ font-family:'Segoe UI',Arial,sans-serif; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; padding:20px; margin:0; display:flex; align-items:center; justify-content:center; }
        .card{ background:#fff; border-radius:12px; padding:30px; width:680px; max-width:100%; box-shadow:0 10px 40px rgba(0,0,0,.15); }
        .tabs{ display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #e0e0e0; }
        .tabs a{ padding:10px 20px; text-decoration:none; color:#888; font-size:14px; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .2s; }
        .tabs a.act, .tabs a:hover{ color:#667eea; border-bottom-color:#667eea; }
        h2{ color:#333; margin:0 0 5px; font-size:20px; }
        .sub{ color:#888; font-size:13px; margin-bottom:15px; }
        .form-group{ display:flex; gap:10px; }
        .form-group input{ flex:1; padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; transition:border .2s; }
        .form-group input:focus{ border-color:#667eea; }
        .form-group button{ padding:12px 24px; background:#667eea; color:#fff; border:none; border-radius:8px; font-size:14px; cursor:pointer; transition:background .2s; white-space:nowrap; }
        .form-group button:hover{ background:#5a6fd6; }
        .result-box{ margin-top:15px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); border-radius:10px; padding:20px; text-align:center; color:#fff; }
        .result-box .area{ font-size:26px; font-weight:bold; margin-bottom:3px; }
        .result-box .conf{ font-size:13px; opacity:.85; }
        .feedback-box{ margin-top:12px; padding:15px; background:#f8f9ff; border-radius:8px; border:1px solid #e8e8ff; }
        .feedback-box p{ margin:0 0 8px; font-weight:600; color:#555; font-size:14px; }
        .feedback-row{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .feedback-row select{ flex:1; padding:8px; border:1px solid #ddd; border-radius:6px; min-width:150px; font-size:13px; }
        .btn{ padding:8px 18px; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
        .btn-success{ background:#28a745; color:#fff; }
        .btn-warning{ background:#ffc107; color:#333; }
        .btn:hover{ opacity:.9; }
        .msg{ margin-top:10px; padding:10px; background:#fff3cd; border-radius:6px; font-size:13px; }
        .err{ margin-top:10px; padding:10px; background:#f8d7da; border-radius:6px; color:#721c24; font-size:13px; }
        .nav-links{ display:flex; gap:8px; margin-top:15px; justify-content:center; flex-wrap:wrap; }
        .nav-links a{ color:#667eea; text-decoration:none; font-size:12px; padding:5px 12px; border:1px solid #667eea; border-radius:20px; transition:all .2s; }
        .nav-links a:hover{ background:#667eea; color:#fff; }
        .mini-stats{ display:flex; gap:8px; justify-content:center; margin-top:12px; flex-wrap:wrap; }
        .mini-stats span{ background:#f0f0f0; padding:4px 10px; border-radius:15px; font-size:11px; color:#666; }
        .consulta-info{ margin-top:12px; padding:15px; background:#e8f5e9; border-radius:8px; font-size:14px; }
        .consulta-info table{ width:100%; font-size:13px; }
        .consulta-info td{ padding:4px 8px; }
        .consulta-info td:first-child{ font-weight:600; color:#555; width:120px; }
        hr{ border:none; border-top:1px solid #e0e0e0; margin:15px 0; }
        .codigo-box{ margin-top:10px; padding:8px; background:rgba(255,255,255,.15); border-radius:6px; font-size:13px; }
    </style>
</head>
<body>
<div class="card">
    <div class="tabs">
        <a href="?<?= isset($_GET['codigo']) ? '' : '' ?>" class="<?= !isset($_GET['codigo']) ? 'act' : '' ?>">🔮 Predecir</a>
        <a href="?codigo=" class="<?= isset($_GET['codigo']) ? 'act' : '' ?>">🔍 Consultar</a>
    </div>

    <?php if (isset($_GET['codigo']) && !$consulta): ?>
    <h2>Consultar Trámite</h2>
    <div class="sub">Ingrese el código de seguimiento</div>
    <form method="GET" class="form-group" style="margin-bottom:10px;">
        <input type="text" name="codigo" placeholder="Ej: TRA-2026-00001" value="<?= htmlspecialchars($_GET['codigo']) ?>" required style="text-transform:uppercase;">
        <button type="submit">Buscar</button>
    </form>
    <?php if ($_GET['codigo'] !== ''): ?>
    <div class="err">Código no encontrado</div>
    <?php endif; ?>

    <?php elseif ($consulta): ?>
    <h2>Resultado de Consulta</h2>
    <div class="consulta-info">
        <table>
            <tr><td>Código</td><td style="font-family:monospace;font-weight:bold;"><?= htmlspecialchars($consulta['codigo']) ?></td></tr>
            <tr><td>Asunto</td><td><?= htmlspecialchars($consulta['asunto']) ?></td></tr>
            <tr><td>Área asignada</td><td><strong><?= htmlspecialchars($consulta['area_predicha']) ?></strong></td></tr>
            <tr><td>Formato</td><td><?= htmlspecialchars($consulta['formato_documento'] ?? '—') ?></td></tr>
            <tr><td>Estado</td><td><?php
                $estado = $consulta['estado'] ?? 'pendiente';
                if ($estado === 'pendiente') echo '<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:10px;">⏳ Pendiente de revisión</span>';
                elseif ($estado === 'derivado') echo '<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:10px;">✓ Derivado a ' . htmlspecialchars($consulta['area_destino'] ?: $consulta['area_predicha']) . '</span>';
                else echo htmlspecialchars($estado);
            ?></td></tr>
            <tr><td>Fecha</td><td><?= $consulta['created_at'] ?></td></tr>
            <?php if ($consulta['pdf_path']): ?>
            <tr><td>Documento</td><td><a href="<?= htmlspecialchars($consulta['pdf_path']) ?>" target="_blank" style="color:#667eea;">📄 Ver PDF</a></td></tr>
            <?php endif; ?>
            <?php if ($consulta['ciudadano_nombre']): ?>
            <tr><td>Registrado por</td><td><?= htmlspecialchars($consulta['ciudadano_nombre']) ?> (<?= htmlspecialchars($consulta['ciudadano_dni']) ?>)</td></tr>
            <?php endif; ?>
        </table>
    </div>
    <hr>
    <?php endif; ?>

    <?php if (!isset($_GET['codigo']) || ($consulta)): ?>
    <h2>🔮 Predecir Área</h2>
    <div class="sub">Ingrese el asunto del documento para derivarlo automáticamente</div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <input type="text" name="asunto" placeholder="Ej: Solicito una carta de no adeudo" value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>" required>
            <button type="submit">Predecir</button>
        </div>
        <div class="form-group" style="margin-top:8px;">
            <input type="file" name="pdf" accept=".pdf" required style="flex:1;padding:8px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;">
            <span style="font-size:12px;color:#999;white-space:nowrap;align-self:center;">Solo PDF</span>
        </div>
        <details style="margin-top:10px;">
            <summary style="font-size:13px;color:#667eea;cursor:pointer;">📋 Datos del ciudadano (opcional)</summary>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                <input type="text" name="ciu_nombre" placeholder="Nombre completo" value="<?= htmlspecialchars($_POST['ciu_nombre'] ?? '') ?>" style="flex:1;min-width:200px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;">
                <input type="text" name="ciu_dni" placeholder="DNI" value="<?= htmlspecialchars($_POST['ciu_dni'] ?? '') ?>" style="width:120px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;" maxlength="8">
                <input type="email" name="ciu_email" placeholder="Correo electrónico" value="<?= htmlspecialchars($_POST['ciu_email'] ?? '') ?>" style="flex:1;min-width:200px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;">
                <input type="text" name="ciu_telefono" placeholder="Teléfono" value="<?= htmlspecialchars($_POST['ciu_telefono'] ?? '') ?>" style="width:150px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;" maxlength="15">
            </div>
        </details>
    </form>
    <?php endif; ?>

    <?php if($mensaje): ?><div class="msg"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if($prediccion): ?>
    <div class="result-box">
        <div class="area"><?= htmlspecialchars($prediccion) ?></div>
        <?php if ($formato): ?><div style="font-size:14px;margin-top:4px;">Formato: <strong><?= htmlspecialchars($formato) ?></strong></div><?php endif; ?>
        <div class="conf">Confianza: <?= htmlspecialchars($confianza) ?>%</div>
        <?php if($ultimo_id): $codigo_mostrar = 'TRA-' . date('Y') . '-' . str_pad($ultimo_id, 5, '0', STR_PAD_LEFT); ?>
        <div class="codigo-box">Código de seguimiento: <strong style="font-family:monospace;font-size:15px;letter-spacing:1px;"><?= $codigo_mostrar ?></strong></div>
        <?php if ($pdf_subido): ?>
        <div style="margin-top:8px;"><a href="<?= htmlspecialchars($pdf_subido) ?>" target="_blank" style="color:#fff;text-decoration:underline;font-size:13px;">📄 Ver documento adjunto</a></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if($ultimo_id && $areas): ?>
    <div class="feedback-box">
        <p>¿Es correcta esta área?</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="tramite_id" value="<?= $ultimo_id ?>">
            <div class="feedback-row">
                <button type="submit" name="accion_feedback" value="confirmar" class="btn btn-success">Sí, correcta</button>
                <select name="area_correcta">
                    <?php foreach($areas as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= $a === $prediccion ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="accion_feedback" value="corregir" class="btn btn-warning">Corregir</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="mini-stats">
        <span>📋 <?= $stats_total ?> trámites</span>
        <span>✅ <?= $stats_feedback ?> feedbacks</span>
        <span>🏢 8 áreas</span>
        <span><a href="login_ciudadano.php" style="color:#667eea;text-decoration:none;">🔑 Mi Cuenta</a></span>
    </div>
</div>
</body>
</html>
