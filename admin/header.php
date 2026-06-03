<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$root = preg_replace('#/admin$#', '', $dir);
$admin_url = rtrim($root, '/') . '/admin/';
$public_url = rtrim($root, '/') . '/';

if (isset($_SESSION['admin']) && $_SESSION['admin']) {
    $tiempo_max = 120;
    if (isset($_SESSION['admin_ultimo_acceso']) && (time() - $_SESSION['admin_ultimo_acceso']) > $tiempo_max) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $admin_url . 'login.php?expiro=1');
        exit;
    }
    $_SESSION['admin_ultimo_acceso'] = time();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
            die('CSRF token inválido.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo ?? 'Admin' ?> — Municipalidad ML</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store">
    <script>window.addEventListener('pageshow',function(e){if(e.persisted)window.location.reload()});</script>
    <style>
        :root{ --bg:#f0f2f5; --card:#fff; --text:#333; --text2:#666; --border:#dee2e6; --th:#f8f9fa; --input:#ced4da; }
        .dark{ --bg:#1a1d23; --card:#242830; --text:#e0e0e0; --text2:#aaa; --border:#3a3d45; --th:#2a2d35; --input:#3a3d45; }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Segoe UI',Arial,sans-serif; background:var(--bg); display:flex; min-height:100vh; transition:background .3s; }
        .sidebar{ width:250px; background:#1a2332; color:#fff; padding:0; flex-shrink:0; }
        .sidebar h2{ padding:20px; font-size:16px; border-bottom:1px solid #2a3a4a; }
        .sidebar a{ display:block; padding:12px 20px; color:#b0c4de; text-decoration:none; border-left:3px solid transparent; transition:all .2s; }
        .sidebar a:hover, .sidebar a.activo{ background:#243044; color:#fff; border-left-color:#007bff; }
        .main{ flex:1; padding:30px; max-width:calc(100vw - 250px); color:var(--text); }
        .bar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; }
        .bar h1{ font-size:22px; color:var(--text); }
        .bar a{ color:#dc3545; text-decoration:none; }
        .card{ background:var(--card); border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1); transition:background .3s; }
        .stats{ display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px; }
        .stat{ flex:1; min-width:150px; background:var(--card); border-radius:8px; padding:20px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.1); }
        .stat strong{ display:block; font-size:28px; color:#007bff; }
        .stat span{ color:var(--text2); font-size:13px; }
        table{ width:100%; border-collapse:collapse; color:var(--text); }
        th,td{ border:1px solid var(--border); padding:10px 12px; text-align:left; font-size:14px; }
        th{ background:var(--th); font-weight:600; }
        .btn{ display:inline-block; padding:8px 16px; border:none; border-radius:4px; cursor:pointer; font-size:14px; text-decoration:none; }
        .btn-primary{ background:#007bff; color:#fff; }
        .btn-success{ background:#28a745; color:#fff; }
        .btn-danger{ background:#dc3545; color:#fff; }
        .btn-sm{ padding:4px 10px; font-size:12px; }
        input,select{ padding:8px 12px; border:1px solid var(--input); border-radius:4px; font-size:14px; background:var(--card); color:var(--text); }
        .dark input[type="date"]{ color-scheme:dark; }
        .pagination{ margin-top:15px; display:flex; gap:5px; }
        .pagination a{ padding:6px 12px; border:1px solid var(--border); border-radius:4px; text-decoration:none; color:#007bff; background:var(--card); }
        .pagination a.act{ background:#007bff; color:#fff; border-color:#007bff; }
        .busqueda{ display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; }
        .alert{ padding:12px; border-radius:4px; margin-bottom:15px; }
        .alert-success{ background:#d4edda; color:#155724; }
        .alert-danger{ background:#f8d7da; color:#721c24; }
        .bar-chart{ margin-top:10px; }
        .bar-row{ display:flex; align-items:center; margin-bottom:6px; }
        .bar-label{ width:180px; font-size:13px; flex-shrink:0; color:var(--text); }
        .bar-fill{ height:22px; background:#007bff; border-radius:4px; display:flex; align-items:center; padding:0 8px; color:#fff; font-size:12px; min-width:30px; transition:width .5s; }
        .dark-toggle{ padding:10px 20px; cursor:pointer; color:#b0c4de; font-size:13px; border-top:1px solid #2a3a4a; margin-top:auto; }
        .dark-toggle:hover{ background:#243044; color:#fff; }
        .sidebar{ display:flex; flex-direction:column; }
        @media(max-width:768px){ .sidebar{ width:60px; } .sidebar h2, .sidebar a span, .dark-toggle span{ display:none; } .main{ max-width:calc(100vw - 60px); } }
    </style>
</head>
<body>
<div class="sidebar">
    <h2>🏛️ Municipalidad</h2>
    <a href="<?= $admin_url ?>dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'activo' : '' ?>"><span>📊 Dashboard</span></a>
    <a href="<?= $admin_url ?>reporte_mensual.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reporte_mensual.php' ? 'activo' : '' ?>"><span>📈 Reportes</span></a>
    <a href="<?= $admin_url ?>areas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'areas.php' ? 'activo' : '' ?>"><span>🏢 Áreas</span></a>
    <a href="<?= $admin_url ?>usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'activo' : '' ?>"><span>👥 Usuarios</span></a>
    <a href="<?= $admin_url ?>historial.php" class="<?= basename($_SERVER['PHP_SELF']) === 'historial.php' ? 'activo' : '' ?>"><span>📋 Historial</span></a>
    <a href="<?= $admin_url ?>backup.php" class="<?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'activo' : '' ?>"><span>💾 Respaldo</span></a>
    <a href="<?= $admin_url ?>batch.php" class="<?= basename($_SERVER['PHP_SELF']) === 'batch.php' ? 'activo' : '' ?>"><span>📤 Carga masiva</span></a>
    <a href="<?= $admin_url ?>exportar_modelo.php" class="<?= basename($_SERVER['PHP_SELF']) === 'exportar_modelo.php' ? 'activo' : '' ?>"><span>📦 Exportar modelo</span></a>
    <a href="../retrain.php" target="_blank"><span>🔄 Reentrenar</span></a>
    <a href="<?= $public_url ?>index.php" target="_blank"><span>🔮 Predicción</span></a>
    <div class="dark-toggle" onclick="toggleDark()"><span>🌙 Modo oscuro</span></div>
    <a href="<?= $admin_url ?>logout.php" style="border-top:1px solid #2a3a4a;"><span>🚪 Salir</span></a>
</div>
<div class="main">

<script>
function toggleDark(){
    document.body.classList.toggle('dark');
    localStorage.setItem('dark', document.body.classList.contains('dark') ? '1' : '0');
}
if(localStorage.getItem('dark') === '1') document.body.classList.add('dark');
setInterval(function(){fetch('ping.php')},30000);
</script>
