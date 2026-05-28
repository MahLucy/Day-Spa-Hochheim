<?php

declare(strict_types=1);

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

/**
 * Roteador central da API.
 *
 * Recebe $method e $uri (sem o prefixo /api) do index.php.
 * Suporta parâmetros dinâmicos :id, :code, etc.
 *
 * Rotas públicas:  não exigem autenticação
 * Rotas admin:     exigem AuthMiddleware::requireAdmin()
 */
function dispatchRoute(string $method, string $uri): void
{
    // ─── Rotas públicas ───────────────────────────────────────────────────────

    // GET /services
    if ($method === 'GET' && $uri === '/services') {
        (new \App\Controllers\ServiceController())->index();
    }

    // GET /services/:id
    if ($method === 'GET' && preg_match('#^/services/(\d+)$#', $uri, $m)) {
        (new \App\Controllers\ServiceController())->show((int) $m[1]);
    }

    // POST /orders
    if ($method === 'POST' && $uri === '/orders') {
        (new \App\Controllers\OrderController())->create();
    }

    // POST /payments/create-preference
    if ($method === 'POST' && $uri === '/payments/create-preference') {
        (new \App\Controllers\PaymentController())->createPreference();
    }

    // POST /payments/webhook
    if ($method === 'POST' && $uri === '/payments/webhook') {
        (new \App\Controllers\PaymentController())->webhook();
    }

    // POST /payments/process  (Checkout Bricks — pagamento transparente)
    if ($method === 'POST' && $uri === '/payments/process') {
        (new \App\Controllers\PaymentController())->processPayment();
    }

    // POST /payments/mock-approve  (somente APP_ENV=development)
    if ($method === 'POST' && $uri === '/payments/mock-approve') {
        (new \App\Controllers\PaymentController())->mockApprove();
    }

    // POST /invoices/webhook  (Focus NFe callback — configurar como gatilho no painel)
    if ($method === 'POST' && $uri === '/invoices/webhook') {
        (new \App\Controllers\InvoiceController())->focusnfeWebhook();
    }

    // POST /contact
    if ($method === 'POST' && $uri === '/contact') {
        (new \App\Controllers\ContactController())->send();
    }

    // GET /giftcards/:code
    if ($method === 'GET' && preg_match('#^/giftcards/([A-Z0-9\-]+)$#i', $uri, $m)) {
        $code = strtoupper($m[1]);
        if (!str_ends_with($code, '/PDF')) {
            (new \App\Controllers\GiftCardController())->validate($code);
        }
    }

    // GET /giftcards/:code/pdf
    if ($method === 'GET' && preg_match('#^/giftcards/([A-Z0-9\-]+)/pdf$#i', $uri, $m)) {
        (new \App\Controllers\GiftCardController())->downloadPdf(strtoupper($m[1]));
    }

    // GET /orders/:id/giftcard  (usado pela SuccessPage para buscar o código após pagamento)
    if ($method === 'GET' && preg_match('#^/orders/(\d+)/giftcard$#', $uri, $m)) {
        (new \App\Controllers\GiftCardController())->getByOrderId((int) $m[1]);
    }

    // ─── Rotas admin ─────────────────────────────────────────────────────────

    if (str_starts_with($uri, '/admin')) {
        AuthMiddleware::requireAdmin();
        dispatchAdminRoute($method, $uri);
    }

    // Rota não encontrada
    Response::notFound("Rota não encontrada: {$method} {$uri}");
}

function dispatchAdminRoute(string $method, string $uri): void
{
    $adminUri = substr($uri, strlen('/admin')); // remove o prefixo /admin

    // Dashboard
    if ($method === 'GET' && $adminUri === '/dashboard') {
        (new \App\Controllers\AdminController())->dashboard();
    }

    // ── Serviços ──────────────────────────────────────────────────────────────

    if ($method === 'GET' && $adminUri === '/services') {
        (new \App\Controllers\ServiceController())->adminIndex();
    }

    if ($method === 'POST' && $adminUri === '/services') {
        (new \App\Controllers\ServiceController())->create();
    }

    if ($method === 'PUT' && preg_match('#^/services/(\d+)$#', $adminUri, $m)) {
        (new \App\Controllers\ServiceController())->update((int) $m[1]);
    }

    if ($method === 'DELETE' && preg_match('#^/services/(\d+)$#', $adminUri, $m)) {
        (new \App\Controllers\ServiceController())->delete((int) $m[1]);
    }

    if ($method === 'POST' && preg_match('#^/services/(\d+)/image$#', $adminUri, $m)) {
        (new \App\Controllers\ServiceController())->uploadImage((int) $m[1]);
    }

    // ── Pedidos ───────────────────────────────────────────────────────────────

    if ($method === 'GET' && $adminUri === '/orders') {
        (new \App\Controllers\OrderController())->adminIndex();
    }

    if ($method === 'GET' && preg_match('#^/orders/(\d+)$#', $adminUri, $m)) {
        (new \App\Controllers\OrderController())->adminShow((int) $m[1]);
    }

    if ($method === 'POST' && preg_match('#^/orders/(\d+)/resend-email$#', $adminUri, $m)) {
        (new \App\Controllers\OrderController())->resendEmail((int) $m[1]);
    }

    if ($method === 'POST' && $adminUri === '/orders/expire-pending') {
        (new \App\Controllers\AdminController())->expirePending();
    }

    // ── Cartões ───────────────────────────────────────────────────────────────

    if ($method === 'GET' && $adminUri === '/giftcards') {
        (new \App\Controllers\GiftCardController())->adminIndex();
    }

    if ($method === 'POST' && preg_match('#^/giftcards/([A-Z0-9\-]+)/redeem$#i', $adminUri, $m)) {
        (new \App\Controllers\GiftCardController())->redeem(strtoupper($m[1]));
    }

    // ── Notas Fiscais ─────────────────────────────────────────────────────────

    if ($method === 'GET' && $adminUri === '/invoices') {
        (new \App\Controllers\InvoiceController())->adminIndex();
    }

    if ($method === 'DELETE' && preg_match('#^/invoices/(\d+)$#', $adminUri, $m)) {
        (new \App\Controllers\InvoiceController())->cancel((int) $m[1]);
    }

    if ($method === 'POST' && preg_match('#^/invoices/(\d+)/reissue$#', $adminUri, $m)) {
        (new \App\Controllers\InvoiceController())->reissue((int) $m[1]);
    }

    if ($method === 'GET' && preg_match('#^/invoices/(\d+)/status$#', $adminUri, $m)) {
        (new \App\Controllers\InvoiceController())->consultStatus((int) $m[1]);
    }

    // ── Relatórios ────────────────────────────────────────────────────────────

    if ($method === 'GET' && $adminUri === '/reports/revenue') {
        (new \App\Controllers\AdminController())->reportRevenue();
    }

    if ($method === 'GET' && $adminUri === '/reports/services') {
        (new \App\Controllers\AdminController())->reportServices();
    }

    // Rota admin não encontrada
    \App\Helpers\Response::notFound("Rota admin não encontrada: {$method} /admin{$adminUri}");
}
