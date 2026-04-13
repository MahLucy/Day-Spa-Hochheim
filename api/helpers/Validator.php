<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Validação de inputs da API.
 */
final class Validator
{
    private array $errors = [];

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Valida CPF (algoritmo dígito verificador). */
    public static function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /** Formata CPF para armazenamento (XXX.XXX.XXX-XX). */
    public static function formatCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    /** Sanitiza CPF — remove qualquer coisa que não seja dígito. */
    public static function sanitizeCpf(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf);
    }

    /** Valida e-mail básico. */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Converte data DD/MM/AAAA para YYYY-MM-DD.
     * Se já estiver em formato ISO ou for inválida, retorna o valor original
     * ou null (deixa o banco/validador lidar).
     */
    public static function dateToIso(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        // Se já estiver no formato YYYY-MM-DD, retorna direto
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Tenta converter DD/MM/AAAA
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        return $date;
    }

    /** Valida telefone brasileiro (apenas dígitos, 10 ou 11 chars). */
    public static function validatePhone(string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 10 && strlen($digits) <= 11;
    }

    /**
     * Valida um array de dados contra regras.
     *
     * Regras suportadas: required, string, int, decimal, email, cpf, phone,
     *                    min:N, max:N, in:a,b,c, date
     *
     * @param  array<string, mixed> $data
     * @param  array<string, string> $rules  ['campo' => 'required|string|min:2']
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleStr) {
            $fieldRules = explode('|', $ruleStr);
            $value      = $data[$field] ?? null;
            $present    = array_key_exists($field, $data);

            foreach ($fieldRules as $rule) {
                [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

                if ($ruleName === 'required') {
                    if (!$present || $value === null || $value === '') {
                        $this->errors[$field][] = "O campo '{$field}' é obrigatório.";
                    }
                    continue;
                }

                // Pula validações se o campo não foi enviado e não é required
                if (!$present || $value === null || $value === '') {
                    continue;
                }

                match ($ruleName) {
                    'string'  => is_string($value) ?: $this->errors[$field][] = "'{$field}' deve ser texto.",
                    'int'     => is_numeric($value) && (int) $value == $value
                                    ?: $this->errors[$field][] = "'{$field}' deve ser inteiro.",
                    'decimal' => is_numeric($value)
                                    ?: $this->errors[$field][] = "'{$field}' deve ser decimal.",
                    'email'   => self::validateEmail((string) $value)
                                    ?: $this->errors[$field][] = "'{$field}' deve ser um e-mail válido.",
                    'cpf'     => self::validateCpf((string) $value)
                                    ?: $this->errors[$field][] = "CPF inválido.",
                    'phone'   => self::validatePhone((string) $value)
                                    ?: $this->errors[$field][] = "Telefone inválido.",
                    'date'    => (bool) strtotime((string) $value)
                                    ?: $this->errors[$field][] = "'{$field}' deve ser uma data válida.",
                    'min'     => strlen((string) $value) >= (int) $ruleParam
                                    ?: $this->errors[$field][] = "'{$field}' deve ter no mínimo {$ruleParam} caracteres.",
                    'max'     => strlen((string) $value) <= (int) $ruleParam
                                    ?: $this->errors[$field][] = "'{$field}' deve ter no máximo {$ruleParam} caracteres.",
                    'in'      => in_array($value, explode(',', $ruleParam ?? ''), true)
                                    ?: $this->errors[$field][] = "'{$field}' deve ser um dos valores: {$ruleParam}.",
                    default   => null,
                };
            }
        }

        return !$this->hasErrors();
    }

    /** Sanitiza string para uso seguro. */
    public static function sanitizeString(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /** Retorna body JSON decodificado ou termina com erro 400. */
    public static function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error('Body JSON inválido: ' . json_last_error_msg(), 400);
        }
        return $decoded ?? [];
    }
}
