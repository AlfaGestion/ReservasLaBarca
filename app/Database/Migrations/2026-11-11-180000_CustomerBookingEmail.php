<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CustomerBookingEmail extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('customers') && !$this->db->fieldExists('email', 'customers')) {
            $this->forge->addColumn('customers', [
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 190,
                    'null'       => true,
                    'after'      => 'phone',
                ],
            ]);
        }

        if ($this->db->tableExists('bookings') && !$this->db->fieldExists('email', 'bookings')) {
            $this->forge->addColumn('bookings', [
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 190,
                    'null'       => true,
                    'after'      => 'phone',
                ],
            ]);
        }
    }

    public function down()
    {
        // Se conserva la información de contacto histórica.
    }
}
