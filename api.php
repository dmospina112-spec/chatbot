<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bootstrap_db.php';

ini_set('display_errors', '0');
ini_set('html_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!extension_loaded('mysqli')) {
    jsonResponse(500, [
        'success' => false,
        'error' => 'La extensión mysqli no está habilitada en PHP. Inicia la app con el PHP de XAMPP o activa mysqli en tu entorno actual.',
    ]);
}

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

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

function normalizeEmailAddress($value): string
{
    return strtolower(trim((string) $value));
}

function encodeMimeHeaderValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function formatMailboxHeader(string $email, string $name = ''): string
{
    if ($name === '') {
        return $email;
    }

    return encodeMimeHeaderValue($name) . " <{$email}>";
}

function getMailTransportConfig(): array
{
    $from = normalizeEmailAddress(getenv('MAIL_FROM') ?: '');
    $fromName = normalizeText(getenv('MAIL_FROM_NAME') ?: '');
    $host = normalizeText(getenv('SMTP_HOST') ?: '');
    $port = (int) (getenv('SMTP_PORT') ?: 0);
    $username = normalizeText(getenv('SMTP_USERNAME') ?: '');
    $password = (string) (getenv('SMTP_PASSWORD') ?: '');
    $encryption = strtolower(normalizeText(getenv('SMTP_ENCRYPTION') ?: ''));
    $timeout = (int) (getenv('SMTP_TIMEOUT') ?: 15);
    $heloDomain = normalizeText(getenv('SMTP_HELO_DOMAIN') ?: '');

    // Atajo práctico para Gmail si el remitente pertenece a ese dominio.
    if ($host === '' && preg_match('/@gmail\.com$/i', $from) === 1) {
        $host = 'smtp.gmail.com';
        if ($port <= 0) {
            $port = 587;
        }
        if ($encryption === '') {
            $encryption = 'tls';
        }
    }

    if ($from !== '' && $username === '' && !in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
        $username = $from;
    }

    if ($port <= 0) {
        if ($encryption === 'ssl') {
            $port = 465;
        } elseif ($host !== '') {
            $port = 587;
        } else {
            $port = 25;
        }
    }

    if ($heloDomain === '') {
        $heloDomain = normalizeText(parse_url((string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '');
    }
    if ($heloDomain === '') {
        $heloDomain = normalizeText(gethostname() ?: '');
    }
    if ($heloDomain === '') {
        $heloDomain = 'localhost';
    }

    return [
        'from' => $from,
        'from_name' => $fromName,
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'encryption' => in_array($encryption, ['', 'tls', 'ssl'], true) ? $encryption : '',
        'timeout' => $timeout > 0 ? $timeout : 15,
        'helo_domain' => $heloDomain,
    ];
}

function validateMailTransportConfig(array $config): void
{
    if ($config['from'] === '' || !filter_var($config['from'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Configura MAIL_FROM con un correo válido en .env para enviar emails.');
    }

    if ($config['host'] === '') {
        throw new RuntimeException('Configura SMTP_HOST en .env para enviar correos reales desde el sistema.');
    }

    if ($config['port'] <= 0) {
        throw new RuntimeException('Configura SMTP_PORT con un puerto SMTP válido en .env.');
    }

    $host = strtolower((string) $config['host']);
    $esServidorLocal = in_array($host, ['localhost', '127.0.0.1'], true);
    $requiereAuth = !$esServidorLocal || $config['username'] !== '' || $config['password'] !== '';

    if ($requiereAuth && $config['username'] === '') {
        throw new RuntimeException('Configura SMTP_USERNAME en .env para autenticar el envío de correos.');
    }

    if ($requiereAuth && $config['password'] === '') {
        throw new RuntimeException('Configura SMTP_PASSWORD en .env para autenticar el envío de correos.');
    }
}

function smtpReadResponse($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }

        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }

    $meta = stream_get_meta_data($socket);
    if (($meta['timed_out'] ?? false) === true) {
        throw new RuntimeException('El servidor SMTP no respondió a tiempo.');
    }

    $response = trim($response);
    if ($response === '') {
        throw new RuntimeException('No hubo respuesta del servidor SMTP.');
    }

    return $response;
}

function smtpSendCommand($socket, string $command, array $expectedCodes, string $label): string
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException("No se pudo enviar el comando SMTP {$label}.");
    }

    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("Error SMTP en {$label}: {$response}");
    }

    return $response;
}

