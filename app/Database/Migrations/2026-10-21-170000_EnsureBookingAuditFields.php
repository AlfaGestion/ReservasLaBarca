<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Completa campos de auditoría que usan las reservas actuales. */
class EnsureBookingAuditFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('bookings')) {
            return;
        }

        $columns = $this->db->getFieldNames('bookings');
        $fields = [
            'created_by_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_by_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'edited_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'edited_by_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'edited_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        foreach ($fields as $name => $definition) {
            if (! in_array($name, $columns, true)) {
                $this->forge->addColumn('bookings', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        // Se conservan los campos de auditoría para no afectar reservas.
    }
}
