<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$titulo = 'Dashboard';
require __DIR__ . '/header.php';

$total = $db->query("SELECT COUNT(*) FROM tramites WHERE archivado = 0")->fetchColumn();
$pendientes = $db->query("SELECT COUNT(*) FROM tramites WHERE estado = 'pendiente' AND archivado = 0")->fetchColumn();
$rechazados = $db->query("SELECT COUNT(*) FROM tramites WHERE estado = 'rechazado' AND archivado = 0")->fetchColumn();
$feedback = $db->query("SELECT COUNT(*) FROM tramites WHERE area_destino != '' AND archivado = 0")->fetchColumn();
$aciertos = $db->query("SELECT COUNT(*) FROM tramites WHERE area_destino != '' AND area_destino = area_predicha AND archivado = 0")->fetchColumn();

// Datos por área
$por_area = $db->query("SELECT area_predicha, COUNT(*) as c FROM tramites WHERE archivado = 0 GROUP BY area_predicha ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
$max_c = $por_area ? max(array_column($por_area, 'c')) : 1;
$colores = ['#007bff','#28a745','#dc3545','#ffc107','#17a2b8','#6f42c1','#fd7e14','#20c997'];

// Tendencia últimos 7 días
$tendencia = $db->query("SELECT DATE(created_at) as fecha, COUNT(*) as c FROM tramites WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY fecha")->fetchAll(PDO::FETCH_ASSOC);
$max_t = $tendencia ? max(array_column($tendencia, 'c')) : 1;

$recientes = $db->query("SELECT asunto, area_predicha, created_at FROM tramites WHERE archivado = 0 ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

// Pendientes de revisión (máximo 10)
$pendientes_lista = $db->query("SELECT id, codigo, asunto, formato_documento, area_predicha, ciudadano_nombre, created_at FROM tramites WHERE estado = 'pendiente' AND archivado = 0 ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$areas = $db->query("SELECT nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Pie chart segments
$total_area = array_sum(array_column($por_area, 'c')) ?: 1;
$angulo_acum = 0;
?>
<div class="bar"><h1>Dashboard</h1><div style="display:flex;align-items:center;gap:12px;"><span style="font-size:13px;color:var(--text2);">👤 <?= htmlspecialchars($_SESSION['admin_nombre'] ?: $_SESSION['admin_user']) ?></span><a href="reporte_mensual.php" class="btn btn-success" style="font-size:13px;">📊 Reporte Mensual</a><a href="logout.php">Cerrar sesión</a></div></div>

<div class="stats">
    <div class="stat"><strong><?= $total ?></strong><span>Trámites registrados</span></div>
    <div class="stat" style="<?= $pendientes ? 'background:#fff3cd;border:1px solid #ffc107;' : '' ?>"><strong><?= $pendientes ?></strong><span>Pendientes de revisión</span></div>
    <div class="stat" style="<?= $rechazados ? 'background:#f8d7da;border:1px solid #dc3545;' : '' ?>"><strong><?= $rechazados ?></strong><span>Rechazados</span></div>
    <div class="stat"><strong><?= $feedback ?></strong><span>Derivados</span></div>
    <div class="stat"><strong><?= $feedback ? round($aciertos / $feedback * 100) . '%' : '—' ?></strong><span>Precisión</span></div>
</div>

<?php if ($pendientes_lista): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid #ffc107;">
    <h3 style="margin-bottom:10px;color:#856404;">⏳ Pendientes de Revisión</h3>
    <table>
        <thead><tr><th>ID</th><th>Código</th><th>Asunto</th><th>Formato</th><th>Área Predicha</th><th>Ciudadano</th><th>Fecha</th><th>Acción</th></tr></thead>
        <tbody>
            <?php foreach ($pendientes_lista as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($p['codigo'] ?? '—') ?></td>
                <td><?= htmlspecialchars(mb_substr($p['asunto'], 0, 45)) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['formato_documento'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['area_predicha']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['ciudadano_nombre'] ?: '—') ?></td>
                <td style="font-size:12px;"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="aprobar(<?= $p['id'] ?>, '<?= htmlspecialchars($p['area_predicha'], ENT_QUOTES) ?>')">✓ Visto Bueno</button>
                    <button class="btn btn-danger btn-sm" onclick="rechazar(<?= $p['id'] ?>)">✗ Rechazar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="display:flex;gap:20px;flex-wrap:wrap;">

<div class="card" style="flex:2;min-width:300px;">
    <h3 style="margin-bottom:15px;">Trámites por Área</h3>
    <div class="bar-chart">
        <?php foreach ($por_area as $i => $r): ?>
        <div class="bar-row">
            <div class="bar-label"><?= htmlspecialchars($r['area_predicha']) ?></div>
            <div class="bar-fill" style="width:<?= max(5, round($r['c'] / $max_c * 100)) ?>%;background:<?= $colores[$i % 8] ?>;"><?= $r['c'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="flex:1;min-width:200px;text-align:center;">
    <h3 style="margin-bottom:15px;">Distribución</h3>
    <div style="position:relative;width:180px;height:180px;margin:0 auto 15px;">
        <svg viewBox="0 0 36 36" style="width:180px;height:180px;transform:rotate(-90deg);">
            <?php foreach ($por_area as $i => $r): ?>
            <?php
            $ang = $r['c'] / $total_area * 360;
            $pct = $r['c'] / $total_area * 100;
            $x1 = 18 + 16 * cos(deg2rad($angulo_acum));
            $y1 = 18 + 16 * sin(deg2rad($angulo_acum));
            $x2 = 18 + 16 * cos(deg2rad($angulo_acum + $ang));
            $y2 = 18 + 16 * sin(deg2rad($angulo_acum + $ang));
            $large = $ang > 180 ? 1 : 0;
            if ($ang > 0):
            ?>
            <path d="M18 18 L<?= $x1 ?> <?= $y1 ?> A16 16 0 <?= $large ?> 1 <?= $x2 ?> <?= $y2 ?> Z" fill="<?= $colores[$i % 8] ?>" />
            <?php endif; ?>
            <?php $angulo_acum += $ang; ?>
            <?php endforeach; ?>
        </svg>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:24px;font-weight:bold;color:#333;"><?= $total ?></div>
    </div>
    <?php foreach ($por_area as $i => $r): ?>
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:3px;justify-content:center;">
        <span style="width:10px;height:10px;border-radius:2px;background:<?= $colores[$i % 8] ?>;display:inline-block;"></span>
        <?= htmlspecialchars($r['area_predicha']) ?> (<?= round($r['c'] / $total_area * 100) ?>%)
    </div>
    <?php endforeach; ?>
</div>

</div>

<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px;">

<div class="card" style="flex:1;min-width:250px;">
    <h3 style="margin-bottom:15px;">Tendencia (7 días)</h3>
    <div style="display:flex;align-items:end;gap:6px;height:120px;padding-top:20px;">
        <?php foreach ($tendencia as $r): ?>
        <div style="flex:1;text-align:center;">
            <div style="height:<?= max(3, round($r['c'] / $max_t * 90)) ?>px;background:#007bff;border-radius:4px 4px 0 0;margin-bottom:3px;"><?= $r['c'] ?></div>
            <div style="font-size:10px;color:#666;"><?= date('d/m', strtotime($r['fecha'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="flex:1;min-width:250px;">
    <h3 style="margin-bottom:15px;">Últimos Trámites</h3>
    <table>
        <thead><tr><th>Asunto</th><th>Área</th><th>Fecha</th></tr></thead>
        <tbody>
            <?php foreach ($recientes as $r): ?>
            <tr>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($r['asunto'], 0, 35)) ?></td>
                <td><?= htmlspecialchars($r['area_predicha']) ?></td>
                <td><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<!-- Modal Dar Visto Bueno -->
<div id="modal-vb" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:480px;" onclick="event.stopPropagation()">
        <span class="close" onclick="cerrarModal('modal-vb')">&times;</span>
        <h3 style="margin-bottom:15px;">✓ Dar Visto Bueno</h3>
        <form id="form-vb">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="vb-id">
            <div class="form-group" style="margin-bottom:10px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">Área asignada:</label>
                <select name="area_destino" id="vb-area" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;">
                    <?php foreach ($areas as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">Nota interna (opcional):</label>
                <textarea name="nota" rows="2" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit;" placeholder="Comentario para el registro..."></textarea>
            </div>
            <p style="font-size:13px;color:#666;margin-bottom:15px;">Se marcará como <strong>derivado</strong> al área seleccionada.</p>
            <button type="submit" class="btn btn-success" style="width:100%;">✓ Confirmar Visto Bueno</button>
        </form>
        <div id="vb-msg" style="margin-top:10px;font-size:13px;display:none;padding:10px;border-radius:6px;"></div>
    </div>
</div>

<!-- Modal Rechazar -->
<div id="modal-rechazar" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:480px;" onclick="event.stopPropagation()">
        <span class="close" onclick="cerrarModal('modal-rechazar')">&times;</span>
        <h3 style="margin-bottom:15px;color:#dc3545;">✗ Rechazar Trámite</h3>
        <form id="form-rechazar">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="rech-id">
            <div class="form-group" style="margin-bottom:10px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">Motivo del rechazo:</label>
                <textarea name="motivo" rows="3" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit;" placeholder="Indique el motivo..."></textarea>
            </div>
            <p style="font-size:13px;color:#666;margin-bottom:15px;">Se notificará al ciudadano por correo.</p>
            <button type="submit" class="btn btn-danger" style="width:100%;">✗ Confirmar Rechazo</button>
        </form>
        <div id="rech-msg" style="margin-top:10px;font-size:13px;display:none;padding:10px;border-radius:6px;"></div>
    </div>
</div>

<script>
async function aprobar(id, area) {
    document.getElementById('vb-id').value = id;
    document.getElementById('vb-area').value = area;
    document.getElementById('form-vb').style.display = '';
    document.getElementById('vb-msg').style.display = 'none';
    document.getElementById('modal-vb').style.display = 'block';
}
function rechazar(id) {
    document.getElementById('rech-id').value = id;
    document.getElementById('form-rechazar').style.display = '';
    document.getElementById('rech-msg').style.display = 'none';
    document.getElementById('modal-rechazar').style.display = 'block';
}
function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}
document.getElementById('form-vb').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    const fd = new FormData(this);
    fd.append('accion', 'visto_bueno');
    try {
        const r = await fetch('visto_bueno.php', { method: 'POST', body: fd });
        const txt = await r.text();
        const msg = document.getElementById('vb-msg');
        msg.style.display = 'block';
        if (txt.trim() === 'OK') {
            msg.className = 'msg';
            msg.textContent = '✓ Trámite derivado correctamente';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.className = 'err';
            msg.textContent = txt;
            btn.disabled = false;
            btn.textContent = '✓ Confirmar Visto Bueno';
        }
    } catch (e) {
        const msg = document.getElementById('vb-msg');
        msg.style.display = 'block';
        msg.className = 'err';
        msg.textContent = 'Error de conexión';
        btn.disabled = false;
        btn.textContent = '✓ Confirmar Visto Bueno';
    }
});
document.getElementById('form-rechazar').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    const fd = new FormData(this);
    fd.append('accion', 'rechazar');
    try {
        const r = await fetch('rechazar.php', { method: 'POST', body: fd });
        const txt = await r.text();
        const msg = document.getElementById('rech-msg');
        msg.style.display = 'block';
        if (txt.trim() === 'OK') {
            msg.className = 'msg';
            msg.textContent = '✗ Trámite rechazado';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.className = 'err';
            msg.textContent = txt;
            btn.disabled = false;
            btn.textContent = '✗ Confirmar Rechazo';
        }
    } catch (e) {
        const msg = document.getElementById('rech-msg');
        msg.style.display = 'block';
        msg.className = 'err';
        msg.textContent = 'Error de conexión';
        btn.disabled = false;
        btn.textContent = '✗ Confirmar Rechazo';
    }
});
document.addEventListener('keydown', e => { if(e.key === 'Escape') { document.querySelectorAll('.modal').forEach(m => m.style.display='none'); } });
</script>

<style>
.modal{ position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5); }
.modal-content{ background:#fff; color:#333!important; margin:10% auto; padding:25px; border-radius:8px; width:500px; max-width:90%; position:relative; }
.close{ position:absolute; right:15px; top:10px; font-size:24px; cursor:pointer; }
</style>
</div></body></html>
