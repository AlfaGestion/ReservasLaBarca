<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class PlayerCarousel extends BaseConfig
{
    public $slides = [
        ['image' => 'assets/player/carousel/futbol.jpg', 'category' => 'Fútbol', 'alt' => 'Cancha de fútbol ilustrativa', 'order' => 1, 'active' => true],
        ['image' => 'assets/player/carousel/padel.jpg', 'category' => 'Pádel', 'alt' => 'Cancha de pádel cubierta ilustrativa', 'order' => 2, 'active' => true],
        ['image' => 'assets/player/carousel/quincho.jpg', 'category' => 'Quincho', 'alt' => 'Parrilla de un quincho ilustrativo', 'order' => 3, 'active' => true],
        ['image' => 'assets/player/carousel/eventos.jpg', 'category' => 'Eventos', 'alt' => 'Salón preparado para un evento ilustrativo', 'order' => 4, 'active' => true],
    ];

    public function categories(): array
    {
        return ['Fútbol', 'Pádel', 'Quincho', 'Eventos'];
    }

    public function activeSlides(): array
    {
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('player_carousel_slides')) {
                $rows = $db->table('player_carousel_slides')->where('active', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->limit(10)->get()->getResultArray();
                $rows = array_values(array_filter($rows, static function (array $slide): bool {
                    return ! empty($slide['image_path']) && is_file(FCPATH . ltrim((string) $slide['image_path'], '/'));
                }));
                return array_map(static function (array $slide): array {
                    return ['image' => $slide['image_path'], 'category' => $slide['category'], 'alt' => $slide['alt_text'], 'order' => $slide['sort_order'], 'active' => (bool) $slide['active']];
                }, $rows);
            }
        } catch (\Throwable $exception) {
            log_message('error', 'No se pudo leer la configuración del carrusel: ' . $exception->getMessage());
        }
        $slides = array_values(array_filter($this->slides, static function (array $slide): bool {
            return ! empty($slide['active']) && ! empty($slide['image']) && is_file(FCPATH . ltrim((string) $slide['image'], '/'));
        }));
        usort($slides, static function (array $first, array $second): int {
            return ((int) ($first['order'] ?? 0)) <=> ((int) ($second['order'] ?? 0));
        });
        return $slides;
    }
}
