<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bootstrap_db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizeText($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    // Corrige textos mojibake comunes (ej: BRICEÃ‘O -> BRICEÑO) al importar desde Excel/PowerShell.
    if (preg_match('/[ÃÂ]/u', $text) === 1) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (is_string($converted) && trim($converted) !== '') {
            $text = trim($converted);
        } else {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && trim($converted) !== '') {
                $text = trim($converted);
            }
        }
    }

    return $text;
}

function ensureConnectionAlive(mysqli $conn): mysqli
{
    if (@$conn->ping()) {
        return $conn;
    }

    return getDbConnection();
}

function normalizeComparisonKey(string $value): string
{
    $text = trim(mb_strtoupper($value, 'UTF-8'));
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if (is_string($converted) && $converted !== '') {
        $text = $converted;
    }

    $text = preg_replace('/[^A-Z0-9]+/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', (string) $text);

    return trim((string) $text);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El cuerpo JSON enviado no es válido.',
        ]);
    }

    return $decoded;
}

function isListArray(array $items): bool
{
    $index = 0;
    foreach ($items as $key => $_value) {
        if ($key !== $index) {
            return false;
        }
        $index++;
    }

    return true;
}

function normalizeStringArray($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $text = trim((string) $item);
        if ($text !== '') {
            $normalized[] = $text;
        }
    }

    return array_values(array_unique($normalized));
}

function normalizeFaltas($faltas): array
{
    $default = [
        'tipo1' => [],
        'tipo2' => [],
        'tipo3' => [],
    ];

    if (!is_array($faltas)) {
        return $default;
    }

    if (isListArray($faltas)) {
        $default['tipo1'] = normalizeStringArray($faltas);
        return $default;
    }

    foreach (['tipo1', 'tipo2', 'tipo3'] as $tipo) {
        if (array_key_exists($tipo, $faltas)) {
            $default[$tipo] = normalizeStringArray($faltas[$tipo]);
        }
    }

    return $default;
}

function encodeArrayAsJson(array $values): string
{
    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $json === false ? '[]' : $json;
}

