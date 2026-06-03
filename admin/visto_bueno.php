<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { http_response_code(403); exit; }

$accion = $_POST['accion'] ?? '';
if ($accion !== 'visto_bueno') { http_response_code(400); exit; }

$id = (int)($_POST['id'] ?? 0);
$area_destino = trim($_POST['area_destino'] ?? '');
$nota = trim($_POST['nota'] ?? '');

if (!$id || !$area_destino) { echo "Faltan datos"; exit; }

$db = (new Database())->getConnection();

$check = $db->prepare("SELECT COUNT(*) FROM areas WHERE nombre = :n");
$check->execute([':n' => $area_destino]);
if (!$check->fetchColumn()) { echo "El área no existe"; exit; }

$stmt = $db->prepare("UPDATE tramites SET area_destino = :a, estado = 'derivado' WHERE id = :id AND estado = 'pendiente'");
$stmt->execute([':a' => $area_destino, ':id' => $id]);

if ($stmt->rowCount() > 0) {
    $admin = ($_SESSION['admin_nombre'] ?? '') ?: ($_SESSION['admin_user'] ?? 'admin');
    $detalle = "Derivado a: $area_destino";
    if ($nota) $detalle .= " | Nota: $nota";
    $log = $db->prepare("INSERT INTO tramite_log (tramite_id, accion, usuario, detalle) VALUES (:id, 'derivado', :u, :d)");
    $log->execute([':id' => $id, ':u' => $admin, ':d' => $detalle]);

    // Notificar al ciudadano
    $info = $db->prepare("SELECT codigo, asunto, ciudadano_email FROM tramites WHERE id = :id");
    $info->execute([':id' => $id]);
    $r = $info->fetch(PDO::FETCH_ASSOC);
    if ($r && $r['ciudadano_email']) {
        $asunto_mail = "Trámite derivado - Municipalidad";
        $cuerpo = "Estimado ciudadano,\n\nSu trámite ha sido derivado al área correspondiente:\n\n";
        $cuerpo .= "Código: {$r['codigo']}\nAsunto: {$r['asunto']}\n";
        $cuerpo .= "Área destino: $area_destino\n";
        if ($nota) $cuerpo .= "Nota: $nota\n";
        $cuerpo .= "\nAtentamente,\nMunicipalidad";
        @mail($r['ciudadano_email'], $asunto_mail, $cuerpo, "From: notificaciones@municipalidad.gob.pe");
    }

    echo "OK";
} else {
    echo "El trámite ya fue procesado o no existe";
}
