<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bootstrap_db.php';

header('Content-Type: text/plain; charset=UTF-8');

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function normalizeImportText($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'Windows-1252');

    if (preg_match('/[ÃƒÃ‚]/u', $text) === 1) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (is_string($converted) && trim($converted) !== '') {
            $text = trim($converted);
        }
    }

    return preg_replace('/\s+/u', ' ', $text) ?: '';
}

function normalizeImportKey(string $value): string
{
    $value = normalizeImportText($value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    return trim($value);
}

function splitNameByHalf(string $fullName): array
{
    $fullName = normalizeImportText($fullName);
    if ($fullName === '') {
        return ['nombre' => '', 'apellido' => ''];
    }

    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    $count = count($parts);

    if ($count <= 1) {
        return ['nombre' => $fullName, 'apellido' => ''];
    }

    if ($count === 2) {
        return ['nombre' => $parts[0], 'apellido' => $parts[1]];
    }

    $splitIndex = (int) ceil($count / 2);

    return [
        'nombre' => implode(' ', array_slice($parts, 0, $splitIndex)),
        'apellido' => implode(' ', array_slice($parts, $splitIndex)),
    ];
}

function splitStudentNameSurnameFirst(string $fullName): array
{
    $fullName = normalizeImportText($fullName);
    if ($fullName === '') {
        return ['nombre' => '', 'apellido' => ''];
    }

    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    $count = count($parts);

    if ($count <= 1) {
        return ['nombre' => $fullName, 'apellido' => ''];
    }

    if ($count === 2) {
        return ['apellido' => $parts[0], 'nombre' => $parts[1]];
    }

    $splitIndex = (int) floor($count / 2);

    return [
        'apellido' => implode(' ', array_slice($parts, 0, $splitIndex)),
        'nombre' => implode(' ', array_slice($parts, $splitIndex)),
    ];
}

function getPlanoMatriculaPath(): string
{
    $envPath = getenv('ACUDIENTES_XLS_PATH') ?: '';
    if (is_string($envPath) && trim($envPath) !== '') {
        return trim($envPath);
    }

    return 'C:\Users\PC\Downloads\Plano_matricula_11-04-2026.xls';
}

function resolveSheetHtmlPath(string $xlsPath): string
{
    $dir = dirname($xlsPath);
    $base = pathinfo($xlsPath, PATHINFO_FILENAME);
    return $dir . DIRECTORY_SEPARATOR . $base . '_archivos' . DIRECTORY_SEPARATOR . 'sheet001.htm';
}

function parseHtmlTableRows(string $sheetPath): array
{
    if (!is_file($sheetPath) || !is_readable($sheetPath)) {
        throw new RuntimeException('No se encontró la hoja HTML exportada por Excel: ' . $sheetPath);
    }

    $html = file_get_contents($sheetPath);
    if (!is_string($html) || trim($html) === '') {
        throw new RuntimeException('No se pudo leer el contenido de la hoja HTML.');
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="Windows-1252">' . $html);
    if ($loaded === false) {
        throw new RuntimeException('No se pudo interpretar la hoja HTML del archivo XLS.');
    }

    $tables = $dom->getElementsByTagName('table');
    if ($tables->length === 0) {
        throw new RuntimeException('La hoja HTML no contiene ninguna tabla para importar.');
    }

    $table = $tables->item(0);
    $rows = [];
    $headers = [];

    foreach ($table->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if ($tag !== 'td' && $tag !== 'th') {
                continue;
            }

            $cells[] = normalizeImportText($node->textContent);
        }

        if (empty($cells)) {
            continue;
        }

        if (empty($headers)) {
            $headers = $cells;
            continue;
        }

        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = $cells[$index] ?? '';
        }

        if (implode('', $row) !== '') {
            $rows[] = $row;
        }
    }

    return $rows;
}

function buildExistingStudentLookup(mysqli $conn): array
{
    $result = $conn->query('SELECT id, nombre, apellido, numero_matricula FROM estudiantes');
    if (!$result) {
        throw new RuntimeException('No se pudo consultar la tabla de estudiantes.');
    }

    $byId = [];
    $byMatricula = [];
    $byName = [];

    while ($row = $result->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $nombre = normalizeImportText($row['nombre'] ?? '');
        $apellido = normalizeImportText($row['apellido'] ?? '');
        $matricula = normalizeImportText($row['numero_matricula'] ?? '');

        $byId[$id] = [
            'id' => $id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'numero_matricula' => $matricula,
        ];

        if ($matricula !== '') {
            $byMatricula[normalizeImportKey($matricula)] = $id;
        }

        foreach ([
            normalizeImportKey($apellido . ' ' . $nombre),
            normalizeImportKey($nombre . ' ' . $apellido),
        ] as $key) {
            if ($key !== '') {
                $byName[$key] = $id;
            }
        }
    }

    return [
        'by_id' => $byId,
        'by_matricula' => $byMatricula,
        'by_name' => $byName,
    ];
}

function resolveStudentData(array $lookup, string $nombreAlumno, string $matricula): array
{
    $matriculaKey = normalizeImportKey($matricula);
    if ($matriculaKey !== '' && isset($lookup['by_matricula'][$matriculaKey])) {
        $studentId = (int) $lookup['by_matricula'][$matriculaKey];
        return $lookup['by_id'][$studentId];
    }

    $nameKey = normalizeImportKey($nombreAlumno);
    if ($nameKey !== '' && isset($lookup['by_name'][$nameKey])) {
        $studentId = (int) $lookup['by_name'][$nameKey];
        return $lookup['by_id'][$studentId];
    }

    return splitStudentNameSurnameFirst($nombreAlumno) + [
        'id' => 0,
        'numero_matricula' => '',
    ];
}

