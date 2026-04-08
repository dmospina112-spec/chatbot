-- =====================================================
-- Base de datos nueva para XAMPP/phpMyAdmin
-- Nombre: app_educativa_xampp
-- Si existe la BD antigua "app_educativa", migra sus datos.
-- =====================================================

CREATE DATABASE IF NOT EXISTS app_educativa_xampp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE app_educativa_xampp;

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
    nombre VARCHAR(150) NOT NULL,
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

DELIMITER $$

DROP PROCEDURE IF EXISTS cargar_datos_iniciales $$
CREATE PROCEDURE cargar_datos_iniciales()
BEGIN
    DECLARE source_db_exists INT DEFAULT 0;
    DECLARE source_docentes_exists INT DEFAULT 0;
    DECLARE source_estudiantes_exists INT DEFAULT 0;
    DECLARE source_registros_exists INT DEFAULT 0;
    DECLARE col_estimulos INT DEFAULT 0;
    DECLARE col_estimulos_tilde INT DEFAULT 0;
    DECLARE col_docentes_rol INT DEFAULT 0;

    SELECT COUNT(*) INTO source_db_exists
    FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME = 'app_educativa';

    IF source_db_exists > 0 THEN
        SELECT COUNT(*) INTO source_docentes_exists
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'app_educativa'
          AND TABLE_NAME = 'docentes';

        SELECT COUNT(*) INTO col_docentes_rol
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'app_educativa'
          AND TABLE_NAME = 'docentes'
          AND COLUMN_NAME = 'rol';

        SELECT COUNT(*) INTO source_estudiantes_exists
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'app_educativa'
          AND TABLE_NAME = 'estudiantes';

        SELECT COUNT(*) INTO source_registros_exists
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'app_educativa'
          AND TABLE_NAME = 'registros_disciplinarios';

        IF source_docentes_exists > 0 THEN
            IF col_docentes_rol > 0 THEN
            INSERT INTO docentes (id, usuario, password, nombre, apellido, rol, correo, activo)
            SELECT
                d.id,
                d.usuario,
                d.contrasena,
                COALESCE(NULLIF(d.nombre, ''), 'Administrador'),
                'Docente',
                COALESCE(NULLIF(d.rol, ''), 'docente'),
                d.correo,
                1
            FROM app_educativa.docentes AS d;
            ELSE
            INSERT INTO docentes (id, usuario, password, nombre, apellido, rol, correo, activo)
            SELECT
                d.id,
                d.usuario,
                d.contrasena,
                COALESCE(NULLIF(d.nombre, ''), 'Administrador'),
                'Docente',
                'docente',
                d.correo,
                1
            FROM app_educativa.docentes AS d;
            END IF;
        END IF;

        IF source_estudiantes_exists > 0 THEN
            INSERT INTO estudiantes (id, nombre, apellido, numero_matricula, activo, fecha_registro)
            SELECT
                e.id,
                e.nombre,
                e.apellido,
                e.numero_matricula,
                COALESCE(e.activo, 1),
                COALESCE(e.fecha_registro, CURRENT_TIMESTAMP)
            FROM app_educativa.estudiantes AS e;
        END IF;

        IF source_registros_exists > 0 THEN
            SELECT COUNT(*) INTO col_estimulos
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'app_educativa'
              AND TABLE_NAME = 'registros_disciplinarios'
              AND COLUMN_NAME = 'estimulos';

            SELECT COUNT(*) INTO col_estimulos_tilde
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'app_educativa'
              AND TABLE_NAME = 'registros_disciplinarios'
              AND COLUMN_NAME = 'estÃ­mulos';

            IF col_estimulos > 0 THEN
                SET @sql_registros = '
                    INSERT INTO app_educativa_xampp.registros_disciplinarios
                    (id, estudiante_id, docente_id, faltas_tipo1, faltas_tipo2, faltas_tipo3, estimulos, fecha_registro)
                    SELECT
                        r.id,
                        r.estudiante_id,
                        r.docente_id,
                        COALESCE(r.faltas_tipo1, ''[]''),
                        COALESCE(r.faltas_tipo2, ''[]''),
                        ''[]'',
                        COALESCE(r.estimulos, ''[]''),
                        COALESCE(r.fecha_registro, CURRENT_TIMESTAMP)
                    FROM app_educativa.registros_disciplinarios AS r
                ';
            ELSEIF col_estimulos_tilde > 0 THEN
                SET @sql_registros = '
                    INSERT INTO app_educativa_xampp.registros_disciplinarios
                    (id, estudiante_id, docente_id, faltas_tipo1, faltas_tipo2, faltas_tipo3, estimulos, fecha_registro)
                    SELECT
                        r.id,
                        r.estudiante_id,
                        r.docente_id,
                        COALESCE(r.faltas_tipo1, ''[]''),
                        COALESCE(r.faltas_tipo2, ''[]''),
                        ''[]'',
                        COALESCE(r.`estÃ­mulos`, ''[]''),
                        COALESCE(r.fecha_registro, CURRENT_TIMESTAMP)
                    FROM app_educativa.registros_disciplinarios AS r
                ';
            ELSE
                SET @sql_registros = '
                    INSERT INTO app_educativa_xampp.registros_disciplinarios
                    (id, estudiante_id, docente_id, faltas_tipo1, faltas_tipo2, faltas_tipo3, estimulos, fecha_registro)
                    SELECT
                        r.id,
                        r.estudiante_id,
                        r.docente_id,
                        COALESCE(r.faltas_tipo1, ''[]''),
                        COALESCE(r.faltas_tipo2, ''[]''),
                        ''[]'',
                        ''[]'',
                        COALESCE(r.fecha_registro, CURRENT_TIMESTAMP)
                    FROM app_educativa.registros_disciplinarios AS r
                ';
            END IF;

            PREPARE stmt_registros FROM @sql_registros;
            EXECUTE stmt_registros;
            DEALLOCATE PREPARE stmt_registros;
        END IF;
    END IF;

    IF (SELECT COUNT(*) FROM docentes) = 0 THEN
        INSERT INTO docentes (usuario, password, nombre, apellido, rol, correo, activo)
        VALUES (
            'admin',
            '$2y$10$grrjr/tNLvezOzDUuwSBJOCy9zjHjTCHXD.aVYfMvQXK/3bSLpft.',
            'Administrador',
            'Principal',
            'administrador',
            'admin@ieaea.edu.co',
            1
        );
    END IF;

    IF (SELECT COUNT(*) FROM estudiantes) = 0 THEN
        INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo) VALUES
        ('MELANIE', 'ARIAS ALVAREZ', '2994', 1),
        ('DYLAN ENMANUEL', 'BRICEÑO NUÑEZ', '2853', 1),
        ('VALENTINA', 'CARMONA SANCHEZ', '220563', 1),
        ('CHRISTOPHER JESUS', 'CASTILLO PAREJO', '2212', 1),
        ('JUANITA SOFIA', 'CASTRO ROCHA', '2886', 1),
        ('MATEO', 'COCHERO DE HOYOS', '2881', 1),
        ('ANTHONY', 'DELGADO PINO', '2854', 1),
        ('PAULINA', 'FLOREZ CARDONA', '221044', 1),
        ('KRISTHOFER ALEXANDER', 'GORDONES ZERPA', '457', 1),
        ('DANIEL', 'HENAO GOMEZ', '223068', 1),
        ('MIGUEL ANGEL', 'HERNANDEZ ALVAREZ', '3188', 1),
        ('JUAN CAMILO', 'JARAMILLO DAVID', '2903', 1),
        ('JHOAN ENRIQUE', 'MARTINEZ OSORIO', '3069', 1),
        ('NICOLAS', 'MARULANDA AVENDAÑO', '221547', 1),
        ('MATHIAS', 'MIRANDA VANEGAS', '417', 1),
        ('MARIANGEL', 'MONSALVE REINOSA', '2095', 1),
        ('DANIELA', 'MORALES PEREZ', '221735', 1),
        ('DARWIN', 'PLATA RODRIGUEZ', '1494', 1),
        ('MIGUEL ANGEL', 'QUINTERO MENESES', '222204', 1),
        ('RASHEL JOHANA', 'REYES FERNANDEZ', '1214', 1),
        ('TAHIRA', 'RUEDA CANO', '2896', 1),
        ('NICOLAS', 'SEPULVEDA ARBELAEZ', '222643', 1),
        ('ALEJANDRO', 'SERNA ROJAS', '222533', 1),
        ('ANTHONY', 'VILLA SANPEDRO', '222718', 1),
        ('VALERIN', 'VILLEGAS CARDONA', '1754', 1);
    END IF;
END $$

DELIMITER ;

CALL cargar_datos_iniciales();
DROP PROCEDURE cargar_datos_iniciales;

