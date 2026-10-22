<?php

namespace App\Controllers;

use App\Libraries\AvailabilityService;
use App\Libraries\CustomerOfferService;
use App\Libraries\PlayerPortalSettings;
use App\Models\BookingSlotsModel;
use App\Models\CancelReservationsModel;
use App\Models\FieldsModel;
use App\Models\MercadoPagoKeysModel;
use App\Models\PlayerAccountsModel;
use App\Models\PlayerBookingLinksModel;
use App\Models\RateModel;
use App\Models\ServicesModel;

class PlayerBookings extends BaseController
{
    private function player(): ?array
    {
        $id = (int) session()->get('player_id');
        return $id > 0 ? (new PlayerAccountsModel())->find($id) : null;
    }

    private function page(string $page, array $data = [])
    {
        return view('player/layout', array_merge([
            'contentView' => 'player/' . $page,
            'player' => $this->player(),
        ], $data));
    }

    public function index()
    {
        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();
        $services = $servicesModel->getActiveServices(true);
        $fields = (new FieldsModel())->getFields();
        $fieldsByService = [];
        foreach ($fields as $field) {
            $code = (string) ($field['service_type'] ?? '');
            if ($code !== '') {
                $fieldsByService[$code][] = $field;
            }
        }
        $services = array_values(array_filter($services, static fn ($service) => ! empty($fieldsByService[$service['code']] ?? [])));

        $keys = (new MercadoPagoKeysModel())->first() ?: [];
        return $this->page('reservations', [
            'services' => $services,
            'fieldsByService' => $fieldsByService,
            'publicKeyMp' => (string) ($keys['public_key'] ?? ''),
            'playerPortalTestMode' => (new PlayerPortalSettings())->isTestMode(),
            'bookings' => $this->myBookings(),
        ]);
    }

