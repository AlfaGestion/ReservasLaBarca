<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatchPadelCategories extends Migration
{
    public function up()
    {
        foreach (['player_matches', 'player_match_publication_intents'] as $table) {
            if (! $this->db->tableExists($table)) continue;
            foreach (['padel_category_min', 'padel_category_max'] as $column) {
                if (! $this->db->fieldExists($column, $table)) {
                    $this->forge->addColumn($table, [$column => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true]]);
                }
            }
        }
    }

    public function down()
    {
        // No se eliminan datos históricos del portal.
    }
}