function buildSmtpMessage(string $to, string $subject, string $body, array $config): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace("/(?m)^\./", '..', $body);
    $bodyEncoded = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));

    $headers = [
        'Date: ' . date('r'),
        'From: ' . formatMailboxHeader($config['from'], $config['from_name']),
        'To: ' . formatMailboxHeader($to),
        'Reply-To: ' . formatMailboxHeader($config['from'], $config['from_name']),
        'Subject: ' . encodeMimeHeaderValue($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: App Educativa SMTP',
    ];

    return implode("\r\n", $headers) . "\r\n\r\n" . $bodyEncoded . "\r\n";
}

function sendEmailUsingSmtp(string $to, string $subject, string $body, array $config): void
{
    validateMailTransportConfig($config);

    $scheme = $config['encryption'] === 'ssl' ? 'ssl://' : '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ],
    ]);

    $socket = @stream_socket_client(
        $scheme . $config['host'] . ':' . $config['port'],
        $errno,
        $errstr,
        $config['timeout'],
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException(
            'No se pudo conectar con el servidor SMTP '
            . $config['host']
            . ':'
            . $config['port']
            . ' ('
            . ($errstr !== '' ? $errstr : 'sin detalle')
            . ').'
        );
    }

    stream_set_timeout($socket, $config['timeout']);

    try {
        $greeting = smtpReadResponse($socket);
        if ((int) substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('El servidor SMTP rechazó la conexión inicial: ' . $greeting);
        }

        smtpSendCommand($socket, 'EHLO ' . $config['helo_domain'], [250], 'EHLO');

        if ($config['encryption'] === 'tls') {
            smtpSendCommand($socket, 'STARTTLS', [220], 'STARTTLS');

            $cryptoEnabled = @stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoEnabled !== true) {
                throw new RuntimeException('No se pudo iniciar el canal TLS con el servidor SMTP.');
            }

            smtpSendCommand($socket, 'EHLO ' . $config['helo_domain'], [250], 'EHLO posterior a STARTTLS');
        }

        if ($config['username'] !== '' || $config['password'] !== '') {
            smtpSendCommand($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
            smtpSendCommand($socket, base64_encode($config['username']), [334], 'SMTP username');
            smtpSendCommand($socket, base64_encode($config['password']), [235], 'SMTP password');
        }

        smtpSendCommand($socket, 'MAIL FROM:<' . $config['from'] . '>', [250], 'MAIL FROM');
        smtpSendCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
        smtpSendCommand($socket, 'DATA', [354], 'DATA');

        $message = buildSmtpMessage($to, $subject, $body, $config);
        $written = fwrite($socket, $message . "\r\n.\r\n");
        if ($written === false) {
            throw new RuntimeException('No se pudo enviar el contenido del mensaje SMTP.');
        }

        $response = smtpReadResponse($socket);
        $code = (int) substr($response, 0, 3);
        if ($code !== 250) {
            throw new RuntimeException('El servidor SMTP rechazó el mensaje: ' . $response);
        }

        smtpSendCommand($socket, 'QUIT', [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

function normalizeUsername($value): string
{
    $username = strtolower(trim((string) $value));
    $username = preg_replace('/\s+/', '', $username);

    return is_string($username) ? $username : '';
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

function normalizeRoleValue($value, bool $allowAdminRole = true): string
{
    $role = strtolower(trim((string) $value));
    $validRoles = $allowAdminRole ? ['docente', 'administrador'] : ['docente'];

    if (!in_array($role, $validRoles, true)) {
        return 'docente';
    }

    return $role;
}

function getSecurityQuestionOptions(): array
{
    return [
        'mascota' => '¿Cuál es el nombre de tu primera mascota?',
        'escuela' => '¿Cómo se llamaba tu escuela primaria?',
        'madre' => '¿Cuál es el segundo nombre de tu madre?',
        'ciudad' => '¿En qué ciudad naciste?',
        'profesor' => '¿Cuál fue tu profesor favorito?',
        'amigo' => '¿Cómo se llamaba tu mejor amigo de la infancia?',
    ];
}

function normalizeSecurityQuestionCode($value): string
{
    $code = strtolower(trim((string) $value));
    $options = getSecurityQuestionOptions();

    return array_key_exists($code, $options) ? $code : '';
}

function getSecurityQuestionLabel(string $code): string
{
    $options = getSecurityQuestionOptions();

    return (string) ($options[$code] ?? '');
}

function normalizeSecurityAnswer($value): string
{
    $answer = mb_strtolower(normalizeText($value), 'UTF-8');
    if ($answer === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $answer);
    if (is_string($converted) && $converted !== '') {
        $answer = $converted;
    }

    $answer = preg_replace('/[^a-z0-9]+/', ' ', $answer);
    $answer = preg_replace('/\s+/', ' ', (string) $answer);

    return trim((string) $answer);
}

function validateSecurityQuestionInput(array $data, bool $required = true): array
{
    $questionCode = normalizeSecurityQuestionCode($data['pregunta_seguridad'] ?? '');
    $answer = normalizeSecurityAnswer($data['respuesta_seguridad'] ?? '');

    if ($required && ($questionCode === '' || $answer === '')) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes seleccionar una pregunta de seguridad y escribir su respuesta.',
        ]);
    }

    if (($questionCode !== '' || $answer !== '') && mb_strlen($answer, 'UTF-8') < 3) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La respuesta de seguridad debe tener al menos 3 caracteres.',
        ]);
    }

    return [
        'pregunta_seguridad' => $questionCode,
        'respuesta_seguridad' => $answer,
    ];
}

