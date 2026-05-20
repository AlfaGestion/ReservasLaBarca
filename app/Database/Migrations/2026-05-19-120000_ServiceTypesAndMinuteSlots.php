<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServiceTypesAndMinuteSlots extends Migration
{
    public function up()
    {
        $fields = $this->db->getFieldNames('fields');

        if (!in_array('service_type', $fields, true)) {
            $this->forge->addColumn('fields', [
                'service_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => false,
                    'default'    => 'football',
                    'after'      => 'field_type',
                ],
            ]);
        }

        if (!in_array('block_minutes', $fields, true)) {
            $this->forge->addColumn('fields', [
                'block_minutes' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                    'default'    => 60,
                    'after'      => 'service_type',
                ],
            ]);
        }

        if (!in_array('price_unit_label', $fields, true)) {
            $this->forge->addColumn('fields', [
                'price_unit_label' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                    'null'       => false,
                    'default'    => 'por hora',
                    'after'      => 'block_minutes',
                ],
            ]);
        }

        $this->db->query("
            UPDATE fields
            SET service_type = COALESCE(NULLIF(service_type, ''), 'football'),
                block_minutes = CASE WHEN block_minutes IS NULL OR block_minutes = 0 THEN 60 ELSE block_minutes END,
                price_unit_label = COALESCE(NULLIF(price_unit_label, ''), 'por hora')
        ");

        $this->ensureService('Pádel', 'padel', 90, 'por bloque de 1:30');
        $this->ensureService('Quincho', 'quincho', 60, 'por hora');
        $this->ensureService('Eventos / Confitería', 'eventos', 60, 'por hora');
    }

    public function down()
    {
        $fields = $this->db->getFieldNames('fields');
        foreach (['price_unit_label', 'block_minutes', 'service_type'] as $column) {
            if (in_array($column, $fields, true)) {
                $this->forge->dropColumn('fields', $column);
            }
        }
    }

    private function ensureService(string $name, string $serviceType, int $blockMinutes, string $unitLabel): void
    {
        $exists = $this->db->table('fields')
            ->where('service_type', $serviceType)
            ->get()
            ->getRowArray();

        if ($exists) {
            return;
        }

        $this->db->table('fields')->insert([
            'name'               => $name,
            'floor_type'         => '',
            'sizes'              => '',
            'ilumination'        => 0,
            'field_type'         => $name,
            'service_type'       => $serviceType,
            'block_minutes'      => $blockMinutes,
            'price_unit_label'   => $unitLabel,
            'roofed'             => 0,
            'value'              => 0,
            'ilumination_value'  => 0,
            'elements_rent'      => 0,
            'disabled'           => 0,
        ]);
    }
}
