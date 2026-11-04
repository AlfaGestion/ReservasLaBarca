<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServiceImagePath extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('services') && ! $this->db->fieldExists('image_path', 'services')) {
            $this->forge->addColumn('services', [
                'image_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'icon',
                ],
            ]);
        }
    }

    public function down()
    {
        // No se elimina información de servicios automáticamente.
    }
}
