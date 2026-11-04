<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatchParticipantPayments extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE player_match_settings ADD COLUMN payment_mode_organizer_deposit TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN payment_mode_split TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN payment_mode_venue TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN participant_payment_expiry_hours INT NULL DEFAULT 24");
        $this->db->query("ALTER TABLE player_matches ADD COLUMN payment_total DECIMAL(12,2) NULL DEFAULT NULL, ADD COLUMN participant_share DECIMAL(12,2) NULL DEFAULT NULL, ADD COLUMN reserved_players INT NOT NULL DEFAULT 0, ADD COLUMN payment_policy VARCHAR(80) NULL DEFAULT NULL");
        $this->db->query("ALTER TABLE player_match_participants ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0, ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0, ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'not_required', ADD COLUMN payment_expires_at DATETIME NULL DEFAULT NULL, ADD COLUMN payment_confirmed_at DATETIME NULL DEFAULT NULL");
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'match_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'participant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'player_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'booking_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'mercado_pago'],
            'provider_payment_id' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'preference_id' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'external_reference' => ['type' => 'VARCHAR', 'constraint' => 180],
            'received_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'received_at' => ['type' => 'DATETIME', 'null' => true],
            'confirmed_at' => ['type' => 'DATETIME', 'null' => true],
            'metadata' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['match_id', 'participant_id']);
        $this->forge->addKey('provider_payment_id');
        $this->forge->addUniqueKey('external_reference');
        $this->forge->createTable('player_match_participant_payments', true);
    }

    public function down()
    {
        $this->forge->dropTable('player_match_participant_payments', true);
        $this->db->query('ALTER TABLE player_match_participants DROP COLUMN amount_due, DROP COLUMN amount_paid, DROP COLUMN payment_status, DROP COLUMN payment_expires_at, DROP COLUMN payment_confirmed_at');
        $this->db->query('ALTER TABLE player_matches DROP COLUMN payment_total, DROP COLUMN participant_share, DROP COLUMN reserved_players, DROP COLUMN payment_policy');
        $this->db->query('ALTER TABLE player_match_settings DROP COLUMN payment_mode_organizer_deposit, DROP COLUMN payment_mode_split, DROP COLUMN payment_mode_venue, DROP COLUMN participant_payment_expiry_hours');
    }
}
