<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Helpers\CodeGenerator;
use App\Models\GiftCard;
use App\Models\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use PDO;

/**
 * Gera QR Code (PNG) e PDF do cartão presente.
 *
 * Fluxo:
 * 1. Gera código único SPA-XXXXXX
 * 2. Cria registro gift_cards + gift_card_items (snapshot)
 * 3. Gera QR Code PNG salvo em storage/qrcodes/
 * 4. Gera PDF salvo em storage/pdfs/
 * 5. Atualiza paths no banco
 */
final class GiftCardService
{
    private GiftCard $giftCardModel;
    private Order    $orderModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->giftCardModel = new GiftCard($pdo);
        $this->orderModel    = new Order($pdo);
    }

    /**
     * Gera o cartão presente completo para um pedido pago.
     *
     * @throws \RuntimeException
     */
    public function generate(int $orderId): array
    {
        // Verifica se já existe
        $existing = $this->giftCardModel->findByOrderId($orderId);
        if ($existing) {
            AppLogger::info('Gift card já existe para o pedido', ['order_id' => $orderId]);
            return $existing;
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }

        $items = $this->orderModel->getItems($orderId);
        if (empty($items)) {
            throw new \RuntimeException("Pedido #{$orderId} sem itens.");
        }

        // Gera código único
        $code = CodeGenerator::generateGiftCardCode($this->pdo);

        // Validade: 6 meses corridos
        $validUntil = new \DateTimeImmutable('+6 months');

        // Cria registro no banco
        $giftCardId = $this->giftCardModel->create($orderId, $code, $validUntil);

        // Snapshot dos serviços (imutável)
        foreach ($items as $item) {
            $snapshot = $item['service_snapshot'];
            $this->giftCardModel->createItem($giftCardId, [
                'service_name'     => $snapshot['name'],
                'service_duration' => $snapshot['duration_minutes'],
                'service_category' => $snapshot['category'],
                'quantity'         => $item['quantity'],
            ]);
        }

        // Gera QR Code
        $qrCodePath = null;
        try {
            $qrCodePath = $this->generateQrCode($code);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao gerar QR Code', ['code' => $code, 'error' => $e->getMessage()]);
        }

        // Gera PDF
        $pdfPath = null;
        try {
            $pdfPath = $this->generatePdf($giftCardId, $code, $order, $items, $validUntil, $qrCodePath);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao gerar PDF', ['code' => $code, 'error' => $e->getMessage()]);
        }

        // Atualiza paths
        $this->giftCardModel->updatePaths($giftCardId, $qrCodePath, $pdfPath);

        AppLogger::info('Gift card gerado', [
            'order_id'     => $orderId,
            'code'         => $code,
            'gift_card_id' => $giftCardId,
        ]);

        return $this->giftCardModel->findById($giftCardId);
    }

    // ─── QR Code ────────────────────────────────────────────────────────────

    private function generateQrCode(string $code): string
    {
        $url        = env('APP_URL', 'https://hochheim.com.br') . "/validar/{$code}";
        $storageDir = $this->getStorageDir('qrcodes');

        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        $filename = "{$code}.png";
        $fullPath = $storageDir . '/' . $filename;
        $result->saveToFile($fullPath);

        return 'qrcodes/' . $filename;
    }

    // ─── PDF ────────────────────────────────────────────────────────────────

    private function generatePdf(
        int                  $giftCardId,
        string               $code,
        array                $order,
        array                $items,
        \DateTimeImmutable   $validUntil,
        ?string              $qrCodeRelPath
    ): string {
        $giftCardItems = $this->giftCardModel->getItems($giftCardId);

        // Carrega QR Code como base64 para incorporar inline no PDF
        $qrCodeBase64 = '';
        if ($qrCodeRelPath) {
            $qrFullPath = $this->getStoragePath() . '/' . $qrCodeRelPath;
            if (file_exists($qrFullPath)) {
                $qrCodeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qrFullPath));
            }
        }

        // Renderiza template HTML
        ob_start();
        $templateData = compact(
            'code', 'order', 'items', 'giftCardItems', 'validUntil', 'qrCodeBase64'
        );
        extract($templateData);
        require dirname(__DIR__) . '/templates/pdf/giftcard.php';
        $html = ob_get_clean();

        // Gera PDF com Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $options->set('chroot', $this->getStoragePath());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        $storageDir = $this->getStorageDir('pdfs');
        $filename   = "{$code}.pdf";
        $fullPath   = $storageDir . '/' . $filename;

        file_put_contents($fullPath, $dompdf->output());

        return 'pdfs/' . $filename;
    }

    // ─── Storage ────────────────────────────────────────────────────────────

    private function getStoragePath(): string
    {
        return rtrim(env('STORAGE_PATH', dirname(__DIR__) . '/storage'), '/');
    }

    private function getStorageDir(string $subdir): string
    {
        $dir = $this->getStoragePath() . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Retorna a URL pública de um arquivo de storage.
     */
    public static function getPublicUrl(string $relativePath): string
    {
        $base = rtrim(env('STORAGE_URL', 'https://hochheim.com.br/storage'), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }
}
