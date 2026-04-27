<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function conectarDB(): mysqli
{
    static $connection = null;
    static $shutdownRegistered = false;

    if ($connection instanceof mysqli && @$connection->ping()) {
        return $connection;
    }

    $cfg = getDbConfig();
    $ports = array_values(array_unique(array_filter([
        (int) $cfg['port'],
        3306,
        3307,
    ], static fn($port) => $port > 0)));

    $errors = [];

    foreach ($ports as $port) {
        $attempt = @new mysqli(
            $cfg['host'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['name'],
            $port
        );

        if ($attempt->connect_error) {
            $errors[] = $attempt->connect_error;
            $attempt->close();
            continue;
        }

        if (!$attempt->set_charset($cfg['charset'])) {
            $attempt->close();
            throw new RuntimeException('No se pudo configurar charset utf8mb4.');
        }

        $connection = $attempt;

        if (!$shutdownRegistered) {
            register_shutdown_function(static function () use (&$connection): void {
                if ($connection instanceof mysqli) {
                    try {
                        $connection->close();
                    } catch (\Throwable $_) {
                        // Ignorar errores al cerrar.
                    }
                }
            });
            $shutdownRegistered = true;
        }

        return $connection;
    }

    $message = $errors[0] ?? 'Sin detalles disponibles.';
    throw new RuntimeException('Error de conexión MySQL: ' . $message);
}
