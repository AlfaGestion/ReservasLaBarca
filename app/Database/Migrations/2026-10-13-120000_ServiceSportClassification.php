<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServiceSportClassification extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_sport', 'services')) {
            $this->forge->addColumn('services', [
                'is_sport' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'after' => 'online_available',
                ],
            ]);
        }

        // Transición explícita de los servicios deportivos que ya existen.
        $this->db->table('services')->whereIn('code', ['football', 'padel'])->update(['is_sport' => 1]);
    }

    public function down()
    {
        if ($this->db->fieldExists('is_sport', 'services')) {
            $this->forge->dropColumn('services', 'is_sport');
        }
    }
}
