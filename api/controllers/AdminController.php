<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Service;

final class AdminController
{
    private Order    $orderModel;
    private GiftCard $giftCardModel;
    private Service  $serviceModel;

    public function __construct()
    {
        $pdo                  = \Database::getInstance();
        $this->orderModel     = new Order($pdo);
        $this->giftCardModel  = new GiftCard($pdo);
        $this->serviceModel   = new Service($pdo);
    }

    /**
     * GET /api/admin/dashboard
     *
     * Cards de resumo: pedidos hoje, receita do mês, cartões ativos.
     */
    public function dashboard(): never
    {
        $data = [
            'orders_today'        => $this->orderModel->countToday(),
            'revenue_today'       => $this->orderModel->revenueToday(),
            'revenue_this_month'  => $this->orderModel->revenueThisMonth(),
            'active_gift_cards'   => $this->giftCardModel->countActive(),
            'gift_cards_by_status' => $this->giftCardModel->countByStatus(),
            'services_by_category' => $this->serviceModel->countByCategory(),
        ];

        Response::success($data);
    }

    /**
     * GET /api/admin/reports/revenue
     *
     * Faturamento por mês nos últimos N meses.
     * Query params: ?months=12
     */
    public function reportRevenue(): never
    {
        $months = min(24, max(1, (int) ($_GET['months'] ?? 12)));
        $data   = $this->orderModel->revenueByMonth($months);
        Response::success($data);
    }

    /**
     * GET /api/admin/reports/services
     *
     * Top N serviços mais vendidos.
     * Query params: ?limit=10
     */
    public function reportServices(): never
    {
        $limit = min(50, max(5, (int) ($_GET['limit'] ?? 10)));
        $data  = $this->serviceModel->topSelling($limit);
        Response::success($data);
    }
}
