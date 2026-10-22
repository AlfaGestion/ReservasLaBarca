<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerBookingLinksModel extends Model
{
    protected $table = 'player_booking_links';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['player_id', 'booking_id', 'created_at'];
    protected $useTimestamps = false;
}