function tableColumnExists(mysqli $conn, string $table, string $column): bool
{
    $cfg = getDbConfig();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $cfg['name'], $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function tableExists(mysqli $conn, string $table, string $schema = ''): bool
{
    $cfg = getDbConfig();

    $schemaName = $schema !== '' ? $schema : $cfg['name'];

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $schemaName, $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function getRequestAction(): string
{
    return normalizeText($_GET['action'] ?? '');
}

function getAcudientesPlanillaPath(): string
{
    $configured = normalizeText(getenv('ACUDIENTES_XLSX_PATH') ?: '');
    if ($configured !== '') {
        return $configured;
    }

    return 'C:\Users\Educacion IEGA\AppData\Roaming\Microsoft\Windows\Network Shortcuts\planilla acudiente.xlsx';
}

function columnLettersFromCellRef(string $ref): string
{
    if (preg_match('/^[A-Z]+/i', $ref, $matches) === 1) {
        return strtoupper($matches[0]);
    }

    return '';
}

function getCellTextValue(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) ($cell['t'] ?? '');

    if ($type === 's') {
        $index = (int) ($cell->v ?? -1);
        return $sharedStrings[$index] ?? '';
    }

    if ($type === 'inlineStr') {
        return normalizeText((string) ($cell->is->t ?? ''));
    }

    return normalizeText((string) ($cell->v ?? ''));
}

function readPlanillaRowsUsingPowerShell(string $xlsxPath): array
{
    $tempScript = tempnam(sys_get_temp_dir(), 'planilla_ps_');
    if ($tempScript === false) {
        throw new RuntimeException('No se pudo crear el script temporal para leer la planilla.');
    }

    $scriptPath = $tempScript . '.ps1';
    @rename($tempScript, $scriptPath);

    $psScript = <<<'POWERSHELL'
param([string]$XlsxPath)
$ErrorActionPreference = "Stop"

function Get-CellValue($cell, $sharedStrings) {
  $type = [string]$cell.t
  $value = [string]$cell.v
  if ($type -eq 's') {
    $idx = 0
    [void][int]::TryParse($value, [ref]$idx)
    if ($idx -ge 0 -and $idx -lt $sharedStrings.Count) { return [string]$sharedStrings[$idx] }
    return ''
  }
  if ($type -eq 'inlineStr') {
    return ([string]$cell.is.t).Trim()
  }
  return $value.Trim()
}

$tempDir = Join-Path $env:TEMP ("planilla_ps_" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $tempDir | Out-Null
try {
  $zipPath = Join-Path $tempDir "planilla.zip"
  Copy-Item -Path $XlsxPath -Destination $zipPath -Force

  $unzipDir = Join-Path $tempDir "unzipped"
  Expand-Archive -Path $zipPath -DestinationPath $unzipDir -Force

  $sheetPath = Join-Path $unzipDir "xl\\worksheets\\sheet1.xml"
  if (-not (Test-Path $sheetPath)) {
    throw "No se encontró xl/worksheets/sheet1.xml"
  }

  $sharedPath = Join-Path $unzipDir "xl\\sharedStrings.xml"
  $sharedStrings = @()
  if (Test-Path $sharedPath) {
    [xml]$ssXml = Get-Content -Path $sharedPath
    foreach ($si in $ssXml.sst.si) {
      if ($null -ne $si.t) {
        $sharedStrings += ([string]$si.t).Trim()
      } elseif ($null -ne $si.r) {
        $parts = @()
        foreach ($run in $si.r) { $parts += [string]$run.t }
        $sharedStrings += (($parts -join '')).Trim()
      } else {
        $sharedStrings += ''
      }
    }
  }

  [xml]$sheetXml = Get-Content -Path $sheetPath
  $rows = $sheetXml.worksheet.sheetData.row
  if ($rows.Count -lt 2) {
    "[]"
    exit 0
  }

  $headerMap = @{}
  foreach ($c in $rows[0].c) {
    $ref = [string]$c.r
    $col = ($ref -replace '\d', '')
    $header = (Get-CellValue $c $sharedStrings).Trim()
    if ($header -ne '') { $headerMap[$col] = $header }
  }

  $output = @()
  for ($i = 1; $i -lt $rows.Count; $i++) {
    $row = $rows[$i]
    $rowVals = @{}
    foreach ($c in $row.c) {
      $ref = [string]$c.r
      $col = ($ref -replace '\d', '')
      if ($headerMap.ContainsKey($col)) {
        $rowVals[$headerMap[$col]] = (Get-CellValue $c $sharedStrings).Trim()
      }
    }

    $hasData = $false
    foreach ($k in $rowVals.Keys) {
      if (-not [string]::IsNullOrWhiteSpace([string]$rowVals[$k])) { $hasData = $true; break }
    }
    if ($hasData) { $output += [pscustomobject]$rowVals }
  }

  $json = $output | ConvertTo-Json -Depth 8 -Compress
  $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
  [Convert]::ToBase64String($bytes)
} finally {
  if (Test-Path $tempDir) { Remove-Item -Path $tempDir -Recurse -Force -ErrorAction SilentlyContinue }
}
POWERSHELL;

    file_put_contents($scriptPath, $psScript);

    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($scriptPath)
        . ' -XlsxPath '
        . escapeshellarg($xlsxPath)
        . ' 2>&1';

    $output = shell_exec($cmd);
    @unlink($scriptPath);

    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('No se pudo leer la planilla con PowerShell.');
    }

    $rawOutput = trim($output);
    if (preg_match('/([A-Za-z0-9+\/=]{40,})/', $rawOutput, $matches) === 1) {
        $rawOutput = $matches[1];
    }

    $decodedJson = base64_decode($rawOutput, true);
    if (!is_string($decodedJson) || $decodedJson === '') {
        throw new RuntimeException('PowerShell no devolvió datos válidos de la planilla.');
    }

    $decoded = json_decode($decodedJson, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('PowerShell no devolvió un JSON válido para la planilla.');
    }

    if (!isListArray($decoded)) {
        $decoded = [$decoded];
    }

    $rows = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $record = [];
        foreach ($item as $key => $value) {
            $record[normalizeText((string) $key)] = normalizeText($value);
        }
        if (!empty($record)) {
            $rows[] = $record;
        }
    }

    return $rows;
}

function readPlanillaRowsFromXlsx(string $xlsxPath): array
{
    if (!is_file($xlsxPath) || !is_readable($xlsxPath)) {
        throw new RuntimeException('No se encontró la planilla de acudientes en la ruta configurada.');
    }

    if (!class_exists('ZipArchive')) {
        return readPlanillaRowsUsingPowerShell($xlsxPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath) !== true) {
        throw new RuntimeException('No se pudo abrir el archivo XLSX de acudientes.');
    }

    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!is_string($sheetXml) || trim($sheetXml) === '') {
        throw new RuntimeException('No se encontró la primera hoja en la planilla de acudientes.');
    }

    $sharedStrings = [];
    if (is_string($sharedStringsXml) && trim($sharedStringsXml) !== '') {
        $shared = @simplexml_load_string($sharedStringsXml);
        if ($shared !== false && isset($shared->si)) {
            foreach ($shared->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = normalizeText((string) $si->t);
                    continue;
                }

                if (isset($si->r)) {
                    $parts = [];
                    foreach ($si->r as $run) {
                        $parts[] = (string) ($run->t ?? '');
                    }
                    $sharedStrings[] = normalizeText(implode('', $parts));
                    continue;
                }

                $sharedStrings[] = '';
            }
        }
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        throw new RuntimeException('La hoja de la planilla no contiene datos válidos.');
    }

    $rows = [];
    $headersByColumn = [];

    foreach ($sheet->sheetData->row as $row) {
        $cells = [];

        foreach ($row->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $column = columnLettersFromCellRef($ref);
            if ($column === '') {
                continue;
            }
            $cells[$column] = getCellTextValue($cell, $sharedStrings);
        }

        if (empty($cells)) {
            continue;
        }

        if (empty($headersByColumn)) {
            foreach ($cells as $column => $value) {
                $header = normalizeText($value);
                if ($header !== '') {
                    $headersByColumn[$column] = $header;
                }
            }
            continue;
        }

        $record = [];
        foreach ($headersByColumn as $column => $header) {
            $record[$header] = normalizeText($cells[$column] ?? '');
        }

        if (implode('', $record) !== '') {
            $rows[] = $record;
        }
    }

    return $rows;
}

