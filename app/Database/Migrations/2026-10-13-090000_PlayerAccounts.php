<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Player identity is intentionally separate from the existing admin users table.
 */
class PlayerAccounts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'email' => ['type' => 'VARCHAR', 'constraint' => 190],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'avatar_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'favorite_sports' => ['type' => 'TEXT', 'null' => true],
            'reset_token_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'reset_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'reset_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('reset_token_hash');
        $this->forge->createTable('player_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('player_accounts');
    }
}
