<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerAccountDiscountsModel extends Model
{
    protected $table = 'player_account_discounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'player_id', 'discount_type', 'value', 'starts_at', 'ends_at', 'service_codes',
        'description', 'active', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
