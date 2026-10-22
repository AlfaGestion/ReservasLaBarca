<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerCarouselSlidesModel extends Model
{
    protected $table = 'player_carousel_slides';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['image_path', 'category', 'alt_text', 'sort_order', 'active', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
