<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\Invoice;
use App\Services\InvoiceService;

final class InvoiceController
{
    private Invoice        $invoiceModel;
    private InvoiceService $invoiceService;

    public function __construct()
    {
        $pdo                   = \Database::getInstance();
        $this->invoiceModel    = new Invoice($pdo);
        $this->invoiceService  = new InvoiceService($pdo);
    }

    /** GET /api/admin/invoices */
    public function adminIndex(): never
    {
        $filters = [
            'status'    => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
        ];

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $invoices = $this->invoiceModel->findAll($filters, $limit, $offset);

        foreach ($invoices as &$invoice) {
            $invoice['pdf_url'] = $invoice['pdf_path']
                ? rtrim(env('STORAGE_URL', ''), '/') . '/' . $invoice['pdf_path']
                : null;
            $invoice['xml_url'] = $invoice['xml_path']
                ? rtrim(env('STORAGE_URL', ''), '/') . '/' . $invoice['xml_path']
                : null;
        }

        Response::success(['invoices' => $invoices]);
    }

    /** GET /api/admin/invoices/:id/status — Consulta status na Focus NFe */
    public function consultStatus(int $id): never
    {
        $invoice = $this->invoiceModel->findById($id);
        if (!$invoice) {
            Response::notFound('Nota fiscal não encontrada.');
        }

        try {
            $result = $this->invoiceService->consultStatus($id);

            if ($result === null) {
                Response::error('Não foi possível consultar o status (referência Focus NFe ausente ou token não configurado).', 422);
            }

            Response::success($result);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao consultar status NFS-e', [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            Response::serverError('Falha ao consultar status: ' . $e->getMessage());
        }
    }

    /** POST /api/admin/invoices/:id/reissue */
    public function reissue(int $id): never
    {
        $invoice = $this->invoiceModel->findById($id);
        if (!$invoice) {
            Response::notFound('Nota fiscal não encontrada.');
        }

        try {
            $result = $this->invoiceService->reissue((int) $invoice['order_id']);

            AppLogger::adminAction('reissue_invoice', 'invoice', $id, [
                'order_id' => $invoice['order_id'],
            ]);

            Response::success(
                $result,
                $result ? 'Nota fiscal reenviada para emissão.' : 'Reemissão iniciada (verifique em instantes).'
            );
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao reemitir nota fiscal', [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            Response::serverError('Falha ao reemitir nota fiscal: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/invoices/webhook   (Focus NFe webhook — público, sem admin auth)
     * Configurar a URL no painel Focus NFe como gatilho de notificação.
     */
    public function focusnfeWebhook(): never
    {
        $payload = Validator::getJsonBody();

        try {
            $processed = $this->invoiceService->processWebhook($payload);
            Response::success(['processed' => $processed]);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao processar webhook Focus NFe', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }
}
