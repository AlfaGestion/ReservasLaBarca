<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerMatchPortalOptions extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('player_match_settings')) {
            $columns = [
                'allow_leave' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'leave_hours_limit' => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
                'allow_free_join' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'allow_paid_join' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'organizer_choose_payment' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'favorite_notifications_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'search_players_in_reservation' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ];
            foreach ($columns as $name => $definition) {
                if (! $this->db->fieldExists($name, 'player_match_settings')) {
                    $this->forge->addColumn('player_match_settings', [$name => $definition]);
                }
            }
        }

        if (! $this->db->fieldExists('payment_mode', 'player_matches')) {
            $this->forge->addColumn('player_matches', ['payment_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'free', 'after' => 'join_mode']]);
        }
        if (! $this->db->fieldExists('withdrawn_at', 'player_match_participants')) {
            $this->forge->addColumn('player_match_participants', [
                'withdrawn_at' => ['type' => 'DATETIME', 'null' => true],
                'withdrawal_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            ]);
        }
        if (! $this->db->fieldExists('notify_favorite_matches', 'player_accounts')) {
            $this->forge->addColumn('player_accounts', ['notify_favorite_matches' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        }

        if (! $this->db->tableExists('player_match_publication_intents')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'booking_id' => ['type' => 'INT', 'unsigned' => true],
                'organizer_player_id' => ['type' => 'INT', 'unsigned' => true],
                'max_players' => ['type' => 'INT', 'unsigned' => true],
                'join_mode' => ['type' => 'VARCHAR', 'constraint' => 20],
                'payment_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'free'],
                'description' => ['type' => 'TEXT', 'null' => true],
                'level' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'match_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('booking_id');
            $this->forge->addKey(['status', 'updated_at']);
            $this->forge->createTable('player_match_publication_intents');
        }

        if (! $this->db->tableExists('player_match_email_notifications')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'match_id' => ['type' => 'INT', 'unsigned' => true],
                'player_id' => ['type' => 'INT', 'unsigned' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'attempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
                'last_error' => ['type' => 'TEXT', 'null' => true],
                'sent_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['match_id', 'player_id']);
            $this->forge->addKey(['status', 'attempts']);
            $this->forge->createTable('player_match_email_notifications');
        }
    }

    public function down()
    {
        // Migración aditiva: no elimina reservas, partidos ni preferencias de jugadores.
    }
}
