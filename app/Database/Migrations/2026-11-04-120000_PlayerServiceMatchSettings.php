<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlayerServiceMatchSettings extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('player_service_match_settings')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'service_id' => ['type' => 'INT', 'unsigned' => true],
                'portal_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'search_players' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'min_players' => ['type' => 'INT', 'unsigned' => true, 'default' => 2],
                'max_players' => ['type' => 'INT', 'unsigned' => true, 'default' => 30],
                'default_starters' => ['type' => 'INT', 'unsigned' => true, 'default' => 2],
                'max_starters' => ['type' => 'INT', 'unsigned' => true, 'default' => 30],
                'default_substitutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'max_substitutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'allow_substitutes' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'count_organizer' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'allow_auto_join' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'allow_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'allow_leave' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'leave_hours_limit' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'allow_slot_edit' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('service_id');
            $this->forge->createTable('player_service_match_settings');
        }

        if ($this->db->tableExists('services')) {
            $services = $this->db->table('services')->select('id, code, is_sport')->get()->getResultArray();
            $now = date('Y-m-d H:i:s');
            foreach ($services as $service) {
                $sport = (int) ($service['is_sport'] ?? 0) === 1;
                $defaults = $sport && $service['code'] === 'padel'
                    ? ['min_players' => 2, 'max_players' => 4, 'default_starters' => 4, 'max_starters' => 4]
                    : ($sport && $service['code'] === 'football'
                        ? ['min_players' => 2, 'max_players' => 12, 'default_starters' => 10, 'max_starters' => 12, 'allow_substitutes' => 1, 'max_substitutes' => 2]
                        : []);
                if (! $this->db->table('player_service_match_settings')->where('service_id', $service['id'])->countAllResults()) {
                    $this->db->table('player_service_match_settings')->insert(array_merge([
                        'service_id' => $service['id'],
                        'portal_enabled' => 1,
                        'search_players' => $sport ? 1 : 0,
                        'min_players' => 2,
                        'max_players' => 30,
                        'default_starters' => 2,
                        'max_starters' => 30,
                        'default_substitutes' => 0,
                        'max_substitutes' => 0,
                        'allow_substitutes' => 0,
                        'count_organizer' => 1,
                        'allow_auto_join' => 1,
                        'allow_approval' => 1,
                        'allow_leave' => 1,
                        'allow_slot_edit' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $defaults));
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('player_service_match_settings')) {
            $this->forge->dropTable('player_service_match_settings');
        }
    }
}