function resolveDocenteRole(array $row): string
{
    $role = normalizeRoleValue($row['rol'] ?? '', true);
    if ($role !== 'docente' || (($row['usuario'] ?? '') !== 'admin')) {
        return $role;
    }

    return 'administrador';
}

function buildAuthUserPayload(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'usuario' => (string) ($row['usuario'] ?? ''),
        'nombre' => (string) ($row['nombre'] ?? ''),
        'apellido' => (string) ($row['apellido'] ?? ''),
        'rol' => resolveDocenteRole($row),
    ];
}

function storeAuthenticatedUserSession(array $user, bool $regenerateId = false): void
{
    if ($regenerateId && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['auth_user'] = $user;
}

function getAuthenticatedUserSession(): ?array
{
    $user = $_SESSION['auth_user'] ?? null;

    return is_array($user) ? $user : null;
}

function clearAuthenticatedUserSession(): void
{
    $_SESSION = [];

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function requireAdminAccess(): array
{
    $user = getAuthenticatedUserSession();
    if (!$user || ($user['rol'] ?? '') !== 'administrador') {
        jsonResponse(403, [
            'success' => false,
            'error' => 'Debes iniciar sesión como administrador para realizar esta acción.',
        ]);
    }

    return $user;
}

function buildDocenteAdminPayload(array $row, int $currentUserId = 0): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'usuario' => (string) ($row['usuario'] ?? ''),
        'nombre' => (string) ($row['nombre'] ?? ''),
        'apellido' => (string) ($row['apellido'] ?? ''),
        'correo' => (string) ($row['correo'] ?? ''),
        'rol' => resolveDocenteRole($row),
        'activo' => (int) ($row['activo'] ?? 1) === 1,
        'fecha_registro' => (string) ($row['fecha_registro'] ?? ''),
        'es_actual' => (int) ($row['id'] ?? 0) === $currentUserId,
    ];
}

function findDocenteById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, usuario, nombre, apellido, correo, rol, activo, fecha_registro
         FROM docentes
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function findDocenteByRecoveryIdentity(mysqli $conn, string $usuario, string $correo): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, usuario, correo, activo, pregunta_seguridad, respuesta_seguridad_hash
         FROM docentes
         WHERE usuario = ?
           AND correo = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $usuario, $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function countActiveAdministrators(mysqli $conn, int $excludeId = 0): int
{
    $sql = 'SELECT COUNT(*) AS total
            FROM docentes
            WHERE activo = 1
              AND (LOWER(COALESCE(rol, "")) = "administrador" OR usuario = "admin")';

    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($excludeId > 0) {
        $stmt->bind_param('i', $excludeId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function validateDocenteInput(mysqli $conn, array $data, bool $allowAdminRole, bool $passwordRequired, int $excludeId = 0): array
{
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $usuario = normalizeUsername($data['usuario'] ?? '');
    $correo = strtolower(normalizeText($data['correo'] ?? ''));
    $contrasena = (string) ($data['contrasena'] ?? '');
    $rolSolicitado = normalizeRoleValue($data['rol'] ?? 'docente', $allowAdminRole);
    $activo = filter_var($data['activo'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($nombre === '' || $apellido === '' || $usuario === '' || $correo === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Nombre, apellido, usuario y correo son obligatorios.',
        ]);
    }

    if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($apellido, 'UTF-8') < 2) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Nombre y apellido deben tener al menos 2 caracteres.',
        ]);
    }

    if (!preg_match('/^[a-z0-9._-]{4,30}$/', $usuario)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El usuario debe tener entre 4 y 30 caracteres y solo puede usar letras, números, punto, guion y guion bajo.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Ingresa un correo electrónico válido.',
        ]);
    }

    if ($passwordRequired && $contrasena === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La contraseña es obligatoria.',
        ]);
    }

    if ($contrasena !== '' && strlen($contrasena) < 8) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
    }

    if ($activo === null) {
        $activo = true;
    }

    $stmt = $conn->prepare('SELECT id FROM docentes WHERE usuario = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo validar el usuario.',
        ]);
    }

    $stmt->bind_param('si', $usuario, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingUser = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($existingUser) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'El usuario ya existe.',
        ]);
    }

    $stmt = $conn->prepare('SELECT id FROM docentes WHERE correo = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo validar el correo.',
        ]);
    }

    $stmt->bind_param('si', $correo, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingEmail = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($existingEmail) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'El correo electrónico ya está registrado.',
        ]);
    }

    return [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'usuario' => $usuario,
        'correo' => $correo,
        'contrasena' => $contrasena,
        'rol' => $rolSolicitado,
        'activo' => $activo ? 1 : 0,
    ];
}

