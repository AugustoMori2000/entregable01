<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) { http_response_code(403); exit; }

$accion = $_POST['accion'] ?? '';
if ($accion !== 'rechazar') { http_response_code(400); exit; }

$id = (int)($_POST['id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if (!$id || !$motivo) { echo "Faltan datos"; exit; }

$db = (new Database())->getConnection();

$stmt = $db->prepare("UPDATE tramites SET estado = 'rechazado', motivo_rechazo = :m WHERE id = :id AND estado = 'pendiente'");
$stmt->execute([':m' => $motivo, ':id' => $id]);

if ($stmt->rowCount() > 0) {
    $admin = ($_SESSION['admin_nombre'] ?? '') ?: ($_SESSION['admin_user'] ?? 'admin');
    $log = $db->prepare("INSERT INTO tramite_log (tramite_id, accion, usuario, detalle) VALUES (:id, 'rechazado', :u, :d)");
    $log->execute([':id' => $id, ':u' => $admin, ':d' => "Rechazado: $motivo"]);

    // Notificar al ciudadano
    $info = $db->prepare("SELECT codigo, asunto, ciudadano_email FROM tramites WHERE id = :id");
    $info->execute([':id' => $id]);
    $r = $info->fetch(PDO::FETCH_ASSOC);
    if ($r && $r['ciudadano_email']) {
        $asunto_mail = "Trámite rechazado - Municipalidad";
        $cuerpo = "Estimado ciudadano,\n\nSu trámite ha sido rechazado:\n\n";
        $cuerpo .= "Código: {$r['codigo']}\nAsunto: {$r['asunto']}\n";
        $cuerpo .= "Motivo: $motivo\n\n";
        $cuerpo .= "Atentamente,\nMunicipalidad";
        @mail($r['ciudadano_email'], $asunto_mail, $cuerpo, "From: notificaciones@municipalidad.gob.pe");
    }

    echo "OK";
} else {
    echo "El trámite ya fue procesado o no existe";
}
