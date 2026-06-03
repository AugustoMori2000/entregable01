<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('HTTP/1.0 403 Forbidden'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM tramites WHERE id = :id");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) { echo "<p>No encontrado</p>"; exit; }
?>
<div style="color:#333;">
<table style="width:100%;border-collapse:collapse;">
    <tr><td style="font-weight:bold;padding:6px;width:120px;color:#333;">ID</td><td style="padding:6px;color:#333;"><?= $r['id'] ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Código</td><td style="padding:6px;font-family:monospace;color:#333;"><?= htmlspecialchars($r['codigo']) ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Asunto</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['asunto']) ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Área Predicha</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['area_predicha']) ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Formato</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['formato_documento'] ?? '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Área Real</td><td style="padding:6px;color:#333;"><?= $r['area_destino'] ? htmlspecialchars($r['area_destino']) : '<span style="color:#999;">—</span>' ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Estado</td><td style="padding:6px;color:#333;"><?php
        $estado = $r['estado'] ?? 'pendiente';
        if ($estado === 'pendiente') echo '<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:10px;">⏳ Pendiente</span>';
        elseif ($estado === 'derivado') echo '<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:10px;">✓ Derivado</span>';
        elseif ($estado === 'rechazado') echo '<span style="background:#f8d7da;color:#721c24;padding:2px 10px;border-radius:10px;">✗ Rechazado</span>';
        else echo htmlspecialchars($estado);
    ?></td></tr>
    <?php if ($r['motivo_rechazo']): ?>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Motivo</td><td style="padding:6px;color:#dc3545;"><?= htmlspecialchars($r['motivo_rechazo']) ?></td></tr>
    <?php endif; ?>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Confianza</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['confianza'] ?? '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Origen</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['creado_por'] ?? '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Fecha</td><td style="padding:6px;color:#333;"><?= $r['created_at'] ?></td></tr>
    <?php if ($r['ciudadano_nombre'] || $r['ciudadano_dni'] || $r['ciudadano_email']): ?>
    <tr><td style="font-weight:bold;padding:6px;border-top:1px solid #ddd;color:#333;" colspan="2">Datos del Ciudadano</td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Nombre</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['ciudadano_nombre'] ?: '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">DNI</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['ciudadano_dni'] ?: '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Email</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['ciudadano_email'] ?: '—') ?></td></tr>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Teléfono</td><td style="padding:6px;color:#333;"><?= htmlspecialchars($r['ciudadano_telefono'] ?: '—') ?></td></tr>
    <?php endif; ?>
    <?php if ($r['pdf_path']): ?>
    <tr><td style="font-weight:bold;padding:6px;color:#333;">Documento</td><td style="padding:6px;color:#333;"><a href="../<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank" style="color:#007bff;">📄 Ver PDF</a></td></tr>
    <?php endif; ?>
</table>

<?php
$logs = $db->prepare("SELECT * FROM tramite_log WHERE tramite_id = :id ORDER BY created_at ASC");
$logs->execute([':id' => $id]);
$entries = $logs->fetchAll(PDO::FETCH_ASSOC);
if ($entries):
?>
<h4 style="margin:16px 0 8px;color:#333;border-bottom:1px solid #ddd;padding-bottom:6px;">📋 Historial</h4>
<div style="padding-left:8px;">
<?php foreach ($entries as $log): ?>
    <div style="display:flex;gap:10px;margin-bottom:10px;align-items:flex-start;">
        <div style="min-width:28px;text-align:center;font-size:16px;">
            <?= $log['accion'] === 'creado' ? '📝' : ($log['accion'] === 'derivado' ? '➡️' : ($log['accion'] === 'rechazado' ? '✗' : '📌')) ?>
        </div>
        <div>
            <div style="font-size:13px;color:#555;">
                <strong><?= htmlspecialchars($log['usuario']) ?></strong>
                <span style="color:#999;margin-left:8px;"><?= $log['created_at'] ?></span>
            </div>
            <div style="font-size:13px;color:#666;margin-top:2px;"><?= htmlspecialchars($log['detalle']) ?></div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
