-- Esquema completo para municipalidad_ml
-- Compatible con MySQL 8.4+ (Aiven)

CREATE TABLE IF NOT EXISTS areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO areas (nombre) VALUES
('Concejo Municipal'),
('Alcaldia'),
('Gerencia Municipal'),
('Gerencia de Desarrollo Económico y Administración Tributaria'),
('Gerencia de Desarrollo Social'),
('Gerencia de Desarrollo Territorial e Infraestructura'),
('Gerencia de Desarrollo del Pueblo Asháninka'),
('Gerencia de Servicios Municipales y Gestión Ambiental'),
('Oficina General de Administración'),
('Oficina General de Asesoría Jurídica'),
('Oficina General de Atención al Ciudadano y Gestión Documental'),
('Oficina General de Planeamiento y Presupuesto');

CREATE TABLE IF NOT EXISTS tramites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20),
    asunto VARCHAR(500) NOT NULL,
    area_destino VARCHAR(100) DEFAULT '',
    area_predicha VARCHAR(100),
    confianza DECIMAL(5,2),
    formato_documento VARCHAR(50),
    estado VARCHAR(20) DEFAULT 'pendiente',
    archivado TINYINT(1) DEFAULT 0,
    motivo_rechazo TEXT,
    pdf_path VARCHAR(255),
    ciudadano_nombre VARCHAR(100),
    ciudadano_dni VARCHAR(8),
    ciudadano_email VARCHAR(100),
    ciudadano_telefono VARCHAR(15),
    creado_por VARCHAR(20) DEFAULT 'sistema',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tramite_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tramite_id INT NOT NULL,
    accion VARCHAR(50) NOT NULL,
    usuario VARCHAR(100),
    detalle TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ciudadanos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(8) UNIQUE,
    nombre VARCHAR(100),
    email VARCHAR(100),
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_usuarios (username, password_hash, nombre)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');
-- Password: admin123
