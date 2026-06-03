<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

$mes = $_GET['mes'] ?? date('Y-m');
$anio = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);
$desde = $_GET['desde'] ?? "$anio-$mes_num-01";
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$filtro_formato = $_GET['formato'] ?? '';
$filtro_admin = $_GET['admin'] ?? '';

$where = ["archivado = 0"];
$params = [];
if ($desde) { $where[] = "DATE(t.created_at) >= :desde"; $params[':desde'] = $desde; }
if ($hasta) { $where[] = "DATE(t.created_at) <= :hasta"; $params[':hasta'] = $hasta; }
if ($filtro_formato) { $where[] = "t.formato_documento = :formato"; $params[':formato'] = $filtro_formato; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];

$titulo = 'Reportes';
require __DIR__ . '/header.php';

// Resumen
$total_mes = $db->prepare("SELECT COUNT(*) FROM tramites t $where_sql");
$total_mes->execute($params);
$total_mes = $total_mes->fetchColumn();

$feedback_mes = $db->prepare("SELECT COUNT(*) FROM tramites t $where_sql AND area_destino != ''");
$feedback_mes->execute($params);
$feedback_mes = $feedback_mes->fetchColumn();

$rechazados_mes = $db->prepare("SELECT COUNT(*) FROM tramites t $where_sql AND estado = 'rechazado'");
$rechazados_mes->execute($params);
$rechazados_mes = $rechazados_mes->fetchColumn();

// Por área
$por_area = $db->prepare("SELECT area_predicha, COUNT(*) as c FROM tramites t $where_sql GROUP BY area_predicha ORDER BY c DESC");
$por_area->execute($params);
$por_area = $por_area->fetchAll(PDO::FETCH_ASSOC);

// Por formato
$por_formato = $db->prepare("SELECT formato_documento, COUNT(*) as c FROM tramites t $where_sql AND formato_documento IS NOT NULL AND formato_documento != '' GROUP BY formato_documento ORDER BY c DESC");
$por_formato->execute($params);
$por_formato = $por_formato->fetchAll(PDO::FETCH_ASSOC);

// Por admin (de tramite_log)
$por_admin = $db->prepare("SELECT l.usuario, COUNT(*) as c FROM tramite_log l JOIN tramites t ON l.tramite_id = t.id $where_sql AND l.accion IN ('derivado','rechazado') GROUP BY l.usuario ORDER BY c DESC");
$por_admin->execute($params);
$por_admin = $por_admin->fetchAll(PDO::FETCH_ASSOC);

// Últimos 20
$recientes = $db->prepare("SELECT t.codigo, t.asunto, t.formato_documento, t.area_predicha, t.area_destino, t.estado, t.ciudadano_nombre, t.ciudadano_dni, t.created_at FROM tramites t $where_sql ORDER BY t.id DESC LIMIT 20");
$recientes->execute($params);
$recientes = $recientes->fetchAll(PDO::FETCH_ASSOC);

