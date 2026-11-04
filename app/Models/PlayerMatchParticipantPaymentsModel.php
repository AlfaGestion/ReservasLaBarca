<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerMatchParticipantPaymentsModel extends Model
{
    protected $table = 'player_match_participant_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['match_id', 'participant_id', 'player_id', 'booking_id', 'amount', 'status', 'provider', 'provider_payment_id', 'preference_id', 'external_reference', 'received_by', 'received_at', 'confirmed_at', 'metadata', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
