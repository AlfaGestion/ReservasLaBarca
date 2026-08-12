<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BookingRateAudit extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('bookings') && ! $db->fieldExists('reservation_rate', 'bookings')) {
            $this->forge->addColumn('bookings', [
                'reservation_rate' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                    'after'      => 'parcial',
                ],
            ]);
        }

        if (! $db->tableExists('rate_history')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'rate_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'old_value' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'new_value' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => false,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'user_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('rate_id');
            $this->forge->addKey('created_at');
            $this->forge->addForeignKey('rate_id', 'rate', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('rate_history');
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('rate_history')) {
            $this->forge->dropTable('rate_history');
        }

        if ($db->tableExists('bookings') && $db->fieldExists('reservation_rate', 'bookings')) {
            $this->forge->dropColumn('bookings', 'reservation_rate');
        }
    }
}
