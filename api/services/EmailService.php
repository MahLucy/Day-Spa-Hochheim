<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Models\GiftCard;
use App\Models\Order;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;
use PDO;

/**
 * Serviço de e-mail via PHPMailer.
 *
 * Configurado para SMTP do cPanel (ou externo via .env).
 * Suporta anexo de PDF e QR Code inline (CID).
 */
final class EmailService
{
    private PDO      $pdo;
    private Order    $orderModel;
    private GiftCard $giftCardModel;

    public function __construct()
    {
        $this->pdo          = \Database::getInstance();
        $this->orderModel   = new Order($this->pdo);
        $this->giftCardModel = new GiftCard($this->pdo);
    }

    /**
     * Envia e-mail com o cartão presente (digital ou impresso).
     */
    public function sendGiftCard(int $orderId): bool
    {
        $order    = $this->orderModel->findById($orderId);
        $giftCard = $this->giftCardModel->findByOrderId($orderId);

        if (!$order || !$giftCard) {
            throw new \InvalidArgumentException("Pedido ou cartão não encontrado para #$orderId.");
        }

        $items    = $this->giftCardModel->getItems((int) $giftCard['id']);
        $isDigital = $order['gift_card_format'] === 'digital';
        $template  = $isDigital ? 'giftcard-digital' : 'giftcard-printed';

        $mail = $this->createMailer();

        $mail->addAddress($order['customer_email'], $order['customer_name']);
        $mail->Subject = '🎁 Seu Cartão Presente — Day Spa Hochheim';

        // QR Code inline
        $qrCid = '';
        if ($giftCard['qr_code_path']) {
            $qrPath = $this->resolveStoragePath($giftCard['qr_code_path']);
            if (file_exists($qrPath)) {
                $qrCid = 'qrcode_' . $giftCard['code'];
                $mail->addEmbeddedImage($qrPath, $qrCid, 'qrcode.png', 'base64', 'image/png');
            }
        }

        // Corpo HTML
        $mail->isHTML(true);
        $mail->Body = $this->renderTemplate($template, [
            'order'    => $order,
            'giftCard' => $giftCard,
            'items'    => $items,
            'qrCid'    => $qrCid,
        ]);
        $mail->AltBody = $this->buildPlainText($order, $giftCard, $items);

        // Anexa PDF se for digital (e existir)
        if ($isDigital && $giftCard['pdf_path']) {
            $pdfPath = $this->resolveStoragePath($giftCard['pdf_path']);
            if (file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath, "CartaoPresente-{$giftCard['code']}.pdf");
            }
        }

