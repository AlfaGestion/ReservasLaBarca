<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FieldAvailabilityWindows extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('field_availability_windows')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'field_id' => ['type' => 'INT', 'unsigned' => true],
            'start_time' => ['type' => 'TIME'],
            'end_time' => ['type' => 'TIME'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['field_id', 'active']);
        $this->forge->createTable('field_availability_windows');
    }

    public function down()
    {
        // Migración aditiva: no elimina configuraciones automáticamente.
    }
}
