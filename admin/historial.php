<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$buscar = $_GET['buscar'] ?? '';
$buscar_dni = $_GET['buscar_dni'] ?? '';
$buscar_email = $_GET['buscar_email'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$mostrar_archivados = $_GET['archivados'] ?? '';

$where = [];
$params = [];
if ($buscar) { $where[] = "asunto LIKE :buscar"; $params[':buscar'] = "%$buscar%"; }
if ($buscar_dni) { $where[] = "ciudadano_dni LIKE :dni"; $params[':dni'] = "%$buscar_dni%"; }
if ($buscar_email) { $where[] = "ciudadano_email LIKE :email"; $params[':email'] = "%$buscar_email%"; }
if ($filtro_area) { $where[] = "area_predicha = :area"; $params[':area'] = $filtro_area; }
if ($fecha_desde) { $where[] = "DATE(created_at) >= :desde"; $params[':desde'] = $fecha_desde; }
if ($fecha_hasta) { $where[] = "DATE(created_at) <= :hasta"; $params[':hasta'] = $fecha_hasta; }
if ($filtro_estado) { $where[] = "estado = :estado"; $params[':estado'] = $filtro_estado; }
if (!$mostrar_archivados) { $where[] = "archivado = 0"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tramites_export.csv"');
    echo "\xEF\xBB\xBF";
    $sep = ';';
    echo "\"ID\";\"Codigo\";\"Asunto\";\"Formato\";\"Area Predicha\";\"Area Real\";\"Estado\";\"PDF\";\"Ciudadano\";\"DNI\";\"Email\";\"Telefono\";\"Fecha\"\n";
    $stmt = $db->prepare("SELECT id, codigo, asunto, formato_documento, area_predicha, area_destino, estado, pdf_path, ciudadano_nombre, ciudadano_dni, ciudadano_email, ciudadano_telefono, created_at FROM tramites $where_sql ORDER BY id ASC");
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $asunto_corto = preg_replace('/\s+/', ' ', trim(mb_substr($r['asunto'], 0, 100))) . (mb_strlen($r['asunto']) > 100 ? '...' : '');
        $pdf = $r['pdf_path'] ? 'Si' : 'No';
        echo "\"{$r['id']}\";\"{$r['codigo']}\";\"" . str_replace('"', '""', $asunto_corto) . "\";\"" . str_replace('"', '""', ($r['formato_documento'] ?: '')) . "\";\"" . str_replace('"', '""', $r['area_predicha']) . "\";\"" . str_replace('"', '""', ($r['area_destino'] ?: '—')) . "\";\"" . str_replace('"', '""', $r['estado'] ?? 'pendiente') . "\";\"$pdf\";\"" . str_replace('"', '""', ($r['ciudadano_nombre'] ?: '')) . "\";\"" . str_replace('"', '""', ($r['ciudadano_dni'] ?: '')) . "\";\"" . str_replace('"', '""', ($r['ciudadano_email'] ?: '')) . "\";\"" . str_replace('"', '""', ($r['ciudadano_telefono'] ?: '')) . "\";\"{$r['created_at']}\"\n";
    }
    exit;
}

if ($_POST && $_POST['accion'] === 'archivar') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { die('CSRF inválido.'); }
    $db->prepare("UPDATE tramites SET archivado = 1 WHERE id = :id")->execute([':id' => $_POST['id']]);
    header('Location: historial.php?buscar=' . urlencode($buscar) . '&area=' . urlencode($filtro_area) . '&p=' . $page . '&fecha_desde=' . urlencode($fecha_desde) . '&fecha_hasta=' . urlencode($fecha_hasta) . '&estado=' . urlencode($filtro_estado) . '&archivados=' . urlencode($mostrar_archivados));
    exit;
}
if ($_POST && $_POST['accion'] === 'desarchivar') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { die('CSRF inválido.'); }
    $db->prepare("UPDATE tramites SET archivado = 0 WHERE id = :id")->execute([':id' => $_POST['id']]);
    header('Location: historial.php?buscar=' . urlencode($buscar) . '&area=' . urlencode($filtro_area) . '&p=' . $page . '&fecha_desde=' . urlencode($fecha_desde) . '&fecha_hasta=' . urlencode($fecha_hasta) . '&estado=' . urlencode($filtro_estado) . '&archivados=' . urlencode($mostrar_archivados));
    exit;
}

$titulo = 'Historial';
require __DIR__ . '/header.php';

$total = $db->prepare("SELECT COUNT(*) FROM tramites $where_sql");
$total->execute($params);
$total_rows = $total->fetchColumn();

$sql = "SELECT * FROM tramites $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$areas = $db->query("SELECT nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$total_pages = max(1, ceil($total_rows / $limit));
?>
<div class="bar"><h1>Historial de Trámites</h1></div>

