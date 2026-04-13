<?php

declare(strict_types=1);

/**
 * Conexão PDO — Singleton.
 * Uso: $pdo = Database::getInstance();
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                env('DB_HOST', 'localhost'),
                env('DB_NAME'),
                env('DB_CHARSET', 'utf8mb4')
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    env('DB_USER'),
                    env('DB_PASS'),
                    $options
                );
            } catch (PDOException $e) {
                // AppLogger pode não estar carregado ainda no bootstrap —
                // escreve direto no error_log do PHP e retorna 503 JSON.
                error_log('[FATAL] DB connection failed: ' . $e->getMessage());
                if (class_exists('AppLogger')) {
                    AppLogger::error('Falha na conexão com banco de dados', [
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode(),
                    ]);
                }
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Serviço temporariamente indisponível.']);
                exit;
            }
        }

        return self::$instance;
    }
}