function buildStudentLookups(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT id, nombre, apellido, numero_matricula
         FROM estudiantes
         WHERE activo = 1'
    );

    if (!$result) {
        throw new RuntimeException('No se pudo consultar el listado de estudiantes para la importación.');
    }

    $byMatricula = [];
    $byName = [];
    $nameCandidates = [];

    while ($row = $result->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $matriculaKey = normalizeComparisonKey((string) ($row['numero_matricula'] ?? ''));
        if ($matriculaKey !== '') {
            $byMatricula[$matriculaKey] = $id;
        }

        $apellido = normalizeText((string) ($row['apellido'] ?? ''));
        $nombre = normalizeText((string) ($row['nombre'] ?? ''));

        $keys = [
            normalizeComparisonKey($apellido . ' ' . $nombre),
            normalizeComparisonKey($nombre . ' ' . $apellido),
        ];

        foreach ($keys as $key) {
            if ($key !== '') {
                $byName[$key] = $id;
                $nameCandidates[] = [
                    'id' => $id,
                    'key' => $key,
                ];
            }
        }
    }

    return [
        'matricula' => $byMatricula,
        'name' => $byName,
        'name_candidates' => $nameCandidates,
    ];
}

function findStudentIdByApproximateName(array $candidates, string $rowNameKey): int
{
    if ($rowNameKey === '' || empty($candidates)) {
        return 0;
    }

    $bestId = 0;
    $bestDistance = PHP_INT_MAX;
    $maxDistance = max(2, (int) floor(strlen($rowNameKey) * 0.18));

    foreach ($candidates as $candidate) {
        $candidateKey = (string) ($candidate['key'] ?? '');
        $candidateId = (int) ($candidate['id'] ?? 0);
        if ($candidateId <= 0 || $candidateKey === '') {
            continue;
        }

        $distance = levenshtein($rowNameKey, $candidateKey);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $bestId = $candidateId;
        }
    }

    if ($bestDistance <= $maxDistance) {
        return $bestId;
    }

    return 0;
}

function importarPlanillaAcudientes(mysqli $conn): void
{
    if (!tableExists($conn, 'acudientes')) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'La tabla acudientes no existe. Ejecuta el script de actualización de BD.',
        ]);
    }

    try {
        $rows = readPlanillaRowsFromXlsx(getAcudientesPlanillaPath());
        $lookups = buildStudentLookups($conn);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'success' => false,
            'error' => $exception->getMessage(),
        ]);
    }

    if (count($rows) === 0) {
        jsonResponse(200, [
            'success' => true,
            'message' => 'La planilla no contiene filas para importar.',
            'total' => 0,
            'guardados' => 0,
            'sin_estudiante' => 0,
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO acudientes (estudiante_id, nombre, parentesco, telefono, correo, direccion)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            parentesco = VALUES(parentesco),
            telefono = VALUES(telefono),
            correo = VALUES(correo),
            direccion = VALUES(direccion),
            updated_at = CURRENT_TIMESTAMP'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar la importación de acudientes.',
        ]);
    }

    $guardados = 0;
    $sinEstudiante = 0;

    foreach ($rows as $row) {
        $nombreAlumno = normalizeText($row['Nombre_alumno'] ?? '');
        $codMatricula = normalizeText($row['Cod_Matricula'] ?? '');
        $nombreAcudiente = normalizeText($row['Nombre_Acudiente'] ?? '');
        $telefono = normalizeText($row['Telefono_acudiente'] ?? '');
        $parentesco = normalizeText($row['Parentesco_acudiente_estudiante'] ?? '');
        $correo = normalizeText($row['Correo_electronico_padre'] ?? '');

        if ($nombreAcudiente === '') {
            continue;
        }

        $estudianteId = 0;
        $matriculaKey = normalizeComparisonKey($codMatricula);
        if ($matriculaKey !== '' && isset($lookups['matricula'][$matriculaKey])) {
            $estudianteId = (int) $lookups['matricula'][$matriculaKey];
        }

        if ($estudianteId <= 0) {
            $nameKey = normalizeComparisonKey($nombreAlumno);
            if ($nameKey !== '' && isset($lookups['name'][$nameKey])) {
                $estudianteId = (int) $lookups['name'][$nameKey];
            } elseif ($nameKey !== '') {
                $estudianteId = findStudentIdByApproximateName($lookups['name_candidates'] ?? [], $nameKey);
            }
        }

        if ($estudianteId <= 0) {
            $sinEstudiante++;
            continue;
        }

        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $correo = '';
        }

        $direccion = '';
        $stmt->bind_param('isssss', $estudianteId, $nombreAcudiente, $parentesco, $telefono, $correo, $direccion);
        if ($stmt->execute()) {
            $guardados++;
        }
    }

    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Planilla de acudientes importada correctamente.',
        'total' => count($rows),
        'guardados' => $guardados,
        'sin_estudiante' => $sinEstudiante,
    ]);
}

