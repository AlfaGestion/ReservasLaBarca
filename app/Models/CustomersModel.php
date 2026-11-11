<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomersModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'customers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'last_name', 'dni', 'phone', 'email', 'offer', 'quantity', 'city'];

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

    public function getCustomers()
    {
        $customers = $this->findAll();

        return $customers;
    }

    public function getCustomer($id)
    {
        $customer = $this->find($id);

        return $customer;
    }

    public function getCustomerName($id)
    {
        if (empty($id)) {
            return 'No informado';
        }

        $customer = $this->find($id);

        return (!empty($customer) && !empty($customer['name']))
            ? $customer['name']
            : 'No informado';
    }

    public function getCustomerPhone($id)
    {
        if (empty($id)) {
            return 'No informado';
        }

        $customer = $this->find($id);

        return (!empty($customer) && !empty($customer['phone']))
            ? $customer['phone']
            : 'No informado';
    }
}
