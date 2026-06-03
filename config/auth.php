<?php
require_once __DIR__ . '/database.php';

function admin_autenticar($username, $password) {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM admin_usuarios WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function admin_obtener_todos() {
    $db = (new Database())->getConnection();
    return $db->query("SELECT id, username, nombre, created_at FROM admin_usuarios ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function admin_crear($username, $password, $nombre) {
    $db = (new Database())->getConnection();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admin_usuarios (username, password_hash, nombre) VALUES (:u, :p, :n)");
    return $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nombre]);
}

function admin_actualizar($id, $username, $nombre, $password = null) {
    $db = (new Database())->getConnection();
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admin_usuarios SET username = :u, nombre = :n, password_hash = :p WHERE id = :id");
        return $stmt->execute([':u' => $username, ':n' => $nombre, ':p' => $hash, ':id' => $id]);
    } else {
        $stmt = $db->prepare("UPDATE admin_usuarios SET username = :u, nombre = :n WHERE id = :id");
        return $stmt->execute([':u' => $username, ':n' => $nombre, ':id' => $id]);
    }
}

function admin_eliminar($id) {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("DELETE FROM admin_usuarios WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}
