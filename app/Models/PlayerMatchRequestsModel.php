<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerMatchRequestsModel extends Model
{
    protected $table = 'player_match_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['match_id', 'player_id', 'status', 'message', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
