<?php

namespace App\Libraries;

use App\Models\BookingSlotsModel;
use App\Models\BookingsModel;
use App\Models\CancelReservationsModel;
use App\Models\FieldsModel;
use App\Models\ServicesModel;

class AvailabilityService
{
    private BookingSlotsModel $slots;
    private BookingsModel $bookings;
    private FieldsModel $fields;
    private ServicesModel $services;

    public function __construct()
    {
        $this->slots = new BookingSlotsModel();
        $this->bookings = new BookingsModel();
        $this->fields = new FieldsModel();
        $this->services = new ServicesModel();
    }

    public function cleanupExpiredPending(): void
    {
        $now = date('Y-m-d H:i:s');
        $expired = $this->slots
            ->where('active', 1)
            ->where('status', 'pending')
            ->where('expires_at <', $now)
            ->findAll();

        foreach ($expired as $slot) {
            $duplicate = $this->slots
                ->where('active', 0)
                ->where('date', $slot['date'])
                ->where('id_field', $slot['id_field'])
                ->where('time_from', $slot['time_from'])
                ->where('time_until', $slot['time_until'])
                ->first();
            if ($duplicate) {
                $this->slots->delete($slot['id']);
                continue;
            }

            $this->slots->update($slot['id'], [
                'active' => 0,
                'status' => 'expired',
            ]);
        }
    }

    public function checkAvailability($fieldId, $date, $from, $to, ?int $ignoreBookingId = null, bool $onlineOnly = true): bool
    {
        return $this->availabilityError($fieldId, $date, $from, $to, $ignoreBookingId, $onlineOnly) === null;
    }

    public static function isReservationInPast($date, $from, ?\DateTimeInterface $now = null): bool
    {
        if (empty($date) || empty($from)) {
            return false;
        }

        $timezoneName = config('App')->appTimezone ?? date_default_timezone_get();
        $timezone = new \DateTimeZone($timezoneName);
        $slotModel = new BookingSlotsModel();
        $normalizedTime = $slotModel->normalizeTime($from);

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $normalizedTime, $timezone);
        if (!$start) {
            return false;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return false;
        }

        $now = $now ? \DateTimeImmutable::createFromInterface($now)->setTimezone($timezone) : new \DateTimeImmutable('now', $timezone);

        return $start <= $now;
    }

    public function availabilityError($fieldId, $date, $from, $to, ?int $ignoreBookingId = null, bool $onlineOnly = true): ?string
    {
        $this->cleanupExpiredPending();

        if (empty($date) || empty($fieldId) || empty($from) || empty($to)) {
            return 'Faltan datos de fecha, servicio u horario.';
        }

        $field = $this->fields->getField($fieldId);
        if (! $field || (string)($field['disabled'] ?? '0') === '1') {
            return 'El servicio seleccionado no existe o no esta activo.';
        }

        $service = $this->services->getByField($field);
        if ($service && (int)($service['active'] ?? 1) !== 1) {
            return 'El servicio seleccionado no esta activo.';
        }
        if ($onlineOnly && $service && (int)($service['online_available'] ?? 1) !== 1) {
            return 'El servicio seleccionado no esta disponible para reservar online.';
        }

        if ($this->isClosedForDateField($date, $fieldId)) {
            return 'No se puede reservar: hay un cierre informado para esa fecha.';
        }

        $from = $this->slots->normalizeTime($from);
        $to = $this->slots->normalizeTime($to);
        $fromMinutes = $this->slots->timeToMinutes($from);
        $toMinutes = $this->slots->timeToMinutes($to);
        if ($toMinutes <= $fromMinutes) {
            $toMinutes += 24 * 60;
        }
        $duration = $toMinutes - $fromMinutes;

        $minimum = (int)($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? $field['duration_minutes'] ?? $field['block_minutes'] ?? 60);
        $interval = (int)($service['slot_interval_minutes'] ?? $service['booking_interval_minutes'] ?? $field['slot_interval_minutes'] ?? $field['block_minutes'] ?? 60);
        if ($minimum <= 0 || $interval <= 0) {
            return 'La configuracion de duracion del servicio no es valida.';
        }
        if ($duration !== $minimum) {
            return 'La duracion seleccionada no es valida para el servicio.';
        }

        $start = $service['opening_time'] ?? null;
        $end = $service['closing_time'] ?? null;
        if ($start && $end && ! $this->isInsideServiceHours($from, $to, $start, $end)) {
            return 'El horario seleccionado esta fuera del horario configurado para el servicio.';
        }

        $builder = $this->bookings->where('date', $date)
            ->where('id_field', $fieldId)
            ->where('annulled', 0);

        if ($ignoreBookingId !== null) {
            $builder->where('id !=', $ignoreBookingId);
        }

        foreach ($builder->findAll() as $booking) {
            if ($this->slots->rangesOverlap($from, $to, $booking['time_from'], $booking['time_until'])) {
                return 'El horario seleccionado ya esta ocupado o en proceso.';
            }
        }

        if ($this->slots->hasActiveOverlap($date, $fieldId, $from, $to, $ignoreBookingId)) {
            return 'El horario seleccionado ya esta ocupado o en proceso.';
        }

        return null;
    }

    public function getServicePrice(array $field): float
    {
        $service = $this->services->getByField($field);
        if ($service) {
            $priceModel = new \App\Models\ServicePricesModel();
            $price = $priceModel->getActiveForService((int)$service['id']);
            if ($price && (float)$price['base_price'] > 0) {
                return (float)$price['base_price'];
            }
        }

        return (float)($field['value'] ?? 0);
    }

    public function finalPriceForField(array $field): float
    {
        $base = $this->getServicePrice($field);
        $service = $this->services->getByField($field);
        if (! $service || (int)($service['offer_active'] ?? 0) !== 1 || (int)($service['active'] ?? 1) !== 1) {
            return $base;
        }

        $today = date('Y-m-d');
        $from = $service['offer_start_date'] ?? null;
        $to = $service['offer_end_date'] ?? null;
        if (($from && $today < $from) || ($to && $today > $to)) {
            return $base;
        }

        $discount = (float)($service['discount_value'] ?? 0);
        if ($discount <= 0) {
            return $base;
        }

        if (($service['discount_type'] ?? 'percentage') === 'fixed') {
            return max(0, $base - $discount);
        }

        return max(0, $base - ($base * $discount / 100));
    }

    private function isInsideServiceHours(string $from, string $to, string $start, string $end): bool
    {
        $fromMinutes = $this->slots->timeToMinutes($from);
        $toMinutes = $this->slots->timeToMinutes($to);
        $startMinutes = $this->slots->timeToMinutes($start);
        $endMinutes = $this->slots->timeToMinutes($end);

        if ($toMinutes <= $fromMinutes) {
            $toMinutes += 24 * 60;
        }
        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
            if ($fromMinutes < $startMinutes) {
                $fromMinutes += 24 * 60;
                $toMinutes += 24 * 60;
            }
        }

        return $fromMinutes >= $startMinutes && $toMinutes <= $endMinutes;
    }

    private function isClosedForDateField($date, $fieldId): bool
    {
        $closures = (new CancelReservationsModel())->where('cancel_date', $date)->findAll();
        foreach ($closures as $closure) {
            if (empty($closure['field_id'])) {
                return true;
            }
            if ((int)$closure['field_id'] === (int)$fieldId) {
                return true;
            }
        }

        return false;
    }
}
