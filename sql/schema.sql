-- Base de datos para invitaciones de boda (3 parejas)
-- MySQL 5.7 compatible
CREATE DATABASE IF NOT EXISTS boda_invitaciones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE boda_invitaciones;

CREATE TABLE IF NOT EXISTS parejas (
    id_pareja INT AUTO_INCREMENT PRIMARY KEY,
    nombre_pareja VARCHAR(150) NOT NULL,
    novio VARCHAR(100) NOT NULL,
    novia VARCHAR(100) NOT NULL,
    fecha_boda DATE NOT NULL,
    lugar_boda VARCHAR(200),
    hora_boda TIME,
    mensaje_invitacion TEXT,
    token_acceso VARCHAR(64) NOT NULL UNIQUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invitados (
    id_invitado INT AUTO_INCREMENT PRIMARY KEY,
    id_pareja INT NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(150),
    cantidad_personas INT NOT NULL DEFAULT 1,
    codigo_invitacion VARCHAR(64) NOT NULL UNIQUE,
    estado ENUM('pendiente','confirmado','rechazado') DEFAULT 'pendiente',
    mensaje TEXT,
    fecha_confirmacion DATETIME NULL,
    fecha_envio_whatsapp DATETIME NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pareja) REFERENCES parejas(id_pareja) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios_admin (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    rol VARCHAR(30) DEFAULT 'admin',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracion (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario admin por defecto: admin / admin123 (cambiar en producción)
INSERT INTO usuarios_admin (nombre, usuario, contrasena, rol)
VALUES ('Administrador', 'admin', '$2y$12$aZGGmNusaagScheE4q/Uqulzv5f.hLWMMVExpAe5NtQR2mOchcWQu', 'admin');
