<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Middleware CORS — wrapper para a função em config/cors.php.
 * Disponível como classe para uso em contextos orientados a objetos.
 */
final class CorsMiddleware
{
    public static function apply(): void
    {
        applyCorsHeaders();
    }
}
