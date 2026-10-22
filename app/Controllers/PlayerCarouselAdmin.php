<?php

namespace App\Controllers;

use App\Models\PlayerCarouselSlidesModel;

class PlayerCarouselAdmin extends BaseController
{
    private const MAX_SLIDES = 10;
    private const UPLOAD_DIRECTORY = 'assets/player/carousel/';

    private function guardSuperadmin()
    {
        if (! session()->get('logueado') || ! session()->get('superadmin')) {
            return redirect()->to(base_url('abmAdmin'))->with('msg', ['type' => 'danger', 'body' => 'No tenés permisos para administrar las imágenes del portal.']);
        }
        return null;
    }

    private function model(): PlayerCarouselSlidesModel
    {
        return new PlayerCarouselSlidesModel();
    }

    private function tableExists(): bool
    {
        return \Config\Database::connect()->tableExists('player_carousel_slides');
    }

    public function index()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $model = $this->model();
        if (! $this->tableExists()) {
            return view('player_carousel_admin/index', ['slides' => [], 'categories' => config('PlayerCarousel')->categories(), 'migrationMissing' => true]);
        }
        $config = config('PlayerCarousel');
        return view('player_carousel_admin/index', ['slides' => $model->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll(), 'categories' => $config->categories(), 'migrationMissing' => false]);
    }

    public function upload()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $model = $this->model();
        if (! $this->tableExists()) return $this->back('Primero ejecutá la migración del portal de imágenes.');
        if ($model->countAllResults() >= self::MAX_SLIDES) return $this->back('Alcanzaste el máximo de 10 imágenes. Eliminá una antes de agregar otra.');

        $file = $this->request->getFile('carousel_image');
        $rules = ['carousel_image' => 'uploaded[carousel_image]|is_image[carousel_image]|mime_in[carousel_image,image/jpg,image/jpeg,image/png,image/webp]|max_size[carousel_image,10000]|max_dims[carousel_image,8000,8000]'];
        if (! $this->validate($rules) || ! $file || ! $file->isValid()) {
            return $this->back('La imagen no es válida o supera el límite permitido de 8000 × 8000 píxeles y 10 MB. Usá un archivo JPG, PNG o WebP.');
        }

        $category = trim((string) $this->request->getPost('category'));
        $alt = trim((string) $this->request->getPost('alt_text'));
        $order = max(1, min(self::MAX_SLIDES, (int) $this->request->getPost('sort_order')));
        if (! in_array($category, config('PlayerCarousel')->categories(), true)) return $this->back('Seleccioná una categoría válida.');
        if ($alt === '') $alt = $category;
        $filename = $file->getRandomName();
        $directory = FCPATH . self::UPLOAD_DIRECTORY;
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        if (! $file->move($directory, $filename)) return $this->back('No se pudo guardar la imagen.');
        $now = date('Y-m-d H:i:s');
        $model->insert(['image_path' => self::UPLOAD_DIRECTORY . $filename, 'category' => $category, 'alt_text' => $alt, 'sort_order' => $order, 'active' => $this->request->getPost('active') ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
        return redirect()->to(base_url('admin/carrusel'))->with('msg', ['type' => 'success', 'body' => 'Imagen agregada al carrusel.']);
    }

    public function toggle(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $model = $this->model();
        $slide = $model->find($id);
        if ($slide) $model->update($id, ['active' => (int) ! $slide['active'], 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->to(base_url('admin/carrusel'));
    }

    public function delete(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $model = $this->model();
        $slide = $model->find($id);
        if ($slide) {
            $path = FCPATH . ltrim($slide['image_path'], '/');
            if (is_file($path)) @unlink($path);
            $model->delete($id);
            $this->normalizeOrder($model);
        }
        return redirect()->to(base_url('admin/carrusel'))->with('msg', ['type' => 'success', 'body' => 'Imagen eliminada del carrusel.']);
    }

    private function normalizeOrder(PlayerCarouselSlidesModel $model): void
    {
        $slides = $model->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        foreach ($slides as $index => $slide) {
            $order = $index + 1;
            if ((int) $slide['sort_order'] !== $order) {
                $model->update($slide['id'], ['sort_order' => $order, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    private function back(string $message)
    {
        return redirect()->to(base_url('admin/carrusel'))->with('msg', ['type' => 'danger', 'body' => $message]);
    }
}
