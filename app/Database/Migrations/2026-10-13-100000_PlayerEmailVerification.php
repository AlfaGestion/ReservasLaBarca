<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerEmailVerification extends Migration
{
    public function up()
    {
        $fields = $this->db->getFieldNames('player_accounts');
        $add = [];

        if (! in_array('email_verified_at', $fields, true)) {
            $add['email_verified_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if (! in_array('verification_token_hash', $fields, true)) {
            $add['verification_token_hash'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true];
        }
        if (! in_array('verification_expires_at', $fields, true)) {
            $add['verification_expires_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if (! in_array('verification_sent_at', $fields, true)) {
            $add['verification_sent_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if (! in_array('session_version', $fields, true)) {
            $add['session_version'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false, 'default' => 0];
        }

        if ($add !== []) {
            $this->forge->addColumn('player_accounts', $add);
        }

        // Accounts created before email verification existed remain usable.
        $this->db->query("UPDATE player_accounts SET email_verified_at = COALESCE(created_at, NOW()) WHERE email_verified_at IS NULL");
        if (! $this->indexExists('player_accounts', 'player_accounts_verification_token_idx')) {
            $this->db->query('ALTER TABLE `player_accounts` ADD INDEX `player_accounts_verification_token_idx` (`verification_token_hash`)');
        }
    }

    public function down()
    {
        // Additive compatibility migration: do not remove account data on rollback.
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index])->getRowArray();
        return is_array($row) && $row !== [];
    }
}