        try {
            $mail->send();
            AppLogger::info('E-mail de gift card enviado', [
                'order_id' => $orderId,
                'email'    => $order['customer_email'],
            ]);
            return true;
        } catch (MailException $e) {
            AppLogger::error('Falha ao enviar e-mail de gift card', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Falha ao enviar e-mail: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Envia confirmação de pedido (antes do pagamento).
     */
    public function sendOrderConfirmation(int $orderId): bool
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #$orderId não encontrado.");
        }

        $items = $this->orderModel->getItems($orderId);
        $mail  = $this->createMailer();

        $mail->addAddress($order['customer_email'], $order['customer_name']);
        $mail->Subject = 'Pedido recebido — Day Spa Hochheim';
        $mail->isHTML(true);
        $mail->Body    = $this->renderTemplate('order-confirmation', [
            'order' => $order,
            'items' => $items,
        ]);

        try {
            $mail->send();
            return true;
        } catch (MailException $e) {
            AppLogger::error('Falha ao enviar confirmação de pedido', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envia e-mail informando falha/rejeição do pagamento.
     */
    public function sendPaymentFailed(array $order): bool
    {
        $mail = $this->createMailer();
        $mail->addAddress($order['customer_email'], $order['customer_name']);
        $mail->Subject = 'Pagamento não aprovado — Day Spa Hochheim';
        $mail->isHTML(true);
        $mail->Body    = $this->renderTemplate('payment-failed', ['order' => $order]);

        try {
            $mail->send();
            return true;
        } catch (MailException $e) {
            AppLogger::error('Falha ao enviar e-mail de pagamento recusado', [
                'order_id' => $order['id'],
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envia formulário de contato para o e-mail da clínica.
     */
    public function sendContactForm(array $data): bool
    {
        $mail = $this->createMailer();

        // Envia para o e-mail da clínica
        $clinicEmail = env('MAIL_USER', 'contato@hochheim.com.br');
        $mail->addAddress($clinicEmail, 'Day Spa Hochheim');

        // Reply-to do visitante
        $mail->addReplyTo($data['email'], $data['name']);

        $mail->Subject = "Contato pelo site: {$data['subject']}";
        $mail->isHTML(true);

        $phone = !empty($data['phone']) ? $data['phone'] : 'Não informado';

        $mail->Body = "
            <div style='font-family: Poppins, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px;'>
                <h2 style='color: #004567; margin-bottom: 24px;'>Nova mensagem de contato</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #666; width: 120px;'><strong>Nome:</strong></td>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #333;'>{$data['name']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #666;'><strong>E-mail:</strong></td>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #333;'><a href='mailto:{$data['email']}'>{$data['email']}</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #666;'><strong>Telefone:</strong></td>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #333;'>{$phone}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #666;'><strong>Assunto:</strong></td>
                        <td style='padding: 10px 0; border-bottom: 1px solid #eee; color: #333;'>{$data['subject']}</td>
                    </tr>
                </table>
                <div style='margin-top: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px;'>
                    <p style='color: #666; margin: 0 0 8px; font-size: 14px;'><strong>Mensagem:</strong></p>
                    <p style='color: #333; margin: 0; white-space: pre-wrap;'>" . nl2br(htmlspecialchars($data['message'])) . "</p>
                </div>
            </div>
        ";

        $mail->AltBody = implode("\n", [
            "Nova mensagem de contato",
            "",
            "Nome: {$data['name']}",
            "E-mail: {$data['email']}",
            "Telefone: {$phone}",
            "Assunto: {$data['subject']}",
            "",
            "Mensagem:",
            $data['message'],
        ]);

        try {
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            AppLogger::error('Falha ao enviar formulário de contato', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Falha ao enviar e-mail de contato: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        // NUNCA usar DEBUG_SERVER em produção ou desenvolvimento:
        // ele imprime texto de debug no stdout e corrompe a resposta JSON.
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        $mail->isSMTP();
        $mail->Host = env('MAIL_HOST', 'localhost');
        $mail->Port = (int) env('MAIL_PORT', 587);

        $mailUser = env('MAIL_USER', '');
        $mailPass = env('MAIL_PASS', '');

        // Mailhog e outros servidores locais não exigem autenticação
        if (!empty($mailUser)) {
            $mail->SMTPAuth = true;
            $mail->Username = $mailUser;
            $mail->Password = $mailPass;
        } else {
            $mail->SMTPAuth = false;
        }

        $encryption = strtolower(env('MAIL_ENCRYPTION', ''));
        $mail->SMTPSecure = match ($encryption) {
            'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
            'tls'   => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };

        $mail->setFrom(
            env('MAIL_USER', 'noreply@hochheim.com.br'),
            env('MAIL_FROM_NAME', 'Day Spa Hochheim')
        );

        return $mail;
    }

    private function renderTemplate(string $name, array $data = []): string
    {
        $templatePath = dirname(__DIR__) . "/templates/emails/{$name}.php";
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template de e-mail não encontrado: {$name}");
        }

        extract($data);
        ob_start();
        require $templatePath;
        return ob_get_clean();
    }

    private function buildPlainText(array $order, array $giftCard, array $items): string
    {
        $lines = [
            "Olá {$order['customer_name']},",
            '',
            'Seu cartão presente do Day Spa Hochheim está pronto!',
            '',
            "Código: {$giftCard['code']}",
            "Válido até: " . date('d/m/Y', strtotime($giftCard['valid_until'])),
            '',
            'Serviços incluídos:',
        ];

        foreach ($items as $item) {
            $lines[] = "- {$item['service_name']} x{$item['quantity']} ({$item['service_duration']} min)";
        }

        $lines[] = '';
        $lines[] = 'Apresente este código ao chegar no spa.';
        $lines[] = 'Day Spa Hochheim — (47) 3037-1707';

        return implode("\n", $lines);
    }

    private function resolveStoragePath(string $relativePath): string
    {
        $base = rtrim(env('STORAGE_PATH', dirname(__DIR__) . '/storage'), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }
}