function obtenerEstudiantes(mysqli $conn): void
{
    $sql = 'SELECT id, nombre, apellido, numero_matricula, activo
            FROM estudiantes
            WHERE activo = 1
            ORDER BY apellido ASC, nombre ASC';

    $result = $conn->query($sql);
    if (!$result) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudieron cargar los estudiantes.',
        ]);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    jsonResponse(200, [
        'success' => true,
        'data' => $rows,
    ]);
}

function obtenerEstudiante(mysqli $conn): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar un ID de estudiante válido.',
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT id, nombre, apellido, numero_matricula
         FROM estudiantes
         WHERE id = ? AND activo = 1
         LIMIT 1'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar la consulta del estudiante.',
        ]);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$data) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'Estudiante no encontrado.',
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'data' => $data,
    ]);
}

function decodeJsonColumnArray($value): array
{
    if (!is_string($value) || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return [];
    }

    return normalizeStringArray($decoded);
}

function obtenerHistorialEstudiante(mysqli $conn): void
{
    $estudianteId = (int) ($_GET['estudiante_id'] ?? 0);
    if ($estudianteId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes indicar el estudiante para consultar el historial.',
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT r.id,
                r.faltas_tipo1,
                r.faltas_tipo2,
                r.faltas_tipo3,
                r.estimulos,
                r.fecha_registro,
                d.nombre AS docente_nombre
         FROM registros_disciplinarios r
         LEFT JOIN docentes d ON d.id = r.docente_id
         WHERE r.estudiante_id = ?
         ORDER BY r.fecha_registro DESC
         LIMIT 25'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar la consulta del historial disciplinario.',
        ]);
    }

    $stmt->bind_param('i', $estudianteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result ? $result->fetch_assoc() : null) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'faltas_tipo1' => decodeJsonColumnArray((string) ($row['faltas_tipo1'] ?? '')),
            'faltas_tipo2' => decodeJsonColumnArray((string) ($row['faltas_tipo2'] ?? '')),
            'faltas_tipo3' => decodeJsonColumnArray((string) ($row['faltas_tipo3'] ?? '')),
            'estimulos' => decodeJsonColumnArray((string) ($row['estimulos'] ?? '')),
            'fecha_registro' => (string) ($row['fecha_registro'] ?? ''),
            'docente_nombre' => trim((string) ($row['docente_nombre'] ?? '')),
        ];
    }

    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'data' => $rows,
    ]);
}

function estudianteActivoExiste(mysqli $conn, int $estudianteId): bool
{
    $stmt = $conn->prepare('SELECT id FROM estudiantes WHERE id = ? AND activo = 1 LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $estudianteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (bool) $row;
}

function fetchLegacyAcudiente(mysqli $conn, int $estudianteId): ?array
{
    $legacySchema = 'app_educativa';
    if (!tableExists($conn, 'acudientes', $legacySchema)) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT id, estudiante_id, nombre, parentesco, telefono, correo, direccion
         FROM `app_educativa`.acudientes
         WHERE estudiante_id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $estudianteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'estudiante_id' => (int) ($row['estudiante_id'] ?? 0),
        'nombre' => normalizeText($row['nombre'] ?? ''),
        'parentesco' => normalizeText($row['parentesco'] ?? ''),
        'telefono' => normalizeText($row['telefono'] ?? ''),
        'correo' => normalizeText($row['correo'] ?? ''),
        'direccion' => normalizeText($row['direccion'] ?? ''),
    ];
}

