<?php

namespace App\Models;

use CodeIgniter\Model;

class RateHistoryModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'rate_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['rate_id', 'old_value', 'new_value', 'user_id', 'user_name', 'created_at'];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';

    protected $validationRules      = [];
    protected $validationMessages    = [];
    protected $skipValidation        = false;
    protected $cleanValidationRules  = true;
    protected $allowCallbacks        = true;
    protected $beforeInsert          = [];
    protected $afterInsert           = [];
    protected $beforeUpdate          = [];
    protected $afterUpdate           = [];
    protected $beforeFind            = [];
    protected $afterFind             = [];
    protected $beforeDelete           = [];
    protected $afterDelete           = [];
}
