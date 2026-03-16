<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bootstrap_db.php';

mysqli_report(MYSQLI_REPORT_OFF);

$bootstrapOk = true;
$bootstrapMessage = 'Inicializacion correcta';

try {
    ensureDatabaseReady();
} catch (Throwable $e) {
    $bootstrapOk = false;
    $bootstrapMessage = $e->getMessage();
}

$cfg = getDbConfig();
$dbName = $cfg['name'];

$checks = [];
$connServer = null;
$connDb = null;

$checks[] = [
    'label' => 'Version de PHP',
    'ok' => true,
    'value' => phpversion(),
];

$hasMysqli = extension_loaded('mysqli');
$checks[] = [
    'label' => 'Extension MySQLi',
    'ok' => $hasMysqli,
    'value' => $hasMysqli ? 'Instalada' : 'No instalada',
];

$checks[] = [
    'label' => 'Inicializacion automatica de BD',
    'ok' => $bootstrapOk,
    'value' => $bootstrapMessage,
];

if ($hasMysqli) {
    $connServer = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], '', $cfg['port']);

    $checks[] = [
        'label' => 'Conexion MySQL',
        'ok' => !$connServer->connect_error,
        'value' => $connServer->connect_error
            ? $connServer->connect_error
            : "Conectado a {$cfg['host']}:{$cfg['port']}",
    ];

    if (!$connServer->connect_error) {
        $dbExists = $connServer->select_db($dbName);
        $checks[] = [
            'label' => "Base de datos {$dbName}",
            'ok' => $dbExists,
            'value' => $dbExists ? 'Existe' : 'No existe. Ejecuta database.sql',
        ];

        if ($dbExists) {
            $connDb = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $dbName, $cfg['port']);
            if (!$connDb->connect_error) {
                $connDb->set_charset('utf8mb4');

                foreach (['docentes', 'estudiantes', 'registros_disciplinarios', 'acudientes', 'notificaciones_acudiente'] as $table) {
                    $result = $connDb->query("SHOW TABLES LIKE '{$table}'");
                    $exists = $result && $result->num_rows > 0;

                    $checks[] = [
                        'label' => "Tabla {$table}",
                        'ok' => $exists,
                        'value' => $exists ? 'Existe' : 'No existe',
                    ];
                }

                $resultStudents = $connDb->query('SELECT COUNT(*) AS total FROM estudiantes WHERE activo = 1');
                $totalStudents = $resultStudents ? (int) $resultStudents->fetch_assoc()['total'] : 0;

                $checks[] = [
                    'label' => 'Estudiantes activos',
                    'ok' => $totalStudents > 0,
                    'value' => (string) $totalStudents,
                ];

                $resultDocentes = $connDb->query('SELECT COUNT(*) AS total FROM docentes WHERE activo = 1');
                $totalDocentes = $resultDocentes ? (int) $resultDocentes->fetch_assoc()['total'] : 0;

                $checks[] = [
                    'label' => 'Docentes activos',
                    'ok' => $totalDocentes > 0,
                    'value' => (string) $totalDocentes,
                ];
            } else {
                $checks[] = [
                    'label' => "Conexion a {$dbName}",
                    'ok' => false,
                    'value' => $connDb->connect_error,
                ];
            }
        }
    }
}

$hasIndexPhp = file_exists(__DIR__ . '/index.php');
$hasIndexHtml = file_exists(__DIR__ . '/index.html');
$checks[] = [
    'label' => 'Archivo principal (index.php/index.html)',
    'ok' => $hasIndexPhp || $hasIndexHtml,
    'value' => $hasIndexPhp
        ? 'Existe index.php'
        : ($hasIndexHtml ? 'Existe index.html' : 'No existe'),
];

$checks[] = [
    'label' => 'Archivo api.php',
    'ok' => file_exists(__DIR__ . '/api.php'),
    'value' => file_exists(__DIR__ . '/api.php') ? 'Existe' : 'No existe',
];

$checks[] = [
    'label' => 'Archivo js/estudiantes.js',
    'ok' => file_exists(__DIR__ . '/js/estudiantes.js'),
    'value' => file_exists(__DIR__ . '/js/estudiantes.js') ? 'Existe' : 'No existe',
];

$requiredFolders = ['js', 'styles', 'chatbot', 'img'];
$missingFolders = array_values(array_filter($requiredFolders, static fn($folder) => !is_dir(__DIR__ . '/' . $folder)));

$checks[] = [
    'label' => 'Estructura de carpetas',
    'ok' => count($missingFolders) === 0,
    'value' => count($missingFolders) === 0
        ? 'Todas las carpetas requeridas existen'
        : 'Faltan: ' . implode(', ', $missingFolders),
];

if ($connDb instanceof mysqli) {
    try {
        $connDb->close();
    } catch (Throwable $_) {
    }
}

if ($connServer instanceof mysqli) {
    $sameConnection = $connDb instanceof mysqli
        && spl_object_id($connDb) === spl_object_id($connServer);

    if (!$sameConnection) {
        try {
            $connServer->close();
        } catch (Throwable $_) {
        }
    }
}

$allOk = !in_array(false, array_column($checks, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificacion del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .ok { color: #198754; font-weight: 700; }
        .fail { color: #dc3545; font-weight: 700; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-2">Verificacion del Sistema</h1>
        <p class="text-muted">Resultado de validacion para ejecucion en XAMPP.</p>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-primary text-white">Estado general</div>
            <div class="card-body">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Verificacion</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td class="<?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['ok'] ? 'OK' : 'ERROR' ?></td>
                            <td><?= htmlspecialchars($check['value'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert <?= $allOk ? 'alert-success' : 'alert-warning' ?> mt-4">
            <?php if ($allOk): ?>
                Todo esta listo. Puedes abrir <a href="index.php" class="alert-link">index.php</a>.
            <?php else: ?>
                Corrige los puntos marcados como ERROR y vuelve a cargar esta pagina.
            <?php endif; ?>
        </div>

        <div class="alert alert-info">
            <h6 class="mb-2">Config actual</h6>
            <div><strong>DB:</strong> <?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Host:</strong> <?= htmlspecialchars($cfg['host'], ENT_QUOTES, 'UTF-8') ?>:<?= (int) $cfg['port'] ?></div>
            <div><strong>Usuario:</strong> <?= htmlspecialchars($cfg['user'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</body>
</html>
