<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerNotifications extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('player_notifications')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'INT', 'unsigned' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'message' => ['type' => 'TEXT'],
            'url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_read' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['player_id', 'is_read']);
        $this->forge->addKey(['player_id', 'created_at']);
        $this->forge->createTable('player_notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('player_notifications', true);
    }
}
