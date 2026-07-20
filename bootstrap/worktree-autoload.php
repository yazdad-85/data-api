<?php

/**
 * Shared vendor/ is symlinked to the main checkout, so Composer's PSR-4 map
 * points App\\ and Tests\\ at the main tree. Register this AFTER
 * vendor/autoload.php so it prepends ahead of Composer and the current
 * worktree's PHP sources win without re-running composer dump-autoload.
 */
$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    static $prefixes = [
        'App\\' => '/app/',
        'Database\\Factories\\' => '/database/factories/',
        'Database\\Seeders\\' => '/database/seeders/',
        'Tests\\' => '/tests/',
    ];

    foreach ($prefixes as $prefix => $path) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        $file = $root.$path.$relative;

        if (is_file($file)) {
            require $file;
        }

        return;
    }
}, true, true);
