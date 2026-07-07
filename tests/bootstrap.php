<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Package-local Pest bootstrap
|--------------------------------------------------------------------------
|
| The package ships its own `phpunit.xml` with bootstrap="vendor/autoload.php".
| The package does not vendor dependencies of its own (the host project
| resolves them through its composer path repository), so we delegate to
| the host autoloader and then register the package's `Tests` namespace
| on top of it.
|
| This lets `php artisan make:test --pest` style tooling, the package's
| own Pest configuration, and the existing TestCase / traits all work
| without running `composer install` inside the package.
*/

$hostAutoload = dirname(__DIR__, 3).'/vendor/autoload.php';
if (! file_exists($hostAutoload)) {
    fwrite(STDERR, "Host autoloader not found at {$hostAutoload}\n");
    exit(1);
}

require $hostAutoload;

$packageRoot = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($packageRoot) {
    $prefix = 'Headless\\Accounting\\Tests\\';
    $baseDir = $packageRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR;

    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = $baseDir.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';
        if (is_file($file)) {
            require $file;

            return true;
        }
    }

    return false;
}, prepend: true);
