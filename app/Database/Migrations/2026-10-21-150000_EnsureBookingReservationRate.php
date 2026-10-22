<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restaura la columna utilizada para conservar la tasa aplicada a una reserva
 * cuando una base existente ya marcó la migración histórica como ejecutada.
 */
class EnsureBookingReservationRate extends Migration
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
    }

    public function down()
    {
        // No elimina columnas existentes: la migración es deliberadamente
        // conservadora para proteger reservas históricas.
    }
}
