<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminLogsModel extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'admin_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'admin_id',
        'admin_name',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'host_name',
        'client_device',
        'old_data',
        'new_data',
        'created_at',
    ];

    protected $useTimestamps = false;
}
