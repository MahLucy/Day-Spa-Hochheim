<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;

/**
 * Gerencia upload e acesso a arquivos no storage local do cPanel.
 *
 * Estrutura de diretórios:
 *   storage/
 *     qrcodes/   — PNGs dos QR Codes
 *     pdfs/      — PDFs dos cartões
 *     invoices/  — PDFs e XMLs de notas fiscais
 *     images/    — Imagens dos serviços
 *     logs/      — Logs da aplicação
 */
final class StorageService
{
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_IMAGE_SIZE      = 10 * 1024 * 1024; // 10 MB

    public function getStoragePath(): string
    {
        return rtrim(env('STORAGE_PATH', dirname(__DIR__) . '/storage'), '/');
    }

    public function getStorageUrl(): string
    {
        return rtrim(env('STORAGE_URL', 'https://hochheim.com.br/storage'), '/');
    }

    /**
     * Salva imagem de serviço enviada via upload.
     *
     * @param  array  $file   Elemento de $_FILES
     * @param  string $prefix Prefixo para o nome do arquivo
     * @return string         Caminho relativo salvo no banco
     */
    public function saveServiceImage(array $file, string $prefix = 'service'): string
    {
        $this->validateUpload($file);

        $original = pathinfo($file['name'], PATHINFO_FILENAME);
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $original);
        $filename = $safe . '.' . $ext;
        $dir      = $this->ensureDir('images/services');
        $fullPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Falha ao mover arquivo enviado.');
        }

        AppLogger::info('Imagem de serviço salva', ['filename' => $filename]);

        return 'images/services/' . $filename;
    }

    /**
     * Salva conteúdo binário em um subdiretório do storage.
     *
     * @param  string $content  Conteúdo binário
     * @param  string $subdir   Subdiretório (ex: 'invoices')
     * @param  string $filename Nome do arquivo
     * @return string           Caminho relativo
     */
    public function saveContent(string $content, string $subdir, string $filename): string
    {
        $dir      = $this->ensureDir($subdir);
        $fullPath = $dir . '/' . $filename;

        if (file_put_contents($fullPath, $content) === false) {
            throw new \RuntimeException("Falha ao salvar arquivo {$filename} em {$subdir}.");
        }

        return $subdir . '/' . $filename;
    }

    /**
     * Lê o conteúdo de um arquivo de storage.
     *
     * @throws \RuntimeException se arquivo não existir
     */
    public function readFile(string $relativePath): string
    {
        $fullPath = $this->getStoragePath() . '/' . ltrim($relativePath, '/');
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Arquivo não encontrado: {$relativePath}");
        }
        return file_get_contents($fullPath);
    }

    /**
     * Retorna a URL pública de um caminho relativo de storage.
     */
    public function publicUrl(string $relativePath): string
    {
        return $this->getStorageUrl() . '/' . ltrim($relativePath, '/');
    }

    /**
     * Deleta um arquivo de storage.
     */
    public function delete(string $relativePath): bool
    {
        $fullPath = $this->getStoragePath() . '/' . ltrim($relativePath, '/');
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function validateUpload(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande.',
                UPLOAD_ERR_PARTIAL => 'Upload incompleto.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
                default            => 'Erro no upload.',
            };
            throw new \InvalidArgumentException($msg);
        }

        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            throw new \InvalidArgumentException('Imagem muito grande. Máximo: 5 MB.');
        }

        $mime = $this->detectMimeType($file['tmp_name']);

        if (!in_array($mime, self::ALLOWED_IMAGE_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Tipo de arquivo não permitido: {$mime}. Use JPEG, PNG ou WebP."
            );
        }
    }

    private function detectMimeType(string $tmpPath): string
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmpPath);
            if ($mime !== false) {
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpPath);
            if ($mime !== false) {
                return $mime;
            }
        }

        $info = @getimagesize($tmpPath);
        if ($info !== false && isset($info['mime'])) {
            return $info['mime'];
        }

        return 'application/octet-stream';
    }

    private function ensureDir(string $subdir): string
    {
        $dir = $this->getStoragePath() . '/' . ltrim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Não foi possível criar diretório: {$dir}");
        }
        return $dir;
    }
}