function obtenerAcudiente(mysqli $conn): void
{
    if (!tableExists($conn, 'acudientes')) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'La tabla acudientes no existe. Ejecuta el script de actualización de BD.',
        ]);
    }

    $estudianteId = (int) ($_GET['estudiante_id'] ?? 0);
    if ($estudianteId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar un estudiante_id válido.',
        ]);
    }

    if (!estudianteActivoExiste($conn, $estudianteId)) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El estudiante seleccionado no existe o está inactivo.',
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT id, estudiante_id, nombre, parentesco, telefono, correo, direccion
         FROM acudientes
         WHERE estudiante_id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo consultar el perfil del acudiente.',
        ]);
    }

    $stmt->bind_param('i', $estudianteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        $legacy = fetchLegacyAcudiente($conn, $estudianteId);
        jsonResponse(200, [
            'success' => true,
            'data' => $legacy,
            'hint' => $legacy ? 'Los datos provienen de la base antigua.' : null,
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'data' => $row,
    ]);
}

function guardarAcudiente(mysqli $conn, array $data): void
{
    if (!tableExists($conn, 'acudientes')) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'La tabla acudientes no existe. Ejecuta el script de actualización de BD.',
        ]);
    }

    $estudianteId = (int) ($data['estudiante_id'] ?? 0);
    $nombre = normalizeText($data['nombre'] ?? '');
    $parentesco = normalizeText($data['parentesco'] ?? '');
    $telefono = normalizeText($data['telefono'] ?? '');
    $correo = normalizeText($data['correo'] ?? '');
    $direccion = normalizeText($data['direccion'] ?? '');

    if ($estudianteId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar un estudiante válido para guardar acudiente.',
        ]);
    }

    if (!estudianteActivoExiste($conn, $estudianteId)) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El estudiante seleccionado no existe o está inactivo.',
        ]);
    }

    if ($nombre === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El nombre del acudiente es obligatorio.',
        ]);
    }

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El correo del acudiente no tiene un formato válido.',
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO acudientes (estudiante_id, nombre, parentesco, telefono, correo, direccion)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            parentesco = VALUES(parentesco),
            telefono = VALUES(telefono),
            correo = VALUES(correo),
            direccion = VALUES(direccion),
            updated_at = CURRENT_TIMESTAMP'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar el guardado del acudiente.',
        ]);
    }

    $stmt->bind_param('isssss', $estudianteId, $nombre, $parentesco, $telefono, $correo, $direccion);

    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo guardar el perfil del acudiente.',
        ]);
    }
    $stmt->close();

    $query = $conn->prepare('SELECT id FROM acudientes WHERE estudiante_id = ? LIMIT 1');
    if (!$query) {
        jsonResponse(200, [
            'success' => true,
            'message' => 'Perfil del acudiente guardado correctamente.',
            'id' => 0,
        ]);
    }

    $query->bind_param('i', $estudianteId);
    $query->execute();
    $result = $query->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $query->close();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Perfil del acudiente guardado correctamente.',
        'id' => (int) ($row['id'] ?? 0),
    ]);
}

function resolverAcudienteIdPorEstudiante(mysqli $conn, int $estudianteId): int
{
    if (!tableExists($conn, 'acudientes')) {
        return 0;
    }

    $lookup = $conn->prepare('SELECT id FROM acudientes WHERE estudiante_id = ? LIMIT 1');
    if (!$lookup) {
        return 0;
    }

    $lookup->bind_param('i', $estudianteId);
    $lookup->execute();
    $lookupResult = $lookup->get_result();
    $lookupRow = $lookupResult ? $lookupResult->fetch_assoc() : null;
    $lookup->close();

    return (int) ($lookupRow['id'] ?? 0);
}

function crearNotificacionAcudiente(
    mysqli $conn,
    int $registroId,
    int $estudianteId,
    string $correo,
    string $asunto,
    string $mensaje
): int {
    $acudienteId = resolverAcudienteIdPorEstudiante($conn, $estudianteId);

    $stmt = $conn->prepare(
        'INSERT INTO notificaciones_acudiente
         (registro_id, estudiante_id, acudiente_id, correo_destino, asunto, mensaje)
         VALUES (NULLIF(?, 0), ?, NULLIF(?, 0), ?, ?, ?)'
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el guardado de la notificación.');
    }

    $stmt->bind_param('iiisss', $registroId, $estudianteId, $acudienteId, $correo, $asunto, $mensaje);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No se pudo guardar la notificación del acudiente.');
    }

    $newId = (int) $conn->insert_id;
    $stmt->close();

    return $newId;
}

function guardarNotificacionAcudiente(mysqli $conn, array $data): void
{
    if (!tableExists($conn, 'notificaciones_acudiente')) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'La tabla notificaciones_acudiente no existe. Ejecuta el script de actualización de BD.',
        ]);
    }

    $estudianteId = (int) ($data['estudiante_id'] ?? 0);
    $registroId = (int) ($data['registro_id'] ?? 0);
    $asunto = normalizeText($data['asunto'] ?? '');
    $mensaje = normalizeText($data['mensaje'] ?? '');
    $correo = normalizeText($data['correo'] ?? '');

    if ($estudianteId <= 0 || $asunto === '' || $mensaje === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Para guardar la notificación debes enviar estudiante, asunto y mensaje.',
        ]);
    }

    if (!estudianteActivoExiste($conn, $estudianteId)) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El estudiante seleccionado no existe o está inactivo.',
        ]);
    }

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El correo del acudiente no tiene un formato válido.',
        ]);
    }

    try {
        $newId = crearNotificacionAcudiente($conn, $registroId, $estudianteId, $correo, $asunto, $mensaje);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'success' => false,
            'error' => $exception->getMessage(),
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'message' => 'Notificación del acudiente guardada correctamente.',
        'id' => $newId,
    ]);
}

