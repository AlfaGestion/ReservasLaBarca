<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatchStarterSubstituteLimits extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('player_matches')) {
            return;
        }

        $columns = [
            'max_starters' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'max_players'],
            'confirmed_starters' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'confirmed_players'],
            'max_substitutes' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 0, 'after' => 'confirmed_starters'],
            'confirmed_substitutes' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 0, 'after' => 'max_substitutes'],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'player_matches')) {
                $this->forge->addColumn('player_matches', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('player_matches')) {
            return;
        }

        foreach (['confirmed_substitutes', 'max_substitutes', 'confirmed_starters', 'max_starters'] as $column) {
            if ($this->db->fieldExists($column, 'player_matches')) {
                $this->forge->dropColumn('player_matches', $column);
            }
        }
    }
}