function logoutCurrentSession(): void
{
    clearAuthenticatedUserSession();
    jsonResponse(200, [
        'success' => true,
        'message' => 'Sesión cerrada correctamente.',
    ]);
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
    $sql = 'SELECT e.id,
                   e.nombre,
                   e.apellido,
                   e.numero_matricula,
                   e.activo,
                   a.id AS acudiente_id,
                   a.nombre AS acudiente_nombre,
                   a.parentesco AS acudiente_parentesco,
                   a.telefono AS acudiente_telefono,
                   a.correo AS acudiente_correo,
                   a.direccion AS acudiente_direccion
            FROM estudiantes e
            LEFT JOIN acudientes a ON a.estudiante_id = e.id
            WHERE e.activo = 1
            ORDER BY e.apellido ASC, e.nombre ASC';

    $result = $conn->query($sql);
    if (!$result) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudieron cargar los estudiantes.',
        ]);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $acudienteId = (int) ($row['acudiente_id'] ?? 0);
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => normalizeText($row['nombre'] ?? ''),
            'apellido' => normalizeText($row['apellido'] ?? ''),
            'numero_matricula' => normalizeText($row['numero_matricula'] ?? ''),
            'activo' => (int) ($row['activo'] ?? 0),
            'acudiente' => $acudienteId > 0
                ? [
                    'id' => $acudienteId,
                    'nombre' => normalizeText($row['acudiente_nombre'] ?? ''),
                    'parentesco' => normalizeText($row['acudiente_parentesco'] ?? ''),
                    'telefono' => normalizeText($row['acudiente_telefono'] ?? ''),
                    'correo' => normalizeText($row['acudiente_correo'] ?? ''),
                    'direccion' => normalizeText($row['acudiente_direccion'] ?? ''),
                ]
                : null,
        ];
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
                TRIM(CONCAT(COALESCE(d.nombre, ""), " ", COALESCE(d.apellido, ""))) AS docente_nombre
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

function normalizeAcudientePayload(array $data): array
{
    $source = $data;
    if (isset($data['acudiente']) && is_array($data['acudiente'])) {
        $source = $data['acudiente'];
    }

    return [
        'nombre' => normalizeText($source['nombre'] ?? ''),
        'parentesco' => normalizeText($source['parentesco'] ?? ''),
        'telefono' => normalizeText($source['telefono'] ?? ''),
        'correo' => normalizeText($source['correo'] ?? ''),
        'direccion' => normalizeText($source['direccion'] ?? ''),
    ];
}

function validateAcudientePayload(array $data, bool $requireName = true): ?string
{
    if ($requireName && ($data['nombre'] ?? '') === '') {
        return 'El nombre del acudiente es obligatorio.';
    }

    $correo = (string) ($data['correo'] ?? '');
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return 'El correo del acudiente no tiene un formato válido.';
    }

    return null;
}