function enviarCorreoAcudiente(mysqli $conn, array $data): void
{
    $estudianteId = (int) ($data['estudiante_id'] ?? 0);
    $registroId = (int) ($data['registro_id'] ?? 0);
    $correo = normalizeText($data['correo'] ?? '');
    $asunto = normalizeText($data['asunto'] ?? '');
    $mensaje = normalizeText($data['mensaje'] ?? '');

    if ($estudianteId <= 0 || $correo === '' || $asunto === '' || $mensaje === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar estudiante, correo, asunto y mensaje para enviar el email.',
        ]);
    }

    if (!estudianteActivoExiste($conn, $estudianteId)) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El estudiante seleccionado no existe o está inactivo.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El correo del acudiente no tiene un formato válido.',
        ]);
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    $from = normalizeText(getenv('MAIL_FROM') ?: '');
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . $from;
        $headers[] = 'Reply-To: ' . $from;
    }

    $ok = @mail($correo, $asunto, $mensaje, implode("\r\n", $headers));
    if (!$ok) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo enviar el correo desde el servidor. Revisa la configuración SMTP de PHP.',
        ]);
    }

    try {
        $notificacionId = crearNotificacionAcudiente($conn, $registroId, $estudianteId, $correo, $asunto, $mensaje);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'success' => false,
            'error' => $exception->getMessage(),
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Correo enviado y notificación guardada correctamente.',
        'id' => $notificacionId,
    ]);
}

function loginDocente(mysqli $conn, array $data): void
{
    $usuario = normalizeText($data['usuario'] ?? '');
    $contrasena = (string) ($data['contrasena'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Usuario y contraseña son obligatorios.',
        ]);
    }

    $query = 'SELECT id, usuario, password, nombre, apellido, rol FROM docentes WHERE usuario = ? AND activo = 1 LIMIT 1';
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        $stmt = $conn->prepare('SELECT id, usuario, password, nombre, apellido FROM docentes WHERE usuario = ? LIMIT 1');
    }

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo consultar la tabla de docentes.',
        ]);
    }

    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'Usuario o contraseña incorrectos.',
        ]);
    }

    $storedPassword = (string) $row['password'];
    $isValid = $storedPassword !== '' && (password_verify($contrasena, $storedPassword) || hash_equals($storedPassword, $contrasena));

    if (!$isValid) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'Usuario o contraseña incorrectos.',
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Ingreso exitoso.',
        'data' => [
            'id' => (int) $row['id'],
            'usuario' => $row['usuario'],
            'nombre' => $row['nombre'],
            'apellido' => (string) ($row['apellido'] ?? ''),
            'rol' => (string) ($row['rol'] ?? 'docente'),
        ],
    ]);
}

function crearDocente(mysqli $conn, array $data): void
{
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $usuario = normalizeText($data['usuario'] ?? '');
    $correo = normalizeText($data['correo'] ?? '');
    $contrasena = (string) ($data['contrasena'] ?? '');
    $rol = strtolower(trim((string) ($data['rol'] ?? 'docente')));

    $rolesValidos = ['docente', 'administrador'];

    if ($nombre === '' || $apellido === '' || $usuario === '' || $correo === '' || $contrasena === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Todos los campos son obligatorios.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Ingresa un correo electrónico válido.',
        ]);
    }

    if (!in_array($rol, $rolesValidos, true)) {
        $rol = 'docente';
    }

    $stmt = $conn->prepare('SELECT id FROM docentes WHERE usuario = ? LIMIT 1');
    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo validar el usuario.',
        ]);
    }

    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $exists = $stmt->get_result();
    $row = $exists ? $exists->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'El usuario ya existe.',
        ]);
    }

    $stmt = $conn->prepare('SELECT id FROM docentes WHERE correo = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $resultCorreo = $stmt->get_result();
        $correoExistente = $resultCorreo ? $resultCorreo->fetch_assoc() : null;
        $stmt->close();
        if ($correoExistente) {
            jsonResponse(409, [
                'success' => false,
                'error' => 'El correo electrónico ya está registrado.',
            ]);
        }
    }

    $hashedPassword = password_hash($contrasena, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo procesar la contraseña.',
        ]);
    }

    $insert = $conn->prepare(
        'INSERT INTO docentes (usuario, password, nombre, apellido, correo, rol, activo)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    if (!$insert) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo crear la cuenta.',
        ]);
    }

    $insert->bind_param('ssssss', $usuario, $hashedPassword, $nombre, $apellido, $correo, $rol);

    if (!$insert->execute()) {
        $code = $insert->errno;
        $insert->close();

        if ($code === 1062) {
            jsonResponse(409, [
                'success' => false,
                'error' => 'El usuario o correo ya están registrados.',
            ]);
        }

        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo crear la cuenta. Intenta de nuevo más tarde.',
        ]);
    }

    $insert->close();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Cuenta creada correctamente. Ya puedes ingresar.',
    ]);
}

