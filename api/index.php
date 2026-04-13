<?php

declare(strict_types=1);

// ─── Garante que warnings/notices não poluam a resposta JSON ─────────────────
// PHP pode emitir notices antes do header Content-Type, corrompendo o JSON.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);   // captura tudo no log, mas não exibe

// ─── Autoloader do Composer ───────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

// ─── Variáveis de ambiente ────────────────────────────────────────────────────
require_once __DIR__ . '/config/env.php';

// ─── Logger (alias global) ────────────────────────────────────────────────────
// O alias é definido no próprio helpers/Logger.php após o autoload

// ─── Banco de dados (singleton) ───────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

// ─── CORS ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/cors.php';
applyCorsHeaders();

// ─── Content-Type padrão ─────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ─── Handler global de exceções não capturadas ────────────────────────────────
set_exception_handler(function (\Throwable $e): void {
    AppLogger::error('Exceção não tratada', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => env('APP_ENV') === 'production'
            ? 'Erro interno do servidor.'
            : $e->getMessage(),
    ]);
    exit;
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ─── Parse da requisição ──────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri    = rawurldecode($uri);

// Remove o prefixo do caminho base (ex.: /api) se a API estiver em subpasta
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}

// Garante que começa com /
if (empty($uri) || $uri[0] !== '/') {
    $uri = '/' . $uri;
}

// Remove trailing slash (exceto raiz)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// ─── Rota de health check ────────────────────────────────────────────────────
if ($uri === '/' || $uri === '/health') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Day Spa Hochheim API',
        'version' => '1.0.0',
        'env'     => env('APP_ENV', 'production'),
        'time'    => date('c'),
    ]);
    exit;
}

// ─── Dispatcher ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/routes/api.php';

try {
    dispatchRoute($method, $uri);
} catch (\App\Helpers\Response $e) {
    // Response termina com exit — não deve chegar aqui
} catch (\Throwable $e) {
    AppLogger::error('Erro não tratado no dispatcher', [
        'method'  => $method,
        'uri'     => $uri,
        'error'   => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => env('APP_ENV') === 'production'
            ? 'Erro interno do servidor.'
            : $e->getMessage(),
    ]);
}