function fetchAcudienteActual(mysqli $conn, int $estudianteId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, estudiante_id, nombre, parentesco, telefono, correo, direccion
         FROM acudientes
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

function fetchAcudienteByStudent(mysqli $conn, int $estudianteId): ?array
{
    $actual = fetchAcudienteActual($conn, $estudianteId);
    if ($actual !== null) {
        return $actual;
    }

    return fetchLegacyAcudiente($conn, $estudianteId);
}

function upsertAcudienteByStudent(mysqli $conn, int $estudianteId, array $data): array
{
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
        throw new RuntimeException('No se pudo preparar el guardado del acudiente.');
    }

    $stmt->bind_param(
        'isssss',
        $estudianteId,
        $data['nombre'],
        $data['parentesco'],
        $data['telefono'],
        $data['correo'],
        $data['direccion']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No se pudo guardar el perfil del acudiente.');
    }
    $stmt->close();

    $savedData = fetchAcudienteActual($conn, $estudianteId);
    if ($savedData !== null) {
        return $savedData;
    }

    return [
        'id' => 0,
        'estudiante_id' => $estudianteId,
        'nombre' => $data['nombre'],
        'parentesco' => $data['parentesco'],
        'telefono' => $data['telefono'],
        'correo' => $data['correo'],
        'direccion' => $data['direccion'],
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

    $row = fetchAcudienteActual($conn, $estudianteId);
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
    $acudienteData = normalizeAcudientePayload($data);

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

    $validationError = validateAcudientePayload($acudienteData, true);
    if ($validationError !== null) {
        jsonResponse(400, [
            'success' => false,
            'error' => $validationError,
        ]);
    }

    try {
        $savedData = upsertAcudienteByStudent($conn, $estudianteId, $acudienteData);
    } catch (RuntimeException $error) {
        jsonResponse(500, [
            'success' => false,
            'error' => $error->getMessage(),
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Perfil del acudiente guardado correctamente.',
        'id' => $savedData['id'] ?? 0,
        'data' => $savedData,
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

    try {
        sendEmailUsingSmtp($correo, $asunto, $mensaje, getMailTransportConfig());
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'success' => false,
            'error' => $exception->getMessage(),
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
    $usuario = normalizeUsername($data['usuario'] ?? '');
    $contrasena = (string) ($data['contrasena'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Usuario y contraseña son obligatorios.',
        ]);
    }

    $stmt = $conn->prepare('SELECT * FROM docentes WHERE usuario = ? LIMIT 1');

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

    if (array_key_exists('activo', $row) && (int) $row['activo'] !== 1) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'La cuenta no está activa.',
        ]);
    }

    $storedPassword = (string) ($row['password'] ?? $row['contrasena'] ?? '');
    $isValid = $storedPassword !== '' && (password_verify($contrasena, $storedPassword) || hash_equals($storedPassword, $contrasena));

    if (!$isValid) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'Usuario o contraseña incorrectos.',
        ]);
    }

    $authUser = buildAuthUserPayload($row);
    storeAuthenticatedUserSession($authUser, true);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Ingreso exitoso.',
        'data' => $authUser,
    ]);
}

function crearDocente(mysqli $conn, array $data): void
{
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $usuario = normalizeUsername($data['usuario'] ?? '');
    $correo = strtolower(normalizeText($data['correo'] ?? ''));
    $contrasena = (string) ($data['contrasena'] ?? '');
    $rolSolicitado = strtolower(trim((string) ($data['rol'] ?? 'docente')));
    $securityInput = validateSecurityQuestionInput($data, true);

    if ($nombre === '' || $apellido === '' || $usuario === '' || $correo === '' || $contrasena === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Todos los campos son obligatorios.',
        ]);
    }

    if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($apellido, 'UTF-8') < 2) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Nombre y apellido deben tener al menos 2 caracteres.',
        ]);
    }

    if (!preg_match('/^[a-z0-9._-]{4,30}$/', $usuario)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'El usuario debe tener entre 4 y 30 caracteres y solo puede usar letras, números, punto, guion y guion bajo.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Ingresa un correo electrónico válido.',
        ]);
    }

    if (strlen($contrasena) < 8) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
    }

    if ($rolSolicitado !== 'docente') {
        jsonResponse(403, [
            'success' => false,
            'error' => 'El registro público solo permite crear cuentas de docente.',
        ]);
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

    $hashedSecurityAnswer = password_hash($securityInput['respuesta_seguridad'], PASSWORD_DEFAULT);
    if ($hashedSecurityAnswer === false) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo procesar la respuesta de seguridad.',
        ]);
    }

    $insert = $conn->prepare(
        'INSERT INTO docentes (
            usuario,
            password,
            nombre,
            apellido,
            correo,
            pregunta_seguridad,
            respuesta_seguridad_hash,
            rol,
            activo
        )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );

    if (!$insert) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo crear la cuenta.',
        ]);
    }

    $rol = 'docente';
    $insert->bind_param(
        'ssssssss',
        $usuario,
        $hashedPassword,
        $nombre,
        $apellido,
        $correo,
        $securityInput['pregunta_seguridad'],
        $hashedSecurityAnswer,
        $rol
    );

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
        'data' => [
            'usuario' => $usuario,
            'rol' => $rol,
        ],
    ]);
}

