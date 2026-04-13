<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

/**
 * Gera códigos únicos para cartões presente no formato SPA-XXXXXX.
 */
final class CodeGenerator
{
    private const PREFIX  = 'SPA';
    private const CHARS   = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O/1/I (ambíguos)
    private const LENGTH  = 6;
    private const MAX_TRIES = 20;

    /**
     * Gera um código SPA-XXXXXX único verificado contra o banco.
     *
     * @throws \RuntimeException se não conseguir gerar um código único
     */
    public static function generateGiftCardCode(PDO $pdo): string
    {
        for ($i = 0; $i < self::MAX_TRIES; $i++) {
            $code = self::build();

            $stmt = $pdo->prepare('SELECT id FROM gift_cards WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);

            if (!$stmt->fetch()) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um código único após ' . self::MAX_TRIES . ' tentativas.');
    }

    /** Monta o código aleatório. */
    private static function build(): string
    {
        $chars  = self::CHARS;
        $len    = strlen($chars);
        $suffix = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $suffix .= $chars[random_int(0, $len - 1)];
        }

        return self::PREFIX . '-' . $suffix;
    }

    /**
     * Gera um token aleatório seguro (para uso interno, tokens de webhook, etc.).
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
