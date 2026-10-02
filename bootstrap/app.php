<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use App\Http\ErrorResponse;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');

$debug = filter_var($_ENV['APP_DEBUG'] ?? '0', FILTER_VALIDATE_BOOL);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log(sprintf(
        '[UNCAUGHT] %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if (PHP_SAPI !== 'cli') {
        ErrorResponse::render(
            500,
            'Ocurrió un error inesperado',
            $debug
                ? $e->getMessage()
                : 'No fue posible completar la operación. El incidente fue registrado y puedes intentarlo nuevamente.',
            '/login',
            'Continuar'
        );
    }
});

return true;
