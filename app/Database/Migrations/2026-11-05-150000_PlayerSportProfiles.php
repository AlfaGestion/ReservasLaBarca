<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerSportProfiles extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('sport_profiles', 'player_accounts')) {
            $this->forge->addColumn('player_accounts', [
                'sport_profiles' => ['type' => 'TEXT', 'null' => true, 'after' => 'favorite_sports'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('sport_profiles', 'player_accounts')) {
            $this->forge->dropColumn('player_accounts', 'sport_profiles');
        }
    }
}