function agregarEstudiante(mysqli $conn, array $data): void
{
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $matricula = strtoupper(normalizeText($data['numero_matricula'] ?? ''));

    if ($nombre === '' || $apellido === '' || $matricula === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Nombre, apellido y matrícula son obligatorios.',
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo)
         VALUES (?, ?, ?, 1)'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar el alta de estudiante.',
        ]);
    }

    $stmt->bind_param('sss', $nombre, $apellido, $matricula);

    if (!$stmt->execute()) {
        $code = $stmt->errno;
        $stmt->close();

        if ($code === 1062) {
            jsonResponse(409, [
                'success' => false,
                'error' => 'La matrícula ya existe.',
            ]);
        }

        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo guardar el estudiante.',
        ]);
    }

    $newId = $conn->insert_id;
    $stmt->close();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Estudiante agregado correctamente.',
        'id' => $newId,
    ]);
}

function actualizarEstudiante(mysqli $conn, array $data): void
{
    $id = (int) ($data['id'] ?? 0);
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $matricula = strtoupper(normalizeText($data['numero_matricula'] ?? ''));

    if ($id <= 0 || $nombre === '' || $apellido === '' || $matricula === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Datos incompletos para actualizar estudiante.',
        ]);
    }

    $stmt = $conn->prepare(
        'UPDATE estudiantes
         SET nombre = ?, apellido = ?, numero_matricula = ?
         WHERE id = ? AND activo = 1'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar la edición del estudiante.',
        ]);
    }

    $stmt->bind_param('sssi', $nombre, $apellido, $matricula, $id);

    if (!$stmt->execute()) {
        $code = $stmt->errno;
        $stmt->close();

        if ($code === 1062) {
            jsonResponse(409, [
                'success' => false,
                'error' => 'La matrícula ya existe.',
            ]);
        }

        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo actualizar el estudiante.',
        ]);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $check = $conn->prepare('SELECT id FROM estudiantes WHERE id = ? AND activo = 1 LIMIT 1');
        if ($check) {
            $check->bind_param('i', $id);
            $check->execute();
            $checkResult = $check->get_result();
            $exists = $checkResult ? $checkResult->fetch_assoc() : null;
            $check->close();

            if (!$exists) {
                jsonResponse(404, [
                    'success' => false,
                    'error' => 'El estudiante a editar no existe o está inactivo.',
                ]);
            }
        }
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Estudiante actualizado correctamente.',
    ]);
}

function eliminarEstudiante(mysqli $conn, array $data): void
{
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar un ID válido para eliminar.',
        ]);
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare('UPDATE estudiantes SET activo = 0 WHERE id = ? AND activo = 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la eliminación del estudiante.');
        }

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('No se pudo eliminar el estudiante.');
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $conn->rollback();
            jsonResponse(404, [
                'success' => false,
                'error' => 'El estudiante no existe o ya fue eliminado.',
            ]);
        }

        if (tableExists($conn, 'acudientes')) {
            $deleteAcudiente = $conn->prepare('DELETE FROM acudientes WHERE estudiante_id = ?');
            if ($deleteAcudiente) {
                $deleteAcudiente->bind_param('i', $id);
                $deleteAcudiente->execute();
                $deleteAcudiente->close();
            }
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        jsonResponse(500, [
            'success' => false,
            'error' => $exception->getMessage(),
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Estudiante y acudiente eliminados correctamente.',
    ]);
}

