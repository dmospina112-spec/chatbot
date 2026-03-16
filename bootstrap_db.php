<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Crea la base y tablas mínimas si aún no existen.
 * Es idempotente: puede ejecutarse muchas veces sin duplicar datos.
 */
function ensureDatabaseReady(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $cfg = getDbConfig();

    mysqli_report(MYSQLI_REPORT_OFF);
    $serverConn = @new mysqli(
        $cfg['host'],
        $cfg['user'],
        $cfg['pass'],
        '',
        (int) $cfg['port']
    );

    if ($serverConn->connect_error) {
        throw new RuntimeException('No se pudo conectar al servidor MySQL para inicializar: ' . $serverConn->connect_error);
    }

    if (!$serverConn->set_charset($cfg['charset'])) {
        $serverConn->close();
        throw new RuntimeException('No se pudo configurar charset durante la inicializacion.');
    }

    $dbName = str_replace('`', '``', $cfg['name']);

    $createDbSql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$serverConn->query($createDbSql)) {
        $error = $serverConn->error;
        $serverConn->close();
        throw new RuntimeException('No se pudo crear/verificar la base de datos: ' . $error);
    }

    if (!$serverConn->select_db($cfg['name'])) {
        $error = $serverConn->error;
        $serverConn->close();
        throw new RuntimeException('No se pudo seleccionar la base de datos: ' . $error);
    }

    $schemaStatements = [
        "CREATE TABLE IF NOT EXISTS docentes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL DEFAULT 'Docente',
            rol VARCHAR(20) NOT NULL DEFAULT 'docente',
            correo VARCHAR(100) DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS estudiantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            numero_matricula VARCHAR(50) NOT NULL UNIQUE,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estudiantes_activo (activo)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS acudientes (
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
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS registros_disciplinarios (
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
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS notificaciones_acudiente (
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
        ) ENGINE=InnoDB",
        "INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo)
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
         WHERE NOT EXISTS (SELECT 1 FROM estudiantes LIMIT 1)",
    ];

    foreach ($schemaStatements as $sql) {
        if (!$serverConn->query($sql)) {
            $error = $serverConn->error;
            $serverConn->close();
            throw new RuntimeException('Error inicializando estructura de base de datos: ' . $error);
        }
    }

    $passwordColumnCheck = $serverConn->query("SHOW COLUMNS FROM docentes LIKE 'password'");
    $contrasenaColumnCheck = $serverConn->query("SHOW COLUMNS FROM docentes LIKE 'contrasena'");
    if (
        (!$passwordColumnCheck || $passwordColumnCheck->num_rows === 0)
        && $contrasenaColumnCheck
        && $contrasenaColumnCheck->num_rows > 0
    ) {
        $serverConn->query("ALTER TABLE docentes CHANGE COLUMN contrasena password VARCHAR(255) NOT NULL");
    }

    $apellidoColumnCheck = $serverConn->query("SHOW COLUMNS FROM docentes LIKE 'apellido'");
    if (!$apellidoColumnCheck || $apellidoColumnCheck->num_rows === 0) {
        $serverConn->query("ALTER TABLE docentes ADD COLUMN apellido VARCHAR(100) NOT NULL DEFAULT 'Docente' AFTER nombre");
    }

    $countResult = $serverConn->query('SELECT COUNT(*) AS total FROM docentes');
    $totalDocentes = 0;
    if ($countResult) {
        $totalDocentes = (int) ($countResult->fetch_assoc()['total'] ?? 0);
        $countResult->free();
    }

    if ($totalDocentes === 0) {
        $defaultHash = password_hash('1234', PASSWORD_DEFAULT);
        if ($defaultHash === false) {
            $serverConn->close();
            throw new RuntimeException('No se pudo generar la contraseña por defecto del administrador.');
        }

        $insertStmt = $serverConn->prepare(
            'INSERT INTO docentes (usuario, password, nombre, apellido, rol, correo, activo)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );

        if (!$insertStmt) {
            $serverConn->close();
            throw new RuntimeException('No se pudo preparar la creación del administrador por defecto.');
        }

        $usuario = 'admin';
        $nombre = 'Administrador';
        $apellido = 'Principal';
        $rol = 'administrador';
        $correo = 'admin@ieaea.edu.co';

        $insertStmt->bind_param('ssssss', $usuario, $defaultHash, $nombre, $apellido, $rol, $correo);
        $insertStmt->execute();
        $insertStmt->close();
    }

    $roleColumnCheck = $serverConn->query("SHOW COLUMNS FROM docentes LIKE 'rol'");
    if ($roleColumnCheck && $roleColumnCheck->num_rows === 0) {
        $serverConn->query("ALTER TABLE docentes ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'docente' AFTER correo");
    }

    $serverConn->close();
    $initialized = true;
}
