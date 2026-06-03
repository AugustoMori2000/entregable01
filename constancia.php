<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once "config/database.php";
$db = (new Database())->getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM tramites WHERE id = :id");
$stmt->execute([':id' => $id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$t) { echo "Trámite no encontrado"; exit; }

// Verificar que el ciudadano logueado sea el dueño
if (!($_SESSION['ciudadano_id'] ?? false) || $_SESSION['ciudadano_email'] !== $t['ciudadano_email']) {
    header('HTTP/1.0 403 Forbidden');
    echo "No autorizado";
    exit;
}

$estado_texto = match($t['estado'] ?? 'pendiente') {
    'pendiente' => '⏳ Pendiente de revisión',
    'derivado' => '✓ Derivado',
    'rechazado' => '✗ Rechazado',
    default => $t['estado']
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia — <?= htmlspecialchars($t['codigo']) ?></title>
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Segoe UI',Arial,sans-serif; background:#fff; padding:40px; color:#333; }
        .constancia{ max-width:700px; margin:0 auto; border:2px solid #333; padding:40px; }
        .header{ text-align:center; margin-bottom:30px; border-bottom:2px solid #333; padding-bottom:15px; }
        .header h1{ font-size:18px; text-transform:uppercase; letter-spacing:2px; }
        .header p{ font-size:12px; color:#666; margin-top:4px; }
        .titulo{ text-align:center; font-size:16px; font-weight:bold; margin-bottom:25px; }
        table{ width:100%; border-collapse:collapse; margin-bottom:20px; }
        td{ padding:8px 10px; border-bottom:1px solid #ddd; font-size:13px; }
        td:first-child{ font-weight:600; width:140px; }
        .footer{ text-align:center; margin-top:30px; font-size:12px; color:#888; border-top:1px solid #ddd; padding-top:15px; }
        .area-box{ background:#f0f4ff; padding:15px; text-align:center; border-radius:8px; margin:15px 0; font-size:18px; font-weight:bold; }
        @media print{ body{ padding:0; } .no-print{ display:none; } }
    </style>
</head>
<body>
<div class="constancia">
    <div class="header">
        <h1>Municipalidad</h1>
        <p>Sistema de Trámite Documentario</p>
    </div>
    <div class="titulo">CONSTANCIA DE REGISTRO DE TRÁMITE</div>

    <table>
        <tr><td>Código</td><td style="font-family:monospace;font-weight:bold;"><?= htmlspecialchars($t['codigo']) ?></td></tr>
        <tr><td>Asunto</td><td><?= htmlspecialchars($t['asunto']) ?></td></tr>
        <tr><td>Fecha de registro</td><td><?= $t['created_at'] ?></td></tr>
        <tr><td>Formato</td><td><?= htmlspecialchars($t['formato_documento'] ?? '—') ?></td></tr>
        <tr><td>Estado</td><td><?= $estado_texto ?></td></tr>
        <?php if ($t['area_destino']): ?>
        <tr><td>Área destino</td><td><?= htmlspecialchars($t['area_destino']) ?></td></tr>
        <?php endif; ?>
        <?php if ($t['motivo_rechazo']): ?>
        <tr><td>Motivo</td><td style="color:#dc3545;"><?= htmlspecialchars($t['motivo_rechazo']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="area-box">
        <?= htmlspecialchars($t['area_predicha']) ?>
    </div>

    <table>
        <tr><td>Registrado por</td><td><?= htmlspecialchars($t['ciudadano_nombre'] ?: '—') ?> (<?= htmlspecialchars($t['ciudadano_dni'] ?: '—') ?>)</td></tr>
        <tr><td>Email</td><td><?= htmlspecialchars($t['ciudadano_email'] ?: '—') ?></td></tr>
    </table>

    <div class="footer">
        <p>Documento generado electrónicamente el <?= date('d/m/Y H:i') ?></p>
        <p>Puede verificar el estado en: http://localhost/tramite_documentario/?codigo=<?= urlencode($t['codigo']) ?></p>
        <button onclick="window.print()" class="no-print" style="margin-top:10px;padding:8px 20px;background:#667eea;color:#fff;border:none;border-radius:6px;cursor:pointer;">🖨️ Imprimir / Guardar PDF</button>
    </div>
</div>
</body>
</html>
