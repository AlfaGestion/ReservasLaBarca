<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerAccountLocality extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('locality', 'player_accounts')) {
            $this->forge->addColumn('player_accounts', [
                'locality' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'phone'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('locality', 'player_accounts')) {
            $this->forge->dropColumn('player_accounts', 'locality');
        }
    }
}
