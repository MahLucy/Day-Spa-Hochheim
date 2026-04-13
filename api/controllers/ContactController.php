<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;

/**
 * Controller de contato.
 *
 * POST /api/contact — recebe formulário de contato do frontend.
 */
final class ContactController
{
    /**
     * POST /api/contact
     *
     * Body esperado:
     * {
     *   "name":    "string (obrigatório)",
     *   "email":   "string (obrigatório, e-mail válido)",
     *   "phone":   "string (opcional)",
     *   "subject": "string (opcional)",
     *   "message": "string (obrigatório)"
     * }
     */
    public function send(): never
    {
        $body = Validator::getJsonBody();

        // Validação
        $errors = [];

        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            $errors['name'] = 'Nome é obrigatório.';
        }

        $email = trim($body['email'] ?? '');
        if (empty($email)) {
            $errors['email'] = 'E-mail é obrigatório.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        $message = trim($body['message'] ?? '');
        if (empty($message)) {
            $errors['message'] = 'Mensagem é obrigatória.';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $phone   = trim($body['phone'] ?? '');
        $subject = trim($body['subject'] ?? 'Contato pelo site');

        // Envia e-mail de contato
        try {
            $emailService = new \App\Services\EmailService();
            $emailService->sendContactForm([
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'subject' => $subject,
                'message' => $message,
            ]);

            AppLogger::info('Formulário de contato enviado', [
                'name'  => $name,
                'email' => $email,
            ]);

            Response::success(null, 'Mensagem enviada com sucesso! Entraremos em contato em breve.');
        } catch (\Throwable $e) {
            AppLogger::error('Falha ao enviar formulário de contato', [
                'error' => $e->getMessage(),
                'name'  => $name,
                'email' => $email,
            ]);

            Response::serverError('Não foi possível enviar sua mensagem. Tente novamente mais tarde.');
        }
    }
}
