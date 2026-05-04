<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\AppLogger;
use App\Helpers\Response;

/**
 * Middleware para webhooks do Mercado Pago.
 *
 * Funções:
 * 1. Rate limiting básico (por IP, baseado em arquivo)
 * 2. Extrai e repassa cabeçalhos necessários para validação de assinatura
 */
final class WebhookMiddleware
{
    private const RATE_LIMIT_CALLS    = 60;    // máximo de chamadas
    private const RATE_LIMIT_WINDOW   = 60;    // por janela de N segundos
    private const RATE_LIMIT_DIR      = null;  // resolvido em runtime

    /**
     * Aplica rate limiting e retorna os cabeçalhos de assinatura.
     *
     * @return array ['signature' => string, 'request_id' => string]
     */
    public static function handle(): array
    {
        self::enforceRateLimit();

        $signature  = $_SERVER['HTTP_X_SIGNATURE']   ?? '';
        $requestId  = $_SERVER['HTTP_X_REQUEST_ID']  ?? '';

        return [
            'signature'  => $signature,
            'request_id' => $requestId,
        ];
    }

    // ─── Rate limiting por arquivo ────────────────────────────────────────────

    private static function enforceRateLimit(): void
    {
        $ip       = self::getClientIp();
        $safeIp   = preg_replace('/[^a-f0-9:]/', '_', strtolower($ip));
        $lockDir  = self::getRateLimitDir();
        $lockFile = $lockDir . '/' . $safeIp . '.json';

        $now    = time();
        $window = self::RATE_LIMIT_WINDOW;

        // FIX: race condition (TOCTOU) corrigida.
        // A implementação anterior usava file_get_contents() sem lock exclusivo, o que
        // permitia que duas requisições simultâneas do MP lessem o mesmo estado e ambas
        // passassem pelo rate limit antes de qualquer uma escrever o valor atualizado.
        // Agora o flock(LOCK_EX) garante acesso atômico: leitura e escrita dentro do
        // mesmo lock, sem janela de corrida entre os dois.
        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            // Sem acesso ao arquivo de lock → não bloqueia (fail-open consciente)
            AppLogger::warning('Webhook rate limit: não foi possível abrir arquivo de lock', [
                'ip'   => $ip,
                'file' => $lockFile,
            ]);
            return;
        }

        flock($fp, LOCK_EX); // bloqueia leitura E escrita até liberar

        $raw  = stream_get_contents($fp);
        $data = $raw ? (json_decode($raw, true) ?? []) : [];
        $data += ['calls' => [], 'blocked_until' => 0];

        // Ainda bloqueado?
        if ($data['blocked_until'] > $now) {
            flock($fp, LOCK_UN);
            fclose($fp);
            AppLogger::warning('Webhook rate limit ativo', ['ip' => $ip]);
            http_response_code(429);
            header('Retry-After: ' . ($data['blocked_until'] - $now));
            Response::error('Too Many Requests', 429);
        }

        // Remove chamadas fora da janela e registra a atual
        $data['calls'] = array_values(
            array_filter($data['calls'], fn($ts) => $ts > $now - $window)
        );
        $data['calls'][] = $now;

        if (count($data['calls']) > self::RATE_LIMIT_CALLS) {
            $data['blocked_until'] = $now + $window;
            rewind($fp); ftruncate($fp, 0);
            fwrite($fp, json_encode($data));
            flock($fp, LOCK_UN);
            fclose($fp);
            AppLogger::warning('Webhook rate limit excedido', ['ip' => $ip]);
            http_response_code(429);
            Response::error('Too Many Requests', 429);
        }

        rewind($fp); ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private static function getRateLimitDir(): string
    {
        $base = rtrim(env('STORAGE_PATH', dirname(__DIR__, 2) . '/api/storage'), '/');
        $dir  = $base . '/rate_limits';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }
}