    public function availability()
    {
        $date = trim((string) $this->request->getGet('date'));
        $fieldId = (int) $this->request->getGet('field_id');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $fieldId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['error' => true, 'message' => 'Fecha o instalación inválida.']);
        }

        $field = (new FieldsModel())->getField($fieldId);
        $service = $field ? (new ServicesModel())->getByField($field) : null;
        if (! $field || ! $service || (int) ($field['disabled'] ?? 0) === 1 || (int) ($service['active'] ?? 1) !== 1 || (int) ($service['online_available'] ?? 1) !== 1) {
            return $this->response->setStatusCode(404)->setJSON(['error' => true, 'message' => 'La instalación no está disponible online.']);
        }

        $slots = [];
        $model = new BookingSlotsModel();
        $from = $model->timeToMinutes($service['opening_time'] ?? '07:00');
        $until = $model->timeToMinutes($service['closing_time'] ?? '23:00');
        $duration = max(1, (int) ($service['duration_minutes'] ?? 60));
        $interval = max(1, (int) ($service['slot_interval_minutes'] ?? $duration));
        if ($until <= $from) {
            $until += 1440;
        }

        $availability = new AvailabilityService();
        for ($cursor = $from; $cursor + $duration <= $until; $cursor += $interval) {
            $slotFrom = $this->minutesToTime($cursor);
            $slotUntil = $this->minutesToTime($cursor + $duration);
            $error = $availability->availabilityError($fieldId, $date, $slotFrom, $slotUntil, null, true);
            $slots[] = [
                'from' => $slotFrom,
                'until' => $slotUntil,
                'available' => $error === null,
                'reason' => $error,
            ];
        }

        return $this->response->setJSON([
            'error' => false,
            'data' => [
                'date' => $date,
                'field' => $field,
                'service' => $service,
                'slots' => $slots,
                'notice' => $slots !== [] && count(array_filter($slots, static fn ($slot) => ! empty($slot['available']))) === 0
                    ? ($slots[0]['reason'] ?? null)
                    : null,
            ],
        ]);
    }

    public function quote()
    {
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        $date = trim((string) ($data['date'] ?? ''));
        $fieldId = (int) ($data['field_id'] ?? 0);
        $from = trim((string) ($data['from'] ?? ''));
        $until = trim((string) ($data['until'] ?? ''));
        $field = (new FieldsModel())->getField($fieldId);
        if (! $field || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $from === '' || $until === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => true, 'message' => 'Completá todos los datos de la reserva.']);
        }

        $availability = new AvailabilityService();
        $availabilityError = $availability->availabilityError($fieldId, $date, $from, $until, null, true);
        if ($availabilityError !== null) {
            return $this->response->setStatusCode(409)->setJSON(['error' => true, 'message' => $availabilityError]);
        }

        $player = $this->player();
        $items = [[
            'fecha' => $date,
            'cancha' => $fieldId,
            'horarioDesde' => $from,
            'horarioHasta' => $until,
        ]];
        $additional = $data['additional'] ?? null;
        if (is_array($additional) && ! empty($additional['enabled'])) {
            $additionalFieldId = (int) ($additional['field_id'] ?? 0);
            $additionalFrom = trim((string) ($additional['from'] ?? ''));
            $additionalUntil = trim((string) ($additional['until'] ?? ''));
            $additionalField = (new FieldsModel())->getField($additionalFieldId);
            if (! $additionalField || ($additionalField['service_type'] ?? '') !== 'quincho') {
                return $this->response->setStatusCode(422)->setJSON(['error' => true, 'message' => 'El adicional seleccionado no es válido.']);
            }
            if ((int) ($field['allows_quincho_addon'] ?? 0) !== 1) {
                return $this->response->setStatusCode(422)->setJSON(['error' => true, 'message' => 'Este servicio no permite agregar un quincho.']);
            }
            $additionalError = $availability->availabilityError($additionalFieldId, $date, $additionalFrom, $additionalUntil, null, true);
            if ($additionalError !== null) {
                return $this->response->setStatusCode(409)->setJSON(['error' => true, 'message' => $additionalError]);
            }
            $items[] = [
                'fecha' => $date,
                'cancha' => $additionalFieldId,
                'horarioDesde' => $additionalFrom,
                'horarioHasta' => $additionalUntil,
            ];
        }
        $quote = (new CustomerOfferService())->calculateBookingQuote($items, $player['phone'] ?? null, $date);
        if (! empty($quote['error'])) {
            return $this->response->setStatusCode(409)->setJSON(['error' => true, 'message' => $quote['message'] ?? 'No se pudo calcular el precio.']);
        }

        $total = (float) ($quote['final_total'] ?? 0);
        $rate = (new RateModel())->first();
        $rateValue = $rate && is_numeric($rate['value'] ?? null) ? (float) $rate['value'] : null;
        return $this->response->setJSON([
            'error' => false,
            'data' => [
                'field' => $field,
                'date' => $date,
                'from' => $from,
                'until' => $until,
                'original_total' => (float) ($quote['original_total'] ?? $total),
                'discount_total' => (float) ($quote['discount_total'] ?? 0),
                'total' => $total,
                'deposit' => $rateValue !== null ? round($total * $rateValue / 100, 2) : null,
                'deposit_rate' => $rateValue,
                'items' => $quote['items'] ?? [],
                'csrfToken' => csrf_hash(),
            ],
        ]);
    }

    public function detail(int $bookingId)
    {
        $booking = $this->bookingForPlayer($bookingId);
        if (! $booking) {
            return $this->response->setStatusCode(404)->setBody('Reserva no encontrada.');
        }
        return $this->page('reservation_detail', ['booking' => $booking]);
    }

    private function myBookings(): array
    {
        $player = $this->player();
        if (! $player) {
            return [];
        }
        $db = \Config\Database::connect();
        return $db->table('player_booking_links l')
            ->select('b.*, f.name AS field_name, f.service_type, s.name AS service_name, s.is_sport')
            ->join('bookings b', 'b.id = l.booking_id', 'inner')
            ->join('fields f', 'f.id = b.id_field', 'left')
            ->join('services s', 's.code = f.service_type', 'left')
            ->where('l.player_id', (int) $player['id'])
            ->orderBy('b.date', 'DESC')
            ->orderBy('b.time_from', 'DESC')
            ->get()->getResultArray();
    }

    private function bookingForPlayer(int $bookingId): ?array
    {
        $player = $this->player();
        if (! $player || $bookingId <= 0) {
            return null;
        }
        $db = \Config\Database::connect();
        return $db->table('player_booking_links l')
            ->select('b.*, f.name AS field_name, f.service_type, s.name AS service_name, s.is_sport')
            ->join('bookings b', 'b.id = l.booking_id', 'inner')
            ->join('fields f', 'f.id = b.id_field', 'left')
            ->join('services s', 's.code = f.service_type', 'left')
            ->where('l.player_id', (int) $player['id'])
            ->where('b.id', $bookingId)
            ->get()->getRowArray() ?: null;
    }

    private function minutesToTime(int $minutes): string
    {
        $minutes %= 1440;
        if ($minutes < 0) {
            $minutes += 1440;
        }
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