function consultarPreguntaSeguridad(mysqli $conn, array $data): void
{
    $usuario = normalizeUsername($data['usuario'] ?? '');
    $correo = strtolower(normalizeText($data['correo'] ?? ''));

    if ($usuario === '' || $correo === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes indicar el usuario y el correo registrado.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Ingresa un correo electrónico válido.',
        ]);
    }

    $row = findDocenteByRecoveryIdentity($conn, $usuario, $correo);
    if (!$row || (int) ($row['activo'] ?? 0) !== 1) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'No se encontró una cuenta activa con ese usuario y correo.',
        ]);
    }

    $questionCode = normalizeSecurityQuestionCode($row['pregunta_seguridad'] ?? '');
    $answerHash = (string) ($row['respuesta_seguridad_hash'] ?? '');

    if ($questionCode === '' || $answerHash === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Esta cuenta no tiene una pregunta de seguridad configurada. Solicita apoyo al administrador.',
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Pregunta de seguridad cargada correctamente.',
        'data' => [
            'pregunta' => $questionCode,
            'pregunta_label' => getSecurityQuestionLabel($questionCode),
        ],
    ]);
}

function recuperarContrasena(mysqli $conn, array $data): void
{
    $usuario = normalizeUsername($data['usuario'] ?? '');
    $correo = strtolower(normalizeText($data['correo'] ?? ''));
    $respuesta = normalizeSecurityAnswer($data['respuesta_seguridad'] ?? '');
    $contrasenaNueva = (string) ($data['contrasena_nueva'] ?? '');

    if ($usuario === '' || $correo === '' || $respuesta === '' || $contrasenaNueva === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes completar usuario, correo, respuesta de seguridad y nueva contraseña.',
        ]);
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Ingresa un correo electrónico válido.',
        ]);
    }

    if (mb_strlen($respuesta, 'UTF-8') < 3) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La respuesta de seguridad debe tener al menos 3 caracteres.',
        ]);
    }

    if (strlen($contrasenaNueva) < 8) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        ]);
    }

    $row = findDocenteByRecoveryIdentity($conn, $usuario, $correo);
    if (!$row || (int) ($row['activo'] ?? 0) !== 1) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'No se encontró una cuenta activa con ese usuario y correo.',
        ]);
    }

    $questionCode = normalizeSecurityQuestionCode($row['pregunta_seguridad'] ?? '');
    $storedAnswer = (string) ($row['respuesta_seguridad_hash'] ?? '');
    if ($questionCode === '' || $storedAnswer === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Esta cuenta no tiene una pregunta de seguridad configurada. Solicita apoyo al administrador.',
        ]);
    }

    $isValidAnswer = password_verify($respuesta, $storedAnswer) || hash_equals($storedAnswer, $respuesta);
    if (!$isValidAnswer) {
        jsonResponse(401, [
            'success' => false,
            'error' => 'La respuesta de seguridad no coincide. No fue posible cambiar la contraseña.',
        ]);
    }

    $hashedPassword = password_hash($contrasenaNueva, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo procesar la nueva contraseña.',
        ]);
    }

    $stmt = $conn->prepare('UPDATE docentes SET password = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo actualizar la contraseña.',
        ]);
    }

    $docenteId = (int) ($row['id'] ?? 0);
    $stmt->bind_param('si', $hashedPassword, $docenteId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo actualizar la contraseña.',
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.',
    ]);
}

function obtenerUsuariosAdmin(mysqli $conn): void
{
    $authUser = requireAdminAccess();
    $result = $conn->query(
        'SELECT id, usuario, nombre, apellido, correo, rol, activo, fecha_registro
         FROM docentes
         ORDER BY activo DESC,
                  CASE WHEN LOWER(COALESCE(rol, "")) = "administrador" OR usuario = "admin" THEN 0 ELSE 1 END,
                  nombre ASC,
                  apellido ASC,
                  usuario ASC'
    );

    if (!$result) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo cargar el listado de usuarios.',
        ]);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = buildDocenteAdminPayload($row, (int) ($authUser['id'] ?? 0));
    }

    jsonResponse(200, [
        'success' => true,
        'data' => $rows,
    ]);
}

