<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServicePriceWindows extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('service_price_windows')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'service_id' => ['type' => 'INT', 'unsigned' => true],
            'weekdays' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1234567'],
            'start_time' => ['type' => 'TIME'],
            'end_time' => ['type' => 'TIME'],
            'price' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'unsigned' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['service_id', 'active']);
        $this->forge->createTable('service_price_windows');
    }

    public function down()
    {
        // Migración aditiva: no se elimina información de precios sin una
        // decisión explícita del administrador.
    }
}
