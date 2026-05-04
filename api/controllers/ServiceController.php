<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\Service;
use App\Services\StorageService;

final class ServiceController
{
    private Service        $model;
    private StorageService $storage;

    public function __construct()
    {
        $pdo           = \Database::getInstance();
        $this->model   = new Service($pdo);
        $this->storage = new StorageService();
    }

    /** GET /api/services */
    public function index(): never
    {
        $services = $this->model->findAll(activeOnly: true);

        // Adiciona URL pública da imagem
        foreach ($services as &$service) {
            $service['image_url'] = $service['image_path']
                ? $this->storage->publicUrl($service['image_path'])
                : null;
        }

        Response::success($services);
    }

    /** GET /api/services/:id */
    public function show(int $id): never
    {
        $service = $this->model->findById($id);
        if (!$service) {
            Response::notFound('Serviço não encontrado.');
        }

        $service['image_url'] = $service['image_path']
            ? $this->storage->publicUrl($service['image_path'])
            : null;

        Response::success($service);
    }

    // ─── Admin ──────────────────────────────────────────────────────────────

    /** GET /api/admin/services */
    public function adminIndex(): never
    {
        $services = $this->model->findAll(activeOnly: false);
        foreach ($services as &$service) {
            $service['image_url'] = $service['image_path']
                ? $this->storage->publicUrl($service['image_path'])
                : null;
        }
        Response::success($services);
    }

    /** POST /api/admin/services */
    public function create(): never
    {
        $data      = Validator::getJsonBody();
        $validator = new Validator();

        $validator->validate($data, [
            'name'             => 'required|string|min:2|max:255',
            'category'         => 'required|in:Spa,Massagem,Massagem Spa Terapia,Drenagem Linfática,Banho,Cuidados Especiais,Depilação',
            'price'            => 'required|decimal',
            'duration_minutes' => 'required|int',
            'sessions'         => 'int',
        ]);

        if ($validator->hasErrors()) {
            Response::validationError($validator->getErrors());
        }

        $id = $this->model->create($data);
        AppLogger::adminAction('create', 'service', $id, ['name' => $data['name']]);

        Response::created(['id' => $id], 'Serviço criado com sucesso.');
    }

    /** PUT /api/admin/services/:id */
    public function update(int $id): never
    {
        $service = $this->model->findById($id);
        if (!$service) {
            Response::notFound('Serviço não encontrado.');
        }

        $data      = Validator::getJsonBody();
        $validator = new Validator();
        $validator->validate($data, [
            'name'     => 'string|min:2|max:255',
            'category' => 'in:Spa,Massagem,Massagem Spa Terapia,Drenagem Linfática,Banho,Cuidados Especiais,Depilação',
            'price'    => 'decimal',
        ]);

        if ($validator->hasErrors()) {
            Response::validationError($validator->getErrors());
        }

        $this->model->update($id, $data);
        AppLogger::adminAction('update', 'service', $id, $data);

        Response::success(null, 'Serviço atualizado.');
    }

    /** DELETE /api/admin/services/:id  (soft-delete) */
    public function delete(int $id): never
    {
        $service = $this->model->findById($id);
        if (!$service) {
            Response::notFound('Serviço não encontrado.');
        }

        $this->model->deactivate($id);
        AppLogger::adminAction('deactivate', 'service', $id);

        Response::success(null, 'Serviço desativado.');
    }

    /** POST /api/admin/services/:id/image */
    public function uploadImage(int $id): never
    {
        $service = $this->model->findById($id);
        if (!$service) {
            Response::notFound('Serviço não encontrado.');
        }

        if (empty($_FILES['image'])) {
            AppLogger::warning('Upload sem arquivo', [
                'files_keys'   => array_keys($_FILES),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'N/A',
                'file_uploads' => ini_get('file_uploads'),
                'post_max'     => ini_get('post_max_size'),
                'upload_max'   => ini_get('upload_max_filesize'),
                'content_len'  => $_SERVER['CONTENT_LENGTH'] ?? 'N/A',
            ]);
            Response::error('Nenhuma imagem enviada.', 400);
        }

        try {
            // Remove imagem antiga
            if ($service['image_path']) {
                $this->storage->delete($service['image_path']);
            }

            $relativePath = $this->storage->saveServiceImage(
                $_FILES['image'],
                'service_' . $id
            );

            $this->model->updateImagePath($id, $relativePath);
            AppLogger::adminAction('upload_image', 'service', $id);

            Response::success(
                ['image_url' => $this->storage->publicUrl($relativePath)],
                'Imagem atualizada.'
            );
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao fazer upload de imagem', [
                'error'        => $e->getMessage(),
                'storage_path' => $this->storage->getStoragePath(),
            ]);
            Response::serverError('Erro ao salvar imagem: ' . $e->getMessage());
        }
    }
}
