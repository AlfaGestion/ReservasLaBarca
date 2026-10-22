<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerMatchesModel extends Model
{
    protected $table = 'player_matches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['booking_id', 'organizer_player_id', 'max_players', 'confirmed_players', 'description', 'level', 'join_mode', 'status', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
