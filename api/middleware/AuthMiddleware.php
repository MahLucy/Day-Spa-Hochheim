<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\AppLogger;
use App\Helpers\Response;

/**
 * Middleware de autenticação para rotas admin.
 *
 * Usa HTTP Basic Auth gerenciado pelo .htpasswd do cPanel.
 * O Apache já valida as credenciais antes de passar para o PHP —
 * aqui apenas verificamos se PHP_AUTH_USER está preenchido.
 *
 * Se o .htpasswd não estiver configurado no vhost, o middleware
 * aceita credenciais via cabeçalho Authorization como fallback.
 */
final class AuthMiddleware
{
    /**
     * Verifica se o acesso admin é válido.
     * Chame no início de qualquer action de rota admin.
     */
    public static function requireAdmin(): void
    {
        // Apache/cPanel já validou via .htpasswd — PHP_AUTH_USER estará preenchido
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            return;
        }

        // Fallback: lê Authorization header manualmente
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Basic ')) {
            $decoded = base64_decode(substr($authHeader, 6));
            [$user, $pass] = array_pad(explode(':', $decoded, 2), 2, '');

            if (self::checkCredentials($user, $pass)) {
                $_SERVER['PHP_AUTH_USER'] = $user;
                return;
            }
        }

        // Não autenticado
        AppLogger::warning('Tentativa de acesso admin não autorizada', [
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);

        header('WWW-Authenticate: Basic realm="Day Spa Hochheim Admin"');
        Response::unauthorized('Credenciais inválidas ou ausentes.');
    }

    /**
     * Verifica credenciais simples configuradas via .env.
     * Apenas como fallback — prefira .htpasswd do cPanel.
     */
    private static function checkCredentials(string $user, string $pass): bool
    {
        $expectedUser = env('ADMIN_USER', '');
        $expectedHash = env('ADMIN_PASS_HASH', ''); // bcrypt hash da senha

        if (empty($expectedUser) || empty($expectedHash)) {
            return false;
        }

        return $user === $expectedUser && password_verify($pass, $expectedHash);
    }
}