function guardarRegistro(mysqli $conn, array $data): void
{
    $estudianteId = (int) ($data['estudiante_id'] ?? 0);
    if ($estudianteId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes enviar un estudiante válido para guardar el registro.',
        ]);
    }

    $docenteId = (int) ($data['docente_id'] ?? 0);
    $faltas = normalizeFaltas($data['faltas'] ?? []);
    $estimulos = normalizeStringArray($data['estimulos'] ?? []);

    $check = $conn->prepare('SELECT id FROM estudiantes WHERE id = ? AND activo = 1 LIMIT 1');
    if (!$check) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo validar el estudiante antes de guardar.',
        ]);
    }

    $check->bind_param('i', $estudianteId);
    $check->execute();
    $checkResult = $check->get_result();
    $exists = $checkResult ? $checkResult->fetch_assoc() : null;
    $check->close();

    if (!$exists) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El estudiante seleccionado no existe o está inactivo.',
        ]);
    }

    $faltasTipo1 = encodeArrayAsJson($faltas['tipo1']);
    $faltasTipo2 = encodeArrayAsJson($faltas['tipo2']);
    $faltasTipo3 = encodeArrayAsJson($faltas['tipo3']);
    $estimulosJson = encodeArrayAsJson($estimulos);

    $hasTipo3 = tableColumnExists($conn, 'registros_disciplinarios', 'faltas_tipo3');
    $hasEstimulos = tableColumnExists($conn, 'registros_disciplinarios', 'estimulos');
    $hasEstimulosTilde = tableColumnExists($conn, 'registros_disciplinarios', 'estímulos');

    if (!$hasEstimulos && !$hasEstimulosTilde) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'La tabla de registros no tiene la columna de estímulos esperada.',
        ]);
    }

    $estimulosColumn = $hasEstimulos ? 'estimulos' : '`estímulos`';

    if ($hasTipo3) {
        $sql = "INSERT INTO registros_disciplinarios
                (estudiante_id, docente_id, faltas_tipo1, faltas_tipo2, faltas_tipo3, {$estimulosColumn})
                VALUES (?, NULLIF(?, 0), ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(500, [
                'success' => false,
                'error' => 'No se pudo preparar el guardado del registro disciplinario.',
            ]);
        }

        $stmt->bind_param(
            'iissss',
            $estudianteId,
            $docenteId,
            $faltasTipo1,
            $faltasTipo2,
            $faltasTipo3,
            $estimulosJson
        );
    } else {
        $faltasTipo2Compat = encodeArrayAsJson(array_merge($faltas['tipo2'], $faltas['tipo3']));

        $sql = "INSERT INTO registros_disciplinarios
                (estudiante_id, docente_id, faltas_tipo1, faltas_tipo2, {$estimulosColumn})
                VALUES (?, NULLIF(?, 0), ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(500, [
                'success' => false,
                'error' => 'No se pudo preparar el guardado del registro disciplinario.',
            ]);
        }

        $stmt->bind_param(
            'iisss',
            $estudianteId,
            $docenteId,
            $faltasTipo1,
            $faltasTipo2Compat,
            $estimulosJson
        );
    }

    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo guardar el registro disciplinario.',
        ]);
    }

    $newId = $conn->insert_id;
    $stmt->close();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Registro disciplinario guardado correctamente.',
        'id' => $newId,
    ]);
}

try {
    ensureDatabaseReady();
    $conn = getDbConnection();
    $conn = ensureConnectionAlive($conn);
} catch (Throwable $exception) {
    jsonResponse(500, [
        'success' => false,
        'error' => $exception->getMessage(),
        'hint' => 'Verifica que MySQL (XAMPP) esté iniciado y que la base exista.',
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = getRequestAction();

if ($method === 'GET') {
    switch ($action) {
        case 'test':
            $cfg = getDbConfig();
            jsonResponse(200, [
                'success' => true,
                'message' => 'API funcionando correctamente.',
                'database' => $cfg['name'],
            ]);
            break;

        case 'obtenerEstudiantes':
            obtenerEstudiantes($conn);
            break;

        case 'obtenerEstudiante':
            obtenerEstudiante($conn);
            break;

        case 'historialEstudiante':
            obtenerHistorialEstudiante($conn);
            break;

        case 'obtenerAcudiente':
            obtenerAcudiente($conn);
            break;

        default:
            jsonResponse(400, [
                'success' => false,
                'error' => 'Acción GET no válida.',
                'action' => $action,
            ]);
    }
}

if ($method === 'POST') {
    $payload = readJsonBody();

    switch ($action) {
        case 'login':
            loginDocente($conn, $payload);
            break;

        case 'agregarEstudiante':
            agregarEstudiante($conn, $payload);
            break;

        case 'actualizarEstudiante':
            actualizarEstudiante($conn, $payload);
            break;

        case 'eliminarEstudiante':
            eliminarEstudiante($conn, $payload);
            break;

        case 'guardarRegistro':
            guardarRegistro($conn, $payload);
            break;

        case 'guardarAcudiente':
            guardarAcudiente($conn, $payload);
            break;

        case 'crearDocente':
            crearDocente($conn, $payload);
            break;

        case 'importarPlanillaAcudientes':
            importarPlanillaAcudientes($conn);
            break;

        case 'guardarNotificacionAcudiente':
            guardarNotificacionAcudiente($conn, $payload);
            break;

        case 'enviarCorreoAcudiente':
            enviarCorreoAcudiente($conn, $payload);
            break;

        default:
            jsonResponse(400, [
                'success' => false,
                'error' => 'Acción POST no válida.',
                'action' => $action,
            ]);
    }
}

jsonResponse(405, [
    'success' => false,
    'error' => 'Método HTTP no permitido.',
]);
