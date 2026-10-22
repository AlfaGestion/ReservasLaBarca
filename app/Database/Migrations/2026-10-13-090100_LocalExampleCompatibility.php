<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keeps the bundled example SQL compatible with the current application schema.
 * All changes are additive and guarded so existing installations are untouched.
 */
class LocalExampleCompatibility extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('time') && ! in_array('is_sunday', $this->db->getFieldNames('time'), true)) {
            $this->forge->addColumn('time', [
                'is_sunday' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 0,
                ],
            ]);
        }

        if (! $this->db->tableExists('mercado_pago_keys')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'public_key' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'access_token' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('mercado_pago_keys');
        }
    }

    public function down()
    {
        // Compatibility objects are intentionally preserved on rollback.
    }
}
