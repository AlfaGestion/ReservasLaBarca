<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatches extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('player_matches')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'booking_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'organizer_player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'max_players' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true],
                'confirmed_players' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
                'description' => ['type' => 'TEXT', 'null' => true],
                'level' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'join_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'auto'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('booking_id');
            $this->forge->addKey(['organizer_player_id', 'status']);
            $this->forge->addKey(['status', 'join_mode']);
            $this->forge->createTable('player_matches');
        }

        if (! $this->db->tableExists('player_match_participants')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'match_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'role' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'participant'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'confirmed'],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['match_id', 'player_id']);
            $this->forge->addKey(['match_id', 'status']);
            $this->forge->createTable('player_match_participants');
        }

        if (! $this->db->tableExists('player_match_requests')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'match_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['match_id', 'player_id']);
            $this->forge->addKey(['match_id', 'status']);
            $this->forge->createTable('player_match_requests');
        }

        if (! $this->db->tableExists('player_match_settings')) {
            $this->forge->addField([
                'id' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true],
                'allow_auto_join' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'allow_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('player_match_settings');
            $this->db->table('player_match_settings')->insert(['id' => 1, 'allow_auto_join' => 1, 'allow_approval' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        foreach (['player_match_requests', 'player_match_participants', 'player_matches', 'player_match_settings'] as $table) {
            if ($this->db->tableExists($table)) $this->forge->dropTable($table);
        }
    }
}
