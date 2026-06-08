<?php

/**
 * Cron: cancela pedidos que ficaram em 'pending' por mais de 24 horas.
 *
 * Configurar no cPanel — Cron Jobs:
 *   Periodicidade: a cada hora  →  0 * * * *
 *   Comando:  php /home/spahochheimcom/public_html/api/cron/expire_orders.php
 */

declare(strict_types=1);

define('CRON_MODE', true);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';

use App\Models\Order;

$pdo        = \Database::getInstance();
$orderModel = new Order($pdo);

$minutesOld = 1440; // 24 horas
$cancelled  = $orderModel->cancelExpired($minutesOld);

$ts = date('Y-m-d H:i:s');

if (empty($cancelled)) {
    echo "{$ts} — Nenhum pedido expirado encontrado.\n";
} else {
    $count = count($cancelled);
    $ids   = implode(', ', $cancelled);
    echo "{$ts} — {$count} pedido(s) cancelado(s): IDs {$ids}\n";
    AppLogger::info('Cron: pedidos expirados cancelados', [
        'count' => $count,
        'ids'   => $cancelled,
    ]);
}
