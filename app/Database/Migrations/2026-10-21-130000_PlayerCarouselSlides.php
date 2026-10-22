<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerCarouselSlides extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('player_carousel_slides')) return;

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'category' => ['type' => 'VARCHAR', 'constraint' => 80],
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 180],
            'sort_order' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['active', 'sort_order']);
        $this->forge->createTable('player_carousel_slides', true);
    }

    public function down()
    {
        $this->forge->dropTable('player_carousel_slides', true);
    }
}
