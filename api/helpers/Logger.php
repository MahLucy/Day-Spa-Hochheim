<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Logger simples em arquivo — compatível com cPanel shared hosting.
 * Não requer Redis, Monolog ou qualquer dependência externa.
 */
final class AppLogger
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB — rota o arquivo automaticamente

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (env('APP_ENV', 'production') !== 'production') {
            self::write('DEBUG', $message, $context);
        }
    }

    /** Registra ação de admin no banco e no arquivo de log. */
    public static function adminAction(
        string $action,
        string $entity,
        ?int $entityId,
        array $details = []
    ): void {
        $adminUser = $_SERVER['PHP_AUTH_USER'] ?? 'unknown';
        $ip        = self::getClientIp();

        self::info("ADMIN: {$action} on {$entity}#{$entityId}", [
            'admin_user' => $adminUser,
            'ip'         => $ip,
            'details'    => $details,
        ]);

        try {
            $pdo  = \Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO admin_logs (action, entity, entity_id, admin_user, ip, details)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $action,
                $entity,
                $entityId,
                $adminUser,
                $ip,
                json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Log de auditoria não deve quebrar o fluxo principal
        }
    }

    private static function write(string $level, string $message, array $context): void
    {
        $logPath = self::getLogPath();
        self::rotateIfNeeded($logPath);

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }

    private static function getLogPath(): string
    {
        $storagePath = env('STORAGE_PATH', dirname(__DIR__) . '/storage');
        $logsDir     = rtrim($storagePath, '/') . '/logs';

        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        return $logsDir . '/app.log';
    }

    private static function rotateIfNeeded(string $logPath): void
    {
        if (file_exists($logPath) && filesize($logPath) > self::MAX_SIZE_BYTES) {
            $archive = str_replace('.log', '-' . date('YmdHis') . '.log', $logPath);
            @rename($logPath, $archive);
        }
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return 'unknown';
    }
}

// Alias global para uso antes do autoloader de namespace resolver
if (!class_exists('AppLogger')) {
    class_alias(\App\Helpers\AppLogger::class, 'AppLogger');
}
