<?php

/**
 * Router para o servidor built-in do PHP (php -S).
 *
 * Comportamento:
 *  - Arquivos estáticos que existem no disco (PDFs, QR Codes, imagens)
 *    são servidos diretamente pelo servidor built-in (retorna false).
 *  - Tudo o mais é roteado para index.php (API REST).
 *
 * Uso: php -S 0.0.0.0:8000 router.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $uri  = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $file = __DIR__ . $uri;

    // Serve o arquivo estático se ele existir (ex.: storage/pdfs/*, storage/qrcodes/*)
    if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
        return false;
    }
}

require_once __DIR__ . '/index.php';
