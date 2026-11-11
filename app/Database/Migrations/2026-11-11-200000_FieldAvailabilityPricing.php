<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FieldAvailabilityPricing extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('field_availability_windows')) {
            return;
        }

        $fields = $this->db->getFieldNames('field_availability_windows');
        if (! in_array('duration_minutes', $fields, true)) {
            $this->forge->addColumn('field_availability_windows', [
                'duration_minutes' => ['type' => 'INT', 'null' => true, 'after' => 'end_time'],
            ]);
        }
        if (! in_array('price', $fields, true)) {
            $this->forge->addColumn('field_availability_windows', [
                'price' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'duration_minutes'],
            ]);
        }
    }

    public function down()
    {
        // Migración aditiva: no elimina configuraciones automáticamente.
    }
}
