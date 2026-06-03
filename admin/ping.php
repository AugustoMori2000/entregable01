<?php
session_start();
if (!($_SESSION['admin'] ?? false)) { http_response_code(401); exit; }
$_SESSION['admin_ultimo_acceso'] = time();
echo 'ok';
