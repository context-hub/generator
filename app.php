<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\ExceptionHandler;
use Butschster\ContextGenerator\Application\Kernel;
use Spiral\Core\Container;
use Spiral\Core\Options;

// -----------------------------------------------------------------------------
//  Prepare Global Environment
// -----------------------------------------------------------------------------
\mb_internal_encoding('UTF-8');
\error_reporting(E_ALL ^ E_DEPRECATED);


// -----------------------------------------------------------------------------
//  Detect Environment
// -----------------------------------------------------------------------------

if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed', 'micro'], true)) {
    echo PHP_EOL . 'This app may only be invoked from a command line, got "' . PHP_SAPI . '"' . PHP_EOL;

    exit(1);
}


// -----------------------------------------------------------------------------
//  Load Composer's Autoloader
// -----------------------------------------------------------------------------
$vendorPath = (static function (): string {
    // OK, it's not, let give Composer autoloader a try!
    $possibleFiles = [
        __DIR__ . '/../../autoload.php',
        __DIR__ . '/../autoload.php',
        __DIR__ . '/vendor/autoload.php',
    ];
    $file = null;
    foreach ($possibleFiles as $possibleFile) {
        if (\file_exists($possibleFile)) {
            $file = $possibleFile;

            break;
        }
    }

    if ($file === null) {
        throw new \RuntimeException('Unable to locate autoload.php file.');
    }

    require_once $file;

    return $file;
})();


// -----------------------------------------------------------------------------
//  Initialize Shared Container
// -----------------------------------------------------------------------------

$insidePhar = \str_starts_with(__FILE__, 'phar://');
$vendorPath = \dirname($vendorPath) . '/../';
$versionFile = $vendorPath . '/version.json';
$appPath = $insidePhar ? \getcwd() : \realpath($vendorPath);

$version = \file_exists($versionFile)
    ? \json_decode(\file_get_contents($versionFile), true)
    : [
        'version' => 'dev',
        'type' => 'phar',
    ];

$type = $version['type'] ?? 'phar';

$options = new Options();
$options->checkScope = true;

$container = new Container(options: $options);
$container->bindSingleton(
    Application::class,
    new Application(
        version: $version['version'] ?? 'dev',
        name: 'Context Generator',
        isBinary: $type !== 'phar',
    ),
);

// -----------------------------------------------------------------------------
//  Execute Application
// -----------------------------------------------------------------------------

// Determine appropriate location for global state based on OS
$globalStateDir = match (PHP_OS_FAMILY) {
    'Windows' => \getenv('APPDATA') . '/CTX',
    'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/CTX',
    default => $_SERVER['HOME'] . '/.config/ctx',
};

$app = Kernel::create(
    directories: [
        'root' => $appPath,
        'output' => $appPath . '/.context',
        'config' => $appPath,
        'global-state' => $globalStateDir,
        'json-schema' => __DIR__,
    ],
    exceptionHandler: ExceptionHandler::class,
    container: $container,
)->run();

if ($app === null) {
    exit(255);
}

$code = (int) $app->serve();
exit($code);
