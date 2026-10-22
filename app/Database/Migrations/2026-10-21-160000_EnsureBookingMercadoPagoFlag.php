<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Mantiene compatible la base de ejemplo con el estado de pago existente. */
class EnsureBookingMercadoPagoFlag extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('bookings') && ! $this->db->fieldExists('mp', 'bookings')) {
            $this->forge->addColumn('bookings', [
                'mp' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'approved',
                ],
            ]);
        }
    }

    public function down()
    {
        // No se eliminan columnas de reservas para preservar compatibilidad.
    }
}
