<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerBookingLinks extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'booking_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['player_id', 'booking_id'], 'uq_player_booking_link');
        $this->forge->addKey('player_id');
        $this->forge->addKey('booking_id');
        $this->forge->createTable('player_booking_links', true);
    }

    public function down()
    {
        $this->forge->dropTable('player_booking_links', true);
    }
}
