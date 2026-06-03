<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once "config/database.php";
$db = (new Database())->getConnection();

if (!($_SESSION['ciudadano_id'] ?? false)) {
    header('Location: login_ciudadano.php');
    exit;
}

$email = $_SESSION['ciudadano_email'];
$nombre = $_SESSION['ciudadano_nombre'];

// Obtener trámites del ciudadano (por email)
$stmt = $db->prepare("SELECT * FROM tramites WHERE ciudadano_email = :e ORDER BY created_at DESC");
$stmt->execute([':e' => $email]);
$tramites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial de logs
$ids = array_column($tramites, 'id');
$logs_por_tramite = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $logStmt = $db->prepare("SELECT * FROM tramite_log WHERE tramite_id IN ($placeholders) ORDER BY created_at ASC");
    $logStmt->execute($ids);
    foreach ($logStmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
        $logs_por_tramite[$log['tramite_id']][] = $log;
    }
}

// Cerrar sesión
if (isset($_GET['salir'])) {
    session_destroy();
    header('Location: login_ciudadano.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Trámites — Ciudadano</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{ box-sizing:border-box; }
        body{ font-family:'Segoe UI',Arial,sans-serif; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; padding:20px; margin:0; }
        .header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .header h1{ color:#fff; margin:0; font-size:22px; }
        .header .user{ color:rgba(255,255,255,.8); font-size:13px; }
        .header a{ color:#fff; text-decoration:none; font-size:13px; padding:6px 16px; border:1px solid rgba(255,255,255,.4); border-radius:20px; transition:all .2s; }
        .header a:hover{ background:rgba(255,255,255,.15); }
        .card{ background:#fff; border-radius:12px; padding:30px; max-width:900px; margin:0 auto; box-shadow:0 10px 40px rgba(0,0,0,.15); }
        .empty{ text-align:center; padding:40px; color:#888; }
        .empty a{ color:#667eea; }
        table{ width:100%; border-collapse:collapse; font-size:13px; }
        th{ text-align:left; padding:10px 8px; border-bottom:2px solid #e0e0e0; color:#555; font-weight:600; }
        td{ padding:10px 8px; border-bottom:1px solid #f0f0f0; }
        tr:hover td{ background:#f8f9ff; }
        .tag{ display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; }
        .tag-pend{ background:#fff3cd; color:#856404; }
        .tag-der{ background:#d4edda; color:#155724; }
        .tag-rech{ background:#f8d7da; color:#721c24; }
        .badge{ background:#667eea; color:#fff; border-radius:12px; padding:2px 10px; font-size:11px; }
        .log-line{ font-size:11px; color:#888; margin-top:2px; }
        .log-line .icon{ margin-right:3px; }
        .volver{ text-align:center; margin-top:20px; }
        .volver a{ color:rgba(255,255,255,.8); text-decoration:none; font-size:13px; }
        .volver a:hover{ color:#fff; }
        @media(max-width:600px){ table, thead, tbody, th, td, tr{ display:block; } th{ display:none; } td{ padding:8px; border:none; } td::before{ content:attr(data-label); font-weight:600; display:inline-block; width:100px; color:#555; } tr{ margin-bottom:12px; border:1px solid #e0e0e0; border-radius:8px; padding:8px; } }
    </style>
</head>
<body>
<div class="header">
    <div>
        <h1>📋 Mis Trámites</h1>
        <div class="user">👤 <?= htmlspecialchars($nombre) ?> (<?= htmlspecialchars($email) ?>)</div>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="index.php">➕ Nuevo trámite</a>
        <a href="?salir=1">Cerrar sesión</a>
    </div>
</div>

<div class="card">
    <?php if (!$tramites): ?>
    <div class="empty">
        <p>No has registrado ningún trámite aún.</p>
        <p><a href="index.php">🔮 Predecir un trámite ahora</a></p>
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Asunto</th>
                <th>Formato</th>
                <th>Área</th>
                <th>Estado</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tramites as $t): ?>
            <tr>
                <td data-label="Código" style="font-family:monospace;font-weight:bold;"><?= htmlspecialchars($t['codigo']) ?></td>
                <td data-label="Asunto"><?= htmlspecialchars(substr($t['asunto'], 0, 80)) . (strlen($t['asunto'] ?? '') > 80 ? '...' : '') ?></td>
                <td data-label="Formato"><?= htmlspecialchars($t['formato_documento'] ?? '—') ?></td>
                <td data-label="Área"><?= htmlspecialchars($t['area_destino'] ?: $t['area_predicha']) ?></td>
                <td data-label="Estado">
                    <?php if (($t['estado'] ?? 'pendiente') === 'pendiente'): ?>
                        <span class="tag tag-pend">⏳ Pendiente</span>
                    <?php elseif ($t['estado'] === 'rechazado'): ?>
                        <span class="tag tag-rech">✗ Rechazado</span>
                    <?php else: ?>
                        <span class="tag tag-der">✓ Derivado</span>
                    <?php endif; ?>
                </td>
                <td data-label="Fecha"><?= $t['created_at'] ?></td>
            </tr>
            <tr style="background:#fafafa;">
                <td colspan="6" style="padding:4px 8px 10px;">
                    <?php if (isset($logs_por_tramite[$t['id']])): ?>
                        <?php foreach ($logs_por_tramite[$t['id']] as $log): ?>
                            <div class="log-line">
                                <span class="icon"><?= $log['accion'] === 'creado' ? '📝' : ($log['accion'] === 'derivado' ? '➡️' : '📌') ?></span>
                                <?= htmlspecialchars($log['detalle']) ?>
                                <span style="color:#bbb;margin-left:6px;">— <?= $log['created_at'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($t['pdf_path']): ?>
                        <div class="log-line"><a href="<?= htmlspecialchars($t['pdf_path']) ?>" target="_blank" style="color:#667eea;">📄 Ver PDF</a> <a href="constancia.php?id=<?= $t['id'] ?>" target="_blank" style="color:#667eea;margin-left:10px;">📜 Constancia</a></div>
                    <?php else: ?>
                        <div class="log-line"><a href="constancia.php?id=<?= $t['id'] ?>" target="_blank" style="color:#667eea;">📜 Constancia</a></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<div class="volver"><a href="index.php">← Volver al inicio</a></div>
</body>
</html>