$formatos = $db->query("SELECT DISTINCT formato_documento FROM tramites WHERE formato_documento IS NOT NULL AND formato_documento != '' ORDER BY formato_documento")->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
@media print{ .no-print{ display:none!important; } body{ background:#fff!important; } .main{ margin-left:0!important; padding:20px!important; } }
.report-actions{ display:flex; gap:10px; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
.report-actions select, .report-actions input{ padding:8px; border:2px solid #e0e0e0; border-radius:6px; font-size:14px; }
.report-header{ margin-bottom:25px; }
.report-header h2{ margin:0 0 5px; }
.report-header p{ color:#666; margin:0; }
.report-stats{ display:flex; gap:15px; margin-bottom:25px; flex-wrap:wrap; }
.report-stat{ background:#f8f9fa; border-radius:8px; padding:15px 20px; text-align:center; flex:1; min-width:120px; }
.report-stat strong{ display:block; font-size:28px; color:#333; }
.report-stat span{ font-size:13px; color:#666; }
table.report-table{ width:100%; border-collapse:collapse; }
table.report-table th{ background:#f1f1f1; padding:10px; text-align:left; font-size:13px; }
table.report-table td{ padding:10px; border-bottom:1px solid #eee; font-size:13px; }
</style>
<div class="bar"><h1>📊 Reportes Avanzados</h1></div>

<div class="report-actions">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <label style="font-weight:600;">Desde:</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
        <label style="font-weight:600;">Hasta:</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
        <label style="font-weight:600;">Formato:</label>
        <select name="formato">
            <option value="">Todos</option>
            <?php foreach ($formatos as $f): ?>
            <option value="<?= htmlspecialchars($f) ?>" <?= $filtro_formato === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="reporte_mensual.php" class="btn" style="background:#6c757d;color:#fff;">Limpiar</a>
    </form>
    <button onclick="window.print()" class="btn btn-success no-print">🖨️ Imprimir / PDF</button>
    <a href="dashboard.php" class="btn no-print" style="background:#6c757d;color:#fff;">← Dashboard</a>
</div>

<div class="report-header">
    <h2>Reporte de <?= $desde ?> a <?= $hasta ?></h2>
    <p>Resumen de trámites registrados en el período</p>
</div>

<div class="report-stats">
    <div class="report-stat"><strong><?= $total_mes ?></strong><span>Total trámites</span></div>
    <div class="report-stat"><strong><?= $feedback_mes ?></strong><span>Derivados</span></div>
    <div class="report-stat" style="background:#f8d7da;"><strong><?= $rechazados_mes ?></strong><span>Rechazados</span></div>
    <div class="report-stat"><strong><?= count($por_area) ?></strong><span>Áreas involucradas</span></div>
</div>

<div style="display:flex;gap:20px;flex-wrap:wrap;">
<?php if ($por_area): ?>
<div class="card" style="flex:1;min-width:250px;">
    <h3 style="margin-bottom:10px;">Trámites por Área</h3>
    <table class="report-table">
        <thead><tr><th>Área</th><th>Cantidad</th></tr></thead>
        <tbody>
            <?php $total_areas = array_sum(array_column($por_area, 'c')); ?>
            <?php foreach ($por_area as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['area_predicha']) ?></td>
                <td><strong><?= $r['c'] ?></strong> (<?= round($r['c'] / $total_areas * 100) ?>%)</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($por_formato): ?>
<div class="card" style="flex:1;min-width:250px;">
    <h3 style="margin-bottom:10px;">Por Formato</h3>
    <table class="report-table">
        <thead><tr><th>Formato</th><th>Cantidad</th></tr></thead>
        <tbody>
            <?php foreach ($por_formato as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['formato_documento']) ?></td>
                <td><strong><?= $r['c'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($por_admin): ?>
<div class="card" style="flex:1;min-width:200px;">
    <h3 style="margin-bottom:10px;">Acciones por Admin</h3>
    <table class="report-table">
        <thead><tr><th>Admin</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($por_admin as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['usuario']) ?></td>
                <td><strong><?= $r['c'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
</div>

<?php if ($recientes): ?>
<div class="card" style="margin-top:20px;">
    <h3 style="margin-bottom:10px;">Últimos Trámites del Período</h3>
    <table class="report-table">
        <thead><tr><th>Código</th><th>Asunto</th><th>Formato</th><th>Área</th><th>Estado</th><th>Ciudadano</th><th>Fecha</th></tr></thead>
        <tbody>
            <?php foreach ($recientes as $r): ?>
            <tr>
                <td style="font-family:monospace;"><?= htmlspecialchars($r['codigo']) ?></td>
                <td><?= htmlspecialchars(mb_substr($r['asunto'], 0, 50)) ?></td>
                <td><?= htmlspecialchars($r['formato_documento'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['area_destino'] ?: $r['area_predicha']) ?></td>
                <td><?php
                    $est = $r['estado'] ?? 'pendiente';
                    if ($est === 'pendiente') echo '⏳ Pendiente';
                    elseif ($est === 'derivado') echo '✓ Derivado';
                    elseif ($est === 'rechazado') echo '✗ Rechazado';
                    else echo $est;
                ?></td>
                <td><?= htmlspecialchars($r['ciudadano_nombre'] ?: '—') ?></td>
                <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!$total_mes): ?>
<div style="text-align:center;padding:40px;color:#999;">No hay trámites registrados en este período.</div>
<?php endif; ?>
</div></body></html>
