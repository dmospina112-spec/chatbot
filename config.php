<?php

declare(strict_types=1);

/**
 * Carga los valores de configuracion.
 * Prioriza el archivo .env, si no, usa valores por defecto.
 */
function getDbConfig(): array
{
    loadLocalEnv();

    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'name' => getenv('DB_NAME') ?: 'App_educativa',
        'charset' => 'utf8mb4',
    ];
}

/**
 * Carga el archivo .env en las variables de entorno del sistema.
 */
function loadLocalEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envPath = __DIR__ . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        $loaded = true;
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        $loaded = true;
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    $loaded = true;
}
