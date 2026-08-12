<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingsModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'bookings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_field', 'date', 'time_from', 'time_until', 'name', 'phone', 'locality', 'total_payment', 'total', 'parcial', 'diference', 'description', 'reservation', 'payment', 'payment_method', 'id_customer', 'customer_offer_id', 'original_total', 'discount_percentage', 'discount_amount', 'id_preference_parcial', 'id_preference_total', 'approved', 'use_offer', 'annulled', 'booking_time', 'mp', 'created_by_type', 'created_by_user_id', 'created_by_name', 'edited_by_user_id', 'edited_by_name', 'edited_at', 'reservation_rate'];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];


    public function getBookings(){
        $bookings = $this->findAll();

        return $bookings;
    }

    public function getBooking($id){
        $booking = $this->find($id);

        return $booking;
    }
}
