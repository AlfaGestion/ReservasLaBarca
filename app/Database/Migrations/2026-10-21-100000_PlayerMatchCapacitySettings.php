<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatchCapacitySettings extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('min_players', 'player_match_settings')) {
            $this->forge->addColumn('player_match_settings', ['min_players' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 2]]);
        }
        if (! $this->db->fieldExists('max_players', 'player_match_settings')) {
            $this->forge->addColumn('player_match_settings', ['max_players' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 30]]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('max_players', 'player_match_settings')) $this->forge->dropColumn('player_match_settings', 'max_players');
        if ($this->db->fieldExists('min_players', 'player_match_settings')) $this->forge->dropColumn('player_match_settings', 'min_players');
    }
}