function refreshStudentLookupEntry(array &$lookup, int $id, string $nombre, string $apellido, string $matricula): void
{
    $lookup['by_id'][$id] = [
        'id' => $id,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'numero_matricula' => $matricula,
    ];

    if ($matricula !== '') {
        $lookup['by_matricula'][normalizeImportKey($matricula)] = $id;
    }

    foreach ([
        normalizeImportKey($apellido . ' ' . $nombre),
        normalizeImportKey($nombre . ' ' . $apellido),
    ] as $key) {
        if ($key !== '') {
            $lookup['by_name'][$key] = $id;
        }
    }
}

function ensureImportSchema(mysqli $conn): void
{
    $conn->query("ALTER TABLE acudientes ADD COLUMN IF NOT EXISTS apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre");
}

function runImport(mysqli $conn, array $rows): array
{
    $lookup = buildExistingStudentLookup($conn);

    $insertStudent = $conn->prepare(
        'INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo)
         VALUES (?, ?, ?, 1)'
    );
    $updateStudent = $conn->prepare(
        'UPDATE estudiantes
         SET nombre = ?, apellido = ?, numero_matricula = ?, activo = 1
         WHERE id = ?'
    );
    $upsertGuardian = $conn->prepare(
        'INSERT INTO acudientes (estudiante_id, nombre, apellido, parentesco, telefono, correo, direccion)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            apellido = VALUES(apellido),
            parentesco = VALUES(parentesco),
            telefono = VALUES(telefono),
            correo = VALUES(correo),
            direccion = VALUES(direccion),
            updated_at = CURRENT_TIMESTAMP'
    );

    if (!$insertStudent || !$updateStudent || !$upsertGuardian) {
        throw new RuntimeException('No se pudieron preparar las sentencias de importación.');
    }

    $stats = [
        'rows' => 0,
        'students_inserted' => 0,
        'students_updated' => 0,
        'guardians_upserted' => 0,
        'skipped' => 0,
    ];

    $conn->begin_transaction();

    try {
        foreach ($rows as $row) {
            $nombreAlumno = normalizeImportText($row['Nombre_alumno'] ?? '');
            $matricula = normalizeImportText($row['Cod_Matricula'] ?? '');
            $nombreAcudiente = normalizeImportText($row['Nombre_Acudiente'] ?? '');
            $telefono = normalizeImportText($row['Telefono_acudiente'] ?? '');
            $parentesco = normalizeImportText($row['Parentesco_acudiente_estudiante'] ?? '');
            $correo = strtolower(normalizeImportText($row['Correo_electronico_padre'] ?? ''));

            if ($nombreAlumno === '' || $matricula === '') {
                $stats['skipped']++;
                continue;
            }

            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $correo = '';
            }

            $studentData = resolveStudentData($lookup, $nombreAlumno, $matricula);
            $studentId = (int) ($studentData['id'] ?? 0);
            $nombre = normalizeImportText($studentData['nombre'] ?? '');
            $apellido = normalizeImportText($studentData['apellido'] ?? '');

            if ($nombre === '' || $apellido === '') {
                $fallback = splitStudentNameSurnameFirst($nombreAlumno);
                $nombre = $nombre !== '' ? $nombre : $fallback['nombre'];
                $apellido = $apellido !== '' ? $apellido : $fallback['apellido'];
            }

            if ($studentId > 0) {
                $updateStudent->bind_param('sssi', $nombre, $apellido, $matricula, $studentId);
                $updateStudent->execute();
                $stats['students_updated']++;
            } else {
                $insertStudent->bind_param('sss', $nombre, $apellido, $matricula);
                $insertStudent->execute();
                $studentId = (int) $conn->insert_id;
                $stats['students_inserted']++;
            }

            refreshStudentLookupEntry($lookup, $studentId, $nombre, $apellido, $matricula);

            if ($nombreAcudiente !== '') {
                $guardianData = splitNameByHalf($nombreAcudiente);
                $direccion = '';
                $upsertGuardian->bind_param(
                    'issssss',
                    $studentId,
                    $guardianData['nombre'],
                    $guardianData['apellido'],
                    $parentesco,
                    $telefono,
                    $correo,
                    $direccion
                );
                $upsertGuardian->execute();
                $stats['guardians_upserted']++;
            }

            $stats['rows']++;
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    } finally {
        $insertStudent->close();
        $updateStudent->close();
        $upsertGuardian->close();
    }

    return $stats;
}

try {
    ensureDatabaseReady();
    $conn = conectarDB();
    ensureImportSchema($conn);

    $xlsPath = getPlanoMatriculaPath();
    $sheetPath = resolveSheetHtmlPath($xlsPath);

    out('Archivo XLS: ' . $xlsPath);
    out('Hoja HTML detectada: ' . $sheetPath);

    $rows = parseHtmlTableRows($sheetPath);
    if (count($rows) === 0) {
        throw new RuntimeException('No se encontraron filas útiles para importar.');
    }

    $stats = runImport($conn, $rows);

    out('Importación completada.');
    out('Filas procesadas: ' . $stats['rows']);
    out('Estudiantes insertados: ' . $stats['students_inserted']);
    out('Estudiantes actualizados: ' . $stats['students_updated']);
    out('Acudientes guardados/actualizados: ' . $stats['guardians_upserted']);
    out('Filas omitidas: ' . $stats['skipped']);
} catch (Throwable $exception) {
    http_response_code(500);
    out('Error: ' . $exception->getMessage());
    exit(1);
}
