CREATE DATABASE IF NOT EXISTS municipalidad_ml
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE municipalidad_ml;

CREATE TABLE IF NOT EXISTS areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tramites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20),
    asunto VARCHAR(255) NOT NULL,
    area_destino VARCHAR(100) NOT NULL DEFAULT '',
    area_predicha VARCHAR(100),
    confianza DECIMAL(5, 2),
    creado_por ENUM('sistema', 'manual') DEFAULT 'sistema',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO areas (nombre) VALUES
    ('Tesorería'),
    ('Catastro'),
    ('Obras Públicas'),
    ('Recursos Humanos'),
    ('Defensa Civil'),
    ('Archivo Central'),
    ('Secretaría General'),
    ('Gerencia Municipal');
