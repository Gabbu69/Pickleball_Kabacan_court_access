<?php

declare(strict_types=1);

if (getenv('VERCEL')) {
    $storagePath = '/tmp/kabacan-pickleplay';
    $directories = [
        $storagePath.'/app/private',
        $storagePath.'/app/public',
        $storagePath.'/framework/cache/data',
        $storagePath.'/framework/sessions',
        $storagePath.'/framework/testing',
        $storagePath.'/framework/views',
        $storagePath.'/logs',
        $storagePath.'/bootstrap/cache',
    ];

    foreach ($directories as $directory) {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create runtime directory: {$directory}");
        }
    }

    $runtimeEnvironment = [
        'LARAVEL_STORAGE_PATH' => $storagePath,
        'VIEW_COMPILED_PATH' => $storagePath.'/framework/views',
        'APP_CONFIG_CACHE' => $storagePath.'/bootstrap/cache/config.php',
        'APP_EVENTS_CACHE' => $storagePath.'/bootstrap/cache/events.php',
        'APP_PACKAGES_CACHE' => $storagePath.'/bootstrap/cache/packages.php',
        'APP_ROUTES_CACHE' => $storagePath.'/bootstrap/cache/routes.php',
        'APP_SERVICES_CACHE' => $storagePath.'/bootstrap/cache/services.php',
        'LOG_CHANNEL' => getenv('LOG_CHANNEL') ?: 'stderr',
        'LOG_STDERR_FORMATTER' => getenv('LOG_STDERR_FORMATTER') ?: 'Monolog\Formatter\JsonFormatter',
        'QUEUE_CONNECTION' => getenv('QUEUE_CONNECTION') ?: 'sync',
    ];

    foreach ($runtimeEnvironment as $name => $value) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

require __DIR__.'/../public/index.php';
