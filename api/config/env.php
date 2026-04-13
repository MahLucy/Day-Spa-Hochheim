<?php

declare(strict_types=1);

use Dotenv\Dotenv;

// Carrega as variáveis do .env uma única vez
if (!defined('ENV_LOADED')) {
    define('ENV_LOADED', true);

    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();

    $dotenv->required([
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'MP_ACCESS_TOKEN', 'MP_WEBHOOK_SECRET',
        'MAIL_HOST', 'MAIL_USER', 'MAIL_PASS',
    ]);
}

/**
 * Retorna valor do ambiente com fallback seguro.
 */
function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return match (strtolower((string) $val)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $val,
    };
}
