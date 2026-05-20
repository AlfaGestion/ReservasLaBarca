<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingSlotsModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'booking_slots';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'date',
        'id_field',
        'time_from',
        'time_until',
        'booking_id',
        'status',
        'active',
        'expires_at',
        'created_at',
    ];

    protected $useTimestamps = false;

    public function normalizeTime($time): string
    {
        $value = trim((string) $time);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}$/', $value)) {
            return str_pad($value, 2, '0', STR_PAD_LEFT) . ':00';
        }

        if (preg_match('/^(\d{1,2}):(\d{1,2})/', $value, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ':' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    public function timeToMinutes($time): int
    {
        $normalized = $this->normalizeTime($time);
        [$hour, $minute] = array_map('intval', explode(':', $normalized ?: '00:00'));

        return ($hour * 60) + $minute;
    }

    public function rangesOverlap($fromA, $untilA, $fromB, $untilB): bool
    {
        $aFrom = $this->timeToMinutes($fromA);
        $aUntil = $this->timeToMinutes($untilA);
        $bFrom = $this->timeToMinutes($fromB);
        $bUntil = $this->timeToMinutes($untilB);

        if ($aUntil <= $aFrom) {
            $aUntil += 24 * 60;
        }
        if ($bUntil <= $bFrom) {
            $bUntil += 24 * 60;
        }

        return $aFrom < $bUntil && $aUntil > $bFrom;
    }

    public function hasActiveOverlap($date, $fieldId, $timeFrom, $timeUntil, ?int $ignoreBookingId = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $builder = $this->where('date', $date)
            ->where('id_field', $fieldId)
            ->where('active', 1)
            ->groupStart()
                ->where('status !=', 'pending')
                ->orGroupStart()
                    ->where('status', 'pending')
                    ->where('expires_at >=', $now)
                ->groupEnd()
            ->groupEnd();

        if ($ignoreBookingId !== null) {
            $builder->groupStart()
                ->where('booking_id !=', $ignoreBookingId)
                ->orWhere('booking_id', null)
            ->groupEnd();
        }

        foreach ($builder->findAll() as $slot) {
            if ($this->rangesOverlap($timeFrom, $timeUntil, $slot['time_from'], $slot['time_until'])) {
                return true;
            }
        }

        return false;
    }

    public function createSlot(array $slotData, ?int $ignoreBookingId = null)
    {
        $slotData['time_from'] = $this->normalizeTime($slotData['time_from'] ?? '');
        $slotData['time_until'] = $this->normalizeTime($slotData['time_until'] ?? '');

        if ($this->hasActiveOverlap($slotData['date'] ?? null, $slotData['id_field'] ?? null, $slotData['time_from'], $slotData['time_until'], $ignoreBookingId)) {
            return false;
        }

        return $this->insert($slotData, true);
    }
}
