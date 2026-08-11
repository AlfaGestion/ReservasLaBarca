<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CustomerOffers extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('customer_offers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'customer_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'value' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0,
                ],
                'description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'expiration_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'apply_all_fields' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'apply_all_services' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('customer_id', 'uq_customer_offers_customer');
            $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('customer_offers');
        }

        if (! $db->tableExists('customer_offer_fields')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'customer_offer_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'field_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['customer_offer_id', 'field_id'], 'uq_customer_offer_fields_offer_field');
            $this->forge->addForeignKey('customer_offer_id', 'customer_offers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('field_id', 'fields', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('customer_offer_fields');
        }

        if (! $db->tableExists('customer_offer_services')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'customer_offer_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'service_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['customer_offer_id', 'service_code'], 'uq_customer_offer_services_offer_service');
            $this->forge->addForeignKey('customer_offer_id', 'customer_offers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('service_code', 'services', 'code', 'CASCADE', 'CASCADE');
            $this->forge->createTable('customer_offer_services');
        }

        $bookingColumns = $db->getFieldNames('bookings');
        if (! in_array('customer_offer_id', $bookingColumns, true)) {
            $this->forge->addColumn('bookings', [
                'customer_offer_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id_customer',
                ],
            ]);
        }

        if (! in_array('original_total', $bookingColumns, true)) {
            $this->forge->addColumn('bookings', [
                'original_total' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => true,
                    'after'      => 'customer_offer_id',
                ],
            ]);
        }

        if (! in_array('discount_percentage', $bookingColumns, true)) {
            $this->forge->addColumn('bookings', [
                'discount_percentage' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                    'after'      => 'original_total',
                ],
            ]);
        }

        if (! in_array('discount_amount', $bookingColumns, true)) {
            $this->forge->addColumn('bookings', [
                'discount_amount' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => true,
                    'after'      => 'discount_percentage',
                ],
            ]);
        }

        $db->table('bookings')
            ->set('original_total', 'total', false)
            ->where('original_total', null)
            ->update();

        $db->table('bookings')
            ->set('discount_percentage', 0)
            ->where('discount_percentage', null)
            ->update();

        $db->table('bookings')
            ->set('discount_amount', 0)
            ->where('discount_amount', null)
            ->update();
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('bookings')) {
            foreach (['discount_amount', 'discount_percentage', 'original_total', 'customer_offer_id'] as $column) {
                if ($db->fieldExists($column, 'bookings')) {
                    $this->forge->dropColumn('bookings', $column);
                }
            }
        }

        if ($db->tableExists('customer_offer_services')) {
            $this->forge->dropTable('customer_offer_services');
        }

        if ($db->tableExists('customer_offer_fields')) {
            $this->forge->dropTable('customer_offer_fields');
        }

        if ($db->tableExists('customer_offers')) {
            $this->forge->dropTable('customer_offers');
        }
    }
}
