<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerAdminAccountControls extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('status', 'player_accounts')) {
            $this->forge->addColumn('player_accounts', [
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active', 'after' => 'email_verified_at'],
            ]);
        }

        if (! $this->db->tableExists('player_account_discounts')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'discount_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'percentage'],
                'value' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'unsigned' => true],
                'starts_at' => ['type' => 'DATE', 'null' => true],
                'ends_at' => ['type' => 'DATE', 'null' => true],
                'service_codes' => ['type' => 'TEXT', 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['player_id', 'active']);
            $this->forge->createTable('player_account_discounts');
        }
    }

    public function down()
    {
        // La reversión no elimina controles ni datos de jugadores.
    }
}
