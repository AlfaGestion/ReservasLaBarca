<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerNotificationsModel extends Model
{
    protected $table = 'player_notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['player_id', 'type', 'title', 'message', 'url', 'is_read', 'created_at'];
    protected $useTimestamps = false;
}