function crearUsuarioAdmin(mysqli $conn, array $data): void
{
    requireAdminAccess();
    $input = validateDocenteInput($conn, $data, true, true);

    $hashedPassword = password_hash($input['contrasena'], PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo procesar la contraseña del usuario.',
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO docentes (usuario, password, nombre, apellido, correo, rol, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo crear el usuario.',
        ]);
    }

    $stmt->bind_param(
        'ssssssi',
        $input['usuario'],
        $hashedPassword,
        $input['nombre'],
        $input['apellido'],
        $input['correo'],
        $input['rol'],
        $input['activo']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo crear el usuario.',
        ]);
    }

    $newId = (int) $conn->insert_id;
    $stmt->close();

    $created = findDocenteById($conn, $newId);
    jsonResponse(201, [
        'success' => true,
        'message' => 'Usuario creado correctamente.',
        'data' => $created ? buildDocenteAdminPayload($created) : ['id' => $newId],
    ]);
}

function actualizarUsuarioAdmin(mysqli $conn, array $data): void
{
    $authUser = requireAdminAccess();
    $userId = (int) ($data['id'] ?? 0);

    if ($userId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes indicar un usuario válido para editar.',
        ]);
    }

    $currentRow = findDocenteById($conn, $userId);
    if (!$currentRow) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El usuario seleccionado no existe.',
        ]);
    }

    $input = validateDocenteInput($conn, $data, true, false, $userId);
    $isCurrentUser = $userId === (int) ($authUser['id'] ?? 0);
    $currentRole = resolveDocenteRole($currentRow);
    $willRemainAdmin = $input['rol'] === 'administrador';
    $willRemainActive = (int) $input['activo'] === 1;

    if ($isCurrentUser && (!$willRemainAdmin || !$willRemainActive)) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta desde aquí.',
        ]);
    }

    if ($currentRole === 'administrador' && (!$willRemainAdmin || !$willRemainActive)) {
        if (countActiveAdministrators($conn, $userId) === 0) {
            jsonResponse(409, [
                'success' => false,
                'error' => 'Debe existir al menos un administrador activo en el sistema.',
            ]);
        }
    }

    $hashedPassword = '';
    if ($input['contrasena'] !== '') {
        $hashedPassword = password_hash($input['contrasena'], PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            jsonResponse(500, [
                'success' => false,
                'error' => 'No se pudo procesar la nueva contraseña.',
            ]);
        }
    }

    $stmt = $conn->prepare(
        'UPDATE docentes
         SET usuario = ?,
             nombre = ?,
             apellido = ?,
             correo = ?,
             rol = ?,
             activo = ?,
             password = COALESCE(NULLIF(?, ""), password)
         WHERE id = ?'
    );

    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo actualizar el usuario.',
        ]);
    }

    $stmt->bind_param(
        'sssssisi',
        $input['usuario'],
        $input['nombre'],
        $input['apellido'],
        $input['correo'],
        $input['rol'],
        $input['activo'],
        $hashedPassword,
        $userId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo actualizar el usuario.',
        ]);
    }

    $stmt->close();

    $updated = findDocenteById($conn, $userId);
    $payload = [
        'usuario' => $updated ? buildDocenteAdminPayload($updated, (int) ($authUser['id'] ?? 0)) : ['id' => $userId],
    ];

    if ($updated && $isCurrentUser) {
        $sessionUser = buildAuthUserPayload($updated);
        storeAuthenticatedUserSession($sessionUser);
        $payload['auth_user'] = $sessionUser;
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Usuario actualizado correctamente.',
        'data' => $payload,
    ]);
}

