<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Padroniza todas as respostas JSON da API.
 * Formato: { success, data, message, errors }
 */
final class Response
{
    public static function json(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200,
        bool $success = true
    ): never {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): never
    {
        self::json($data, $message, $statusCode, true);
    }

    public static function created(mixed $data = null, string $message = 'Criado com sucesso.'): never
    {
        self::json($data, $message, 201, true);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function notFound(string $message = 'Recurso não encontrado.'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Não autorizado.'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acesso negado.'): never
    {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Erro interno do servidor.'): never
    {
        self::error($message, 500);
    }

    public static function validationError(array $errors, string $message = 'Dados inválidos.'): never
    {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}
