<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PaymentsIdUserNullable extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `payments` MODIFY `id_user` INT(11) UNSIGNED NULL');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `payments` MODIFY `id_user` INT(11) UNSIGNED NOT NULL');
    }
}
