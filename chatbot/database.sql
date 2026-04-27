-- =====================================================
-- Base de datos principal del proyecto
-- Nombre: App_educativa
-- =====================================================

CREATE DATABASE IF NOT EXISTS App_educativa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE App_educativa;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS notificaciones_acudiente;
DROP TABLE IF EXISTS acudientes;
DROP TABLE IF EXISTS registros_disciplinarios;
DROP TABLE IF EXISTS estudiantes;
DROP TABLE IF EXISTS docentes;

CREATE TABLE docentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL DEFAULT 'Docente',
    rol VARCHAR(20) NOT NULL DEFAULT 'docente',
    correo VARCHAR(100) DEFAULT NULL,
    pregunta_seguridad VARCHAR(80) DEFAULT NULL,
    respuesta_seguridad_hash VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    numero_matricula VARCHAR(50) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estudiantes_activo (activo)
) ENGINE=InnoDB;

CREATE TABLE acudientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL DEFAULT '',
    parentesco VARCHAR(60) DEFAULT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    correo VARCHAR(150) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_acudiente_estudiante (estudiante_id),
    CONSTRAINT fk_acudiente_estudiante
        FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE registros_disciplinarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    docente_id INT NULL,
    faltas_tipo1 LONGTEXT NOT NULL,
    faltas_tipo2 LONGTEXT NOT NULL,
    faltas_tipo3 LONGTEXT NOT NULL,
    estimulos LONGTEXT NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_registros_estudiante (estudiante_id),
    INDEX idx_registros_docente (docente_id),
    CONSTRAINT fk_registro_estudiante
        FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_registro_docente
        FOREIGN KEY (docente_id) REFERENCES docentes(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notificaciones_acudiente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NULL,
    estudiante_id INT NOT NULL,
    acudiente_id INT NULL,
    correo_destino VARCHAR(150) DEFAULT NULL,
    asunto VARCHAR(255) NOT NULL,
    mensaje LONGTEXT NOT NULL,
    fecha_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notificacion_registro (registro_id),
    INDEX idx_notificacion_estudiante (estudiante_id),
    INDEX idx_notificacion_acudiente (acudiente_id),
    CONSTRAINT fk_notificacion_registro
        FOREIGN KEY (registro_id) REFERENCES registros_disciplinarios(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_notificacion_estudiante
        FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notificacion_acudiente
        FOREIGN KEY (acudiente_id) REFERENCES acudientes(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO docentes (usuario, password, nombre, apellido, rol, correo, activo)
SELECT
    'admin',
    '$2y$10$grrjr/tNLvezOzDUuwSBJOCy9zjHjTCHXD.aVYfMvQXK/3bSLpft.',
    'Administrador',
    'Principal',
    'administrador',
    'admin@ieaea.edu.co',
    1
WHERE NOT EXISTS (SELECT 1 FROM docentes LIMIT 1);

INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo)
SELECT seed.nombre, seed.apellido, seed.numero_matricula, seed.activo
FROM (
    SELECT 'MELANIE' AS nombre, 'ARIAS ALVAREZ' AS apellido, '2994' AS numero_matricula, 1 AS activo
    UNION ALL SELECT 'DYLAN ENMANUEL', 'BRICEÑO NUÑEZ', '2853', 1
    UNION ALL SELECT 'VALENTINA', 'CARMONA SANCHEZ', '220563', 1
    UNION ALL SELECT 'CHRISTOPHER JESUS', 'CASTILLO PAREJO', '2212', 1
    UNION ALL SELECT 'JUANITA SOFIA', 'CASTRO ROCHA', '2886', 1
    UNION ALL SELECT 'MATEO', 'COCHERO DE HOYOS', '2881', 1
    UNION ALL SELECT 'ANTHONY', 'DELGADO PINO', '2854', 1
    UNION ALL SELECT 'PAULINA', 'FLOREZ CARDONA', '221044', 1
    UNION ALL SELECT 'KRISTHOFER ALEXANDER', 'GORDONES ZERPA', '457', 1
    UNION ALL SELECT 'DANIEL', 'HENAO GOMEZ', '223068', 1
    UNION ALL SELECT 'MIGUEL ANGEL', 'HERNANDEZ ALVAREZ', '3188', 1
    UNION ALL SELECT 'JUAN CAMILO', 'JARAMILLO DAVID', '2903', 1
    UNION ALL SELECT 'JHOAN ENRIQUE', 'MARTINEZ OSORIO', '3069', 1
    UNION ALL SELECT 'NICOLAS', 'MARULANDA AVENDAÑO', '221547', 1
    UNION ALL SELECT 'MATHIAS', 'MIRANDA VANEGAS', '417', 1
    UNION ALL SELECT 'MARIANGEL', 'MONSALVE REINOSA', '2095', 1
    UNION ALL SELECT 'DANIELA', 'MORALES PEREZ', '221735', 1
    UNION ALL SELECT 'DARWIN', 'PLATA RODRIGUEZ', '1494', 1
    UNION ALL SELECT 'MIGUEL ANGEL', 'QUINTERO MENESES', '222204', 1
    UNION ALL SELECT 'RASHEL JOHANA', 'REYES FERNANDEZ', '1214', 1
    UNION ALL SELECT 'TAHIRA', 'RUEDA CANO', '2896', 1
    UNION ALL SELECT 'NICOLAS', 'SEPULVEDA ARBELAEZ', '222643', 1
    UNION ALL SELECT 'ALEJANDRO', 'SERNA ROJAS', '222533', 1
    UNION ALL SELECT 'ANTHONY', 'VILLA SANPEDRO', '222718', 1
    UNION ALL SELECT 'VALERIN', 'VILLEGAS CARDONA', '1754', 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM estudiantes LIMIT 1);
