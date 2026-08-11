<?php

namespace App\Models;

use CodeIgniter\Model;

class UsersModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['user', 'password', 'superadmin', 'name', 'active'];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function getUsers(){
        $users = $this->findAll();

        return $users;
    }

    public function getUser($id){
        $user = $this->find($id);

        return $user;
    }

    public function getUserName($id): ?string{
        $userId = (int) $id;
        if ($userId <= 0) {
            return null;
        }

        $user = $this->find($userId);
        if (!is_array($user) || !isset($user['name'])) {
            return null;
        }

        $userName = trim((string) $user['name']);
        return $userName !== '' ? $userName : null;
    }
}
