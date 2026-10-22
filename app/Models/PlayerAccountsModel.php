<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerAccountsModel extends Model
{
    protected $table = 'player_accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'first_name', 'last_name', 'email', 'phone', 'locality', 'password_hash', 'avatar_path',
        'favorite_sports', 'reset_token_hash', 'reset_expires_at', 'reset_used_at',
        'email_verified_at', 'verification_token_hash', 'verification_expires_at',
        'verification_sent_at', 'session_version',
        'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
