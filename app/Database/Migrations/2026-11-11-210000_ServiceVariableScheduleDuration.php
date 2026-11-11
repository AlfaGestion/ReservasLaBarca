<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServiceVariableScheduleDuration extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('services') || $this->db->fieldExists('variable_duration_by_schedule', 'services')) {
            return;
        }

        $this->forge->addColumn('services', [
            'variable_duration_by_schedule' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'duration_minutes'],
        ]);
    }

    public function down()
    {
        // Migración aditiva: no elimina configuraciones automáticamente.
    }
}
