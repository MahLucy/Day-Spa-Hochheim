<?php

declare(strict_types=1);

/**
 * Configura cabeçalhos CORS.
 * Permite apenas o domínio do frontend e localhost em desenvolvimento.
 */
function applyCorsHeaders(): void
{
    $allowedOrigins = [
        env('APP_URL', 'https://hochheim.com.br'),
        env('ADMIN_URL', 'https://hochheim.com.br/admin'),
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } elseif (env('APP_ENV') === 'development') {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Signature');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    // Responde imediatamente para preflight (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
