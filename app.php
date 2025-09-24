<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\ExceptionHandler;
use Butschster\ContextGenerator\Application\FSPath;
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
$globalStateDir = (string) FSPath::create(match (PHP_OS_FAMILY) {
    'Windows' => (function (): string {
            $result = $_SERVER['APPDATA'] ?? null;
            if (\is_string($result)) {
                return $result;
            }

            /*
             * In some cases, the APPDATA environment variable may not be set by an MCP client (f.e. Claude)
             * In this case we need to use workaround.
             */
            /** @psalm-suppress ForbiddenCode */
            $output = `reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\Shell Folders" /v "AppData"`;
            // Check if the command was successful
            $pos = \strpos($output, $_SERVER['USERPROFILE']);
            if ($pos === false) {
                // If the command failed, we can use the default APPDATA path
                return $_SERVER['USERPROFILE'] . '\AppData\Roaming';
            }

            return \trim(\explode("\n", \substr($output, $pos))[0]);
        })() . '/CTX',
    'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/CTX',
    default => $_SERVER['HOME'] . '/.config/ctx',
});

$app = Kernel::create(
    directories: [
        'root' => $appPath,
        'output' => $appPath . '/.context',
        'config' => __DIR__ . '/config',
        'runtime' => __DIR__ . '/runtime',
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
