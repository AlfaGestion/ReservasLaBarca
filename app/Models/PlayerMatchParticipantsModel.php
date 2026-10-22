<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerMatchParticipantsModel extends Model
{
    protected $table = 'player_match_participants';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['match_id', 'player_id', 'role', 'status', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