<form method="GET" class="busqueda">
    <input type="text" name="buscar" placeholder="Buscar asunto..." value="<?= htmlspecialchars($buscar) ?>" style="flex:1;min-width:180px;">
    <input type="text" name="buscar_dni" placeholder="DNI..." value="<?= htmlspecialchars($buscar_dni) ?>" style="width:110px;">
    <input type="text" name="buscar_email" placeholder="Email..." value="<?= htmlspecialchars($buscar_email) ?>" style="width:170px;">
    <select name="area">
        <option value="">Todas las áreas</option>
        <?php foreach ($areas as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $filtro_area === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
        <option value="derivado" <?= $filtro_estado === 'derivado' ? 'selected' : '' ?>>✓ Derivado</option>
        <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>✗ Rechazado</option>
    </select>
    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>" title="Desde">
    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" title="Hasta">
    <label style="font-size:13px;display:flex;align-items:center;gap:4px;">
        <input type="checkbox" name="archivados" value="1" <?= $mostrar_archivados ? 'checked' : '' ?> onchange="this.form.submit()"> Archivados
    </label>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="historial.php" class="btn" style="background:#6c757d;color:#fff;">Limpiar</a>
    <a href="?export=csv&buscar=<?= urlencode($buscar) ?>&buscar_dni=<?= urlencode($buscar_dni) ?>&buscar_email=<?= urlencode($buscar_email) ?>&area=<?= urlencode($filtro_area) ?>&fecha_desde=<?= urlencode($fecha_desde) ?>&fecha_hasta=<?= urlencode($fecha_hasta) ?>&estado=<?= urlencode($filtro_estado) ?>&archivados=<?= urlencode($mostrar_archivados) ?>" class="btn btn-success">CSV</a>
</form>

<div style="margin-bottom:10px;color:#666;"><?= $total_rows ?> resultado(s)</div>

<div class="card" style="padding:0;overflow-x:auto;">
<table>
    <thead><tr><th>ID</th><th>Código</th><th>Asunto</th><th>Formato</th><th>Área Predicha</th><th>Área Real</th><th>Estado</th><th>Ciudadano</th><th>DNI</th><th>PDF</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <tr onclick="ver(<?= $r['id'] ?>)" style="cursor:pointer;">
            <td><?= $r['id'] ?></td>
            <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($r['codigo'] ?? '—') ?></td>
            <td><?= htmlspecialchars(mb_substr($r['asunto'], 0, 60)) ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($r['formato_documento'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['area_predicha']) ?></td>
            <td><?= $r['area_destino'] ? htmlspecialchars($r['area_destino']) : '<span style="color:#999;">—</span>' ?></td>
            <td style="font-size:12px;"><?php
                $estado = $r['estado'] ?? 'pendiente';
                if ($estado === 'pendiente') echo '<span style="color:#856404;background:#fff3cd;padding:2px 8px;border-radius:10px;">⏳ Pendiente</span>';
                elseif ($estado === 'derivado') echo '<span style="color:#155724;background:#d4edda;padding:2px 8px;border-radius:10px;">✓ Derivado</span>';
                elseif ($estado === 'rechazado') echo '<span style="color:#721c24;background:#f8d7da;padding:2px 8px;border-radius:10px;">✗ Rechazado</span>';
                else echo htmlspecialchars($estado);
            ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($r['ciudadano_nombre'] ?? '—') ?></td>
            <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($r['ciudadano_dni'] ?? '—') ?></td>
            <td><?= $r['pdf_path'] ? '<a href="../'.$r['pdf_path'].'" target="_blank" title="Ver PDF">📄</a>' : '—' ?></td>
            <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
                <form method="POST" style="display:inline" onclick="event.stopPropagation()">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <?php if ($r['archivado']): ?>
                    <input type="hidden" name="accion" value="desarchivar">
                    <button type="submit" class="btn btn-sm" style="background:#ffc107;color:#333;">Restaurar</button>
                    <?php else: ?>
                    <input type="hidden" name="accion" value="archivar">
                    <button type="submit" class="btn btn-sm" style="background:#6c757d;color:#fff;">Archivar</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <a href="?p=<?= $i ?>&buscar=<?= urlencode($buscar) ?>&area=<?= urlencode($filtro_area) ?>&fecha_desde=<?= urlencode($fecha_desde) ?>&fecha_hasta=<?= urlencode($fecha_hasta) ?>&estado=<?= urlencode($filtro_estado) ?>&archivados=<?= urlencode($mostrar_archivados) ?>" class="<?= $i === $page ? 'act' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<div id="modal" class="modal" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close" onclick="document.getElementById('modal').style.display='none'">&times;</span>
        <div id="modal-body"></div>
    </div>
</div>

<script>
async function ver(id) {
    const r = await fetch('detalle.php?id=' + id);
    document.getElementById('modal-body').innerHTML = await r.text();
    document.getElementById('modal').style.display = 'block';
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') document.getElementById('modal').style.display = 'none'; });
</script>

<style>
.modal{ display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5); }
.modal-content{ background:#fff; color:#333!important; margin:10% auto; padding:25px; border-radius:8px; width:500px; max-width:90%; position:relative; }
.close{ position:absolute; right:15px; top:10px; font-size:24px; cursor:pointer; }
</style>
</div></body></html>