function eliminarUsuarioAdmin(mysqli $conn, array $data): void
{
    $authUser = requireAdminAccess();
    $userId = (int) ($data['id'] ?? 0);

    if ($userId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debes indicar un usuario válido para eliminar.',
        ]);
    }

    if ($userId === (int) ($authUser['id'] ?? 0)) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'No puedes eliminar tu propia cuenta desde el panel.',
        ]);
    }

    $currentRow = findDocenteById($conn, $userId);
    if (!$currentRow) {
        jsonResponse(404, [
            'success' => false,
            'error' => 'El usuario seleccionado no existe.',
        ]);
    }

    if (resolveDocenteRole($currentRow) === 'administrador' && countActiveAdministrators($conn, $userId) === 0) {
        jsonResponse(409, [
            'success' => false,
            'error' => 'No puedes eliminar al último administrador activo.',
        ]);
    }

    $stmt = $conn->prepare('DELETE FROM docentes WHERE id = ?');
    if (!$stmt) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo eliminar el usuario.',
        ]);
    }

    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo eliminar el usuario.',
        ]);
    }
    $stmt->close();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Usuario eliminado correctamente.',
    ]);
}

function agregarEstudiante(mysqli $conn, array $data): void
{
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $matricula = strtoupper(normalizeText($data['numero_matricula'] ?? ''));
    $acudienteData = normalizeAcudientePayload($data);

    if ($nombre === '' || $apellido === '' || $matricula === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Nombre, apellido y matrícula son obligatorios.',
        ]);
    }

    $validationError = validateAcudientePayload($acudienteData, true);
    if ($validationError !== null) {
        jsonResponse(400, [
            'success' => false,
            'error' => $validationError,
        ]);
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'INSERT INTO estudiantes (nombre, apellido, numero_matricula, activo)
         VALUES (?, ?, ?, 1)'
    );

    if (!$stmt) {
        $conn->rollback();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar el alta de estudiante.',
        ]);
    }

    $stmt->bind_param('sss', $nombre, $apellido, $matricula);

    if (!$stmt->execute()) {
        $code = $stmt->errno;
        $stmt->close();
        $conn->rollback();

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

    try {
        upsertAcudienteByStudent($conn, $newId, $acudienteData);
        $conn->commit();
    } catch (RuntimeException $error) {
        $conn->rollback();
        jsonResponse(500, [
            'success' => false,
            'error' => $error->getMessage(),
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'message' => 'Estudiante y acudiente agregados correctamente.',
        'id' => $newId,
    ]);
}

function actualizarEstudiante(mysqli $conn, array $data): void
{
    $id = (int) ($data['id'] ?? 0);
    $nombre = normalizeText($data['nombre'] ?? '');
    $apellido = normalizeText($data['apellido'] ?? '');
    $matricula = strtoupper(normalizeText($data['numero_matricula'] ?? ''));
    $acudienteData = normalizeAcudientePayload($data);

    if ($id <= 0 || $nombre === '' || $apellido === '' || $matricula === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Datos incompletos para actualizar estudiante.',
        ]);
    }

    $validationError = validateAcudientePayload($acudienteData, true);
    if ($validationError !== null) {
        jsonResponse(400, [
            'success' => false,
            'error' => $validationError,
        ]);
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'UPDATE estudiantes
         SET nombre = ?, apellido = ?, numero_matricula = ?
         WHERE id = ? AND activo = 1'
    );

    if (!$stmt) {
        $conn->rollback();
        jsonResponse(500, [
            'success' => false,
            'error' => 'No se pudo preparar la edición del estudiante.',
        ]);
    }

    $stmt->bind_param('sssi', $nombre, $apellido, $matricula, $id);

    if (!$stmt->execute()) {
        $code = $stmt->errno;
        $stmt->close();
        $conn->rollback();

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
                $conn->rollback();
                jsonResponse(404, [
                    'success' => false,
                    'error' => 'El estudiante a editar no existe o está inactivo.',
                ]);
            }
        }
    }

    try {
        upsertAcudienteByStudent($conn, $id, $acudienteData);
        $conn->commit();
    } catch (RuntimeException $error) {
        $conn->rollback();
        jsonResponse(500, [
            'success' => false,
            'error' => $error->getMessage(),
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Estudiante y acudiente actualizados correctamente.',
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
    $conn = conectarDB();
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

        case 'obtenerUsuariosAdmin':
            obtenerUsuariosAdmin($conn);
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

        case 'logout':
            logoutCurrentSession();
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

        case 'consultarPreguntaSeguridad':
            consultarPreguntaSeguridad($conn, $payload);
            break;

        case 'recuperarContrasena':
            recuperarContrasena($conn, $payload);
            break;

        case 'crearUsuarioAdmin':
            crearUsuarioAdmin($conn, $payload);
            break;

        case 'actualizarUsuarioAdmin':
            actualizarUsuarioAdmin($conn, $payload);
            break;

        case 'eliminarUsuarioAdmin':
            eliminarUsuarioAdmin($conn, $payload);
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
