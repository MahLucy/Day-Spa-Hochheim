<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\GiftCard;
use App\Services\GiftCardService;

final class GiftCardController
{
    private GiftCard        $giftCardModel;
    private GiftCardService $giftCardService;

    public function __construct()
    {
        $pdo                    = \Database::getInstance();
        $this->giftCardModel    = new GiftCard($pdo);
        $this->giftCardService  = new GiftCardService($pdo);
    }

    /**
     * GET /api/giftcards/:code
     *
     * Valida o cartão presente (público — usado pela página do QR Code).
     */
    public function validate(string $code): never
    {
        $code = strtoupper(trim($code));

        // Atualiza expirados antes de retornar
        $this->giftCardModel->expireOutdated();

        $card = $this->giftCardModel->findByCode($code);
        if (!$card) {
            Response::notFound('Cartão não encontrado.');
        }

        $items = $this->giftCardModel->getItems((int) $card['id']);

        // Remove dados sensíveis do retorno público
        $publicCard = [
            'code'         => $card['code'],
            'status'       => $card['status'],
            'valid_until'  => $card['valid_until'],
            'redeemed_at'  => $card['redeemed_at'],
            'created_at'   => $card['created_at'],
            'format'       => $card['gift_card_format'],
            'qr_code_url'  => $card['qr_code_path']
                ? GiftCardService::getPublicUrl($card['qr_code_path'])
                : null,
            'items'        => $items,
            'customer_name' => $card['customer_name'],
        ];

        Response::success($publicCard);
    }

    /**
     * GET /api/giftcards/:code/pdf
     *
     * Download do PDF do cartão presente.
     */
    public function downloadPdf(string $code): never
    {
        $code = strtoupper(trim($code));
        $card = $this->giftCardModel->findByCode($code);

        if (!$card) {
            Response::notFound('Cartão não encontrado.');
        }

        if (empty($card['pdf_path'])) {
            Response::notFound('PDF não disponível para este cartão.');
        }

        $storagePath = rtrim(env('STORAGE_PATH', dirname(__DIR__) . '/storage'), '/');
        $pdfFullPath = $storagePath . '/' . $card['pdf_path'];

        if (!file_exists($pdfFullPath)) {
            Response::notFound('Arquivo PDF não encontrado.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="CartaoPresente-' . $card['code'] . '.pdf"');
        header('Content-Length: ' . filesize($pdfFullPath));
        header('Cache-Control: private, no-cache');
        readfile($pdfFullPath);
        exit;
    }

    /**
     * GET /api/orders/:id/giftcard
     *
     * Retorna o cartão presente gerado para um pedido.
     * Usado pela SuccessPage em polling após redirecionamento do Mercado Pago.
     * Público — o ID do pedido já é conhecido pelo comprador via query string.
     */
    public function getByOrderId(int $orderId): never
    {
        $card = $this->giftCardModel->findByOrderId($orderId);

        if (!$card) {
            Response::notFound('Cartão presente ainda não gerado para este pedido.');
        }

        $items = $this->giftCardModel->getItems((int) $card['id']);

        Response::success([
            'code'        => $card['code'],
            'status'      => $card['status'],
            'valid_until' => $card['valid_until'],
            'pdf_url'     => $card['pdf_path']
                ? GiftCardService::getPublicUrl($card['pdf_path'])
                : null,
            'qr_code_url' => $card['qr_code_path']
                ? GiftCardService::getPublicUrl($card['qr_code_path'])
                : null,
            'items'       => $items,
        ]);
    }

    // ─── Admin ──────────────────────────────────────────────────────────────

    /** GET /api/admin/giftcards */
    public function adminIndex(): never
    {
        $filters = [
            'status'        => $_GET['status'] ?? '',
            'code'          => $_GET['code'] ?? '',
            'customer_name' => $_GET['customer_name'] ?? '',
        ];

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $cards = $this->giftCardModel->findAll($filters, $limit, $offset);

        foreach ($cards as &$card) {
            $card['pdf_url']     = $card['pdf_path'] ? GiftCardService::getPublicUrl($card['pdf_path']) : null;
            $card['qr_code_url'] = $card['qr_code_path'] ? GiftCardService::getPublicUrl($card['qr_code_path']) : null;
            $card['items']       = $this->giftCardModel->getItems((int) $card['id']);
        }

        Response::success(['gift_cards' => $cards]);
    }

    /**
     * POST /api/admin/giftcards/:code/redeem
     *
     * Body: { "redeemed_by": "Atendente Nome", "notes": "Opcional" }
     */
    public function redeem(string $code): never
    {
        $code = strtoupper(trim($code));
        $card = $this->giftCardModel->findByCode($code);

        if (!$card) {
            Response::notFound('Cartão não encontrado.');
        }

        if ($card['status'] === 'redeemed') {
            Response::error('Este cartão já foi utilizado em ' . $card['redeemed_at'] . '.', 409);
        }

        if ($card['status'] === 'expired') {
            Response::error('Este cartão está expirado (válido até ' . $card['valid_until'] . ').', 409);
        }

        if ($card['status'] === 'cancelled') {
            Response::error('Este cartão foi cancelado.', 409);
        }

        $body       = Validator::getJsonBody();
        $redeemedBy = Validator::sanitizeString($body['redeemed_by'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'admin'));
        $notes      = Validator::sanitizeString($body['notes'] ?? '');

        $success = $this->giftCardModel->redeem($code, $redeemedBy, $notes);

        if (!$success) {
            Response::error('Não foi possível marcar o cartão como utilizado.', 500);
        }

        AppLogger::adminAction('redeem', 'gift_card', (int) $card['id'], [
            'code'        => $code,
            'redeemed_by' => $redeemedBy,
        ]);

        Response::success(null, 'Cartão marcado como utilizado.');
    }
}
