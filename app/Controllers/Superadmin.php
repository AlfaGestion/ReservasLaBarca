<?php

namespace App\Controllers;

use App\Models\BookingsModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\LocalitiesModel;
use App\Models\BookingSlotsModel;
use App\Models\MercadoPagoKeysModel;
use App\Models\MercadoPagoModel;
use App\Models\OffersModel;
use App\Models\PaymentsModel;
use App\Models\RateModel;
use App\Models\TimeModel;
use App\Models\UsersModel;

class Superadmin extends BaseController
{
    private function cleanupExpiredPendingBookings(): void
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $mercadoPagoModel = new MercadoPagoModel();
        $paymentsModel = new PaymentsModel();

        $now = date('Y-m-d H:i:s');
        $threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        // 1) Tomar los slots pending vencidos para identificar reservas candidatas.
        $expiredPendingSlots = $bookingSlotsModel
            ->select('booking_id')
            ->where('active', 1)
            ->where('status', 'pending')
            ->where('expires_at <', $now)
            ->findAll();

        // 2) Expirar esos slots.
        $this->expireActiveBookingSlots($bookingSlotsModel, [
            'status' => 'pending',
            'expires_at <' => $now,
        ]);

        if (empty($expiredPendingSlots)) {
            return;
        }

        $candidateIds = [];
        foreach ($expiredPendingSlots as $slot) {
            $bookingId = (int)($slot['booking_id'] ?? 0);
            if ($bookingId > 0) {
                $candidateIds[$bookingId] = true;
            }
        }
        $candidateIds = array_keys($candidateIds);

        // Fallback: reservas provisionales vencidas por booking_time (aunque no tengan slot enlazado).
        $staleBookings = $bookingsModel
            ->select('id')
            ->where('annulled', 0)
            ->where('mp', 0)
            ->where('payment <=', 0)
            ->where('booking_time <', $threshold)
            ->groupStart()
            ->where('approved', 0)
            ->orWhere('approved', null)
            ->groupEnd()
            ->findAll();

        foreach ($staleBookings as $row) {
            $bookingId = (int)($row['id'] ?? 0);
            if ($bookingId > 0) {
                $candidateIds[$bookingId] = true;
            }
        }

        $candidateIds = array_keys($candidateIds);

        if (empty($candidateIds)) {
            return;
        }
        // 3) Solo reservas provisionales sin pago confirmado.
        $candidates = $bookingsModel->whereIn('id', $candidateIds)
            ->where('annulled', 0)
            ->where('mp', 0)
            ->where('payment <=', 0)
            ->groupStart()
            ->where('approved', 0)
            ->orWhere('approved', null)
            ->groupEnd()
            ->findAll();

        if (empty($candidates)) {
            return;
        }

        $idsToDelete = [];
        foreach ($candidates as $booking) {
            $bookingId = (int)$booking['id'];

            $hasApprovedMp = $mercadoPagoModel->where('id_booking', $bookingId)
                ->where('status', 'approved')
                ->first();

            $hasPayment = $paymentsModel->where('id_booking', $bookingId)->first();

            if (!$hasApprovedMp && !$hasPayment) {
                $idsToDelete[] = $bookingId;
            }
        }

        if (empty($idsToDelete)) {
            return;
        }

        // 4) Limpiar registros relacionados y luego la reserva.
        $this->expireActiveBookingSlots($bookingSlotsModel, [], [
            'booking_id' => $idsToDelete,
        ]);

        $paymentsModel->whereIn('id_booking', $idsToDelete)->delete();
        $mercadoPagoModel->whereIn('id_booking', $idsToDelete)->delete();
        $bookingsModel->delete($idsToDelete);
    }

    public function index()
    {
        $fieldsModel = new FieldsModel();
        $rateModel = new RateModel();
        $customersModel = new CustomersModel();
        $timeModel = new TimeModel();
        $usersModel = new UsersModel();
        $offerModel = new OffersModel();
        $localitiesModel = new LocalitiesModel();
        $configModel = new ConfigModel();

        $users = $usersModel->where('active', 1)
        ->where('user !=', 'testuser')
        ->findAll();

        // The bookings table is populated client-side via getActiveBookings().
        // Loading the full history here makes the admin page too slow.
        $bookings = [];

        $getTime = $timeModel->findAll();
        if ($getTime) {
            $time = $getTime[0];
        } else {
            $time = [
                'from' => 0,
                'until' => 0,
                'from_cut' => 0,
                'until_cut' => 0,
                'nocturnal_time' => 0
            ];
        }

        $openingTime = $timeModel->getOpeningTime();

        $getRate = $rateModel->findAll();
        if ($getRate) {
            $rate = $getRate[0];
        } else {
            $rate = 0;
        }

        $getOfferRate = $offerModel->findAll();
        if ($getOfferRate) {
            $offerRate = $getOfferRate[0];
        } else {
            $offerRate = 0;
        }

        $fields = $fieldsModel->findAll();

        $customers = $customersModel->findAll();
        $localities = $localitiesModel->orderBy('name', 'ASC')->findAll();
        $closureTextRow = $configModel->where('clave', 'texto_cierre')->first();
        $closureText = $closureTextRow['valor'] ?? '';
        if (!is_string($closureText) || trim($closureText) === '') {
            $closureText = "Aviso importante\n\n"
                . "Queremos informarles que el dÃ­a <fecha> las canchas permanecerÃ¡n cerradas.\n"
                . "Pedimos disculpas por las molestias que esto pueda ocasionar.\n\n"
                . "De todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\n"
                . "Muchas gracias por la comprensiÃ³n y por seguir eligiÃ©ndonos.";
        }
        $bookingEmailRow = $configModel->where('clave', 'email_reservas')->first();
        $bookingEmail = $bookingEmailRow['valor'] ?? '';

        return view('superadmin/index', [
            'bookings' => $bookings,
            'rate' => $rate,
            'customers' => $customers,
            'time' => $time,
            'openingTime' => $openingTime,
            'fields' => $fields,
            'users' => $users,
            'offerRate' => $offerRate,
            'localities' => $localities,
            'closureText' => $closureText,
            'bookingEmail' => $bookingEmail,
        ]);
    }

    public function saveField()
    {
        $fieldsModel = new FieldsModel();
        $isAjax = $this->request->isAJAX();

        $this->request->getVar('iluminacion') ? $iluminacion = true : $iluminacion = false;
        $this->request->getVar('tipoTecho') ? $techada = true : $techada = false;

        $nombre = $this->request->getVar('nombre');
        $medidas = $this->request->getVar('medidas');
        $tipoPiso = $this->request->getVar('tipoPiso');
        $tipoCancha = $this->request->getVar('tipoCancha');
        $serviceType = $this->request->getVar('serviceType') ?: 'football';
        $blockMinutes = (int)($this->request->getVar('blockMinutes') ?: ($serviceType === 'padel' ? 90 : 60));
        $priceUnitLabel = $serviceType === 'padel' ? 'por bloque de 1:30' : 'por hora';
        $valor = $this->request->getVar('valor');
        $valorIluminacion = $this->request->getVar('valorIluminacion');


        $query = [
            'name' => $nombre,
            'sizes' => $medidas,
            'floor_type' => $tipoPiso,
            'field_type' => $tipoCancha,
            'service_type' => $serviceType,
            'block_minutes' => $blockMinutes,
            'price_unit_label' => $priceUnitLabel,
            'ilumination' => $iluminacion,
            'roofed' => $techada,
            'value' => $valor,
            'ilumination_value' => $valorIluminacion,
            'disabled' => 0,
        ];

        if ($nombre == '' || $valor == '') {
            if ($isAjax) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => true,
                    'message' => 'Debe ingresar todos los datos',
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ingresar todos los datos']);
        }


        try {
            $id = $fieldsModel->insert($query);
            if ($isAjax) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Servicio creado correctamente',
                    'data' => $fieldsModel->find($id),
                ]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => true,
                    'message' => 'Error al insertar datos: ' . $e->getMessage(),
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }

        return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio creado correctamente']);
    }

    public function editField($id)
    {
        $fieldsModel = new FieldsModel();
        $isAjax = $this->request->isAJAX();

        $this->request->getVar('iluminacion') ? $iluminacion = true : $iluminacion = false;
        $this->request->getVar('tipoTecho') ? $techada = true : $techada = false;

        $nombre = $this->request->getVar('nombre');
        $medidas = $this->request->getVar('medidas');
        $tipoPiso = $this->request->getVar('tipoPiso');
        $tipoCancha = $this->request->getVar('tipoCancha');
        $serviceType = $this->request->getVar('serviceType') ?: 'football';
        $blockMinutes = (int)($this->request->getVar('blockMinutes') ?: ($serviceType === 'padel' ? 90 : 60));
        $priceUnitLabel = $serviceType === 'padel' ? 'por bloque de 1:30' : 'por hora';
        $valor = $this->request->getVar('valor');
        $valorIluminacion = $this->request->getVar('valorIluminacion');
        $disabled = $this->request->getVar('disabled') ? 1 : 0;


        $query = [
            'name' => $nombre,
            'sizes' => $medidas,
            'floor_type' => $tipoPiso,
            'field_type' => $tipoCancha,
            'service_type' => $serviceType,
            'block_minutes' => $blockMinutes,
            'price_unit_label' => $priceUnitLabel,
            'ilumination' => $iluminacion,
            'roofed' => $techada,
            'value' => $valor,
            'ilumination_value' => $valorIluminacion,
            'disabled' => $disabled,
        ];

        if ($nombre == '' || $valor == '') {
            if ($isAjax) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => true,
                    'message' => 'Debe ingresar todos los datos',
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ingresar todos los datos']);
        }


        try {
            $fieldsModel->update($id, $query);
            if ($isAjax) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Servicio editado correctamente',
                    'data' => $fieldsModel->find($id),
                ]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => true,
                    'message' => 'Error al insertar datos: ' . $e->getMessage(),
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }

        return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio editado correctamente']);
    }

    public function getActiveBookings()
    {
        $this->cleanupExpiredPendingBookings();

        $fieldsModel = new FieldsModel();
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();

        $getBookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->where('annulled', 0)
            ->orderBy('time_from', 'ASC')
            ->findAll();

        $bookings = [];
        $bookingIds = array_column($getBookings, 'id');
        $paidByBooking = [];

        if (!empty($bookingIds)) {
            $paymentsRows = $paymentsModel
                ->select('id_booking, SUM(amount) as paid_total')
                ->whereIn('id_booking', $bookingIds)
                ->groupBy('id_booking')
                ->findAll();

            foreach ($paymentsRows as $pr) {
                $paidByBooking[(int)$pr['id_booking']] = (float)($pr['paid_total'] ?? 0);
            }
        }

        foreach ($getBookings as $booking) {
            $bookingId = (int)$booking['id'];
            $paymentsSum = $paidByBooking[$bookingId] ?? 0.0;
            $bookingPaid = (float)($booking['payment'] ?? 0);
            $paid = max($paymentsSum, $bookingPaid);
            $total = (float)($booking['total'] ?? 0);
            $difference = $total - $paid;
            if ($difference < 0) {
                $difference = 0;
            }

            $reserva = [
                'id' => $booking['id'],
                'cancha' => $fieldsModel->getField($booking['id_field'])['name'],
                'fecha' => date("d/m/Y", strtotime($booking['date'])),
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'creado_por' => $booking['created_by_name'] ?? $booking['created_by_type'] ?? 'N/D',
                'editado_por' => $booking['edited_by_name'] ?? null,
                'editado_en' => $booking['edited_at'] ?? null,
                'pago_total' => $paid >= $total ? 'Si' : 'No',
                'total_reserva' => $booking['total'],
                'diferencia' => $difference,
                'monto_reserva' => $paid,
                'descripcion' => $booking['description'],
                'metodo_pago' => $booking['payment_method'],
                'anulada'     => $booking['annulled'],
                'mp'        => $booking['mp'],
            ];

            array_push($bookings, $reserva);
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $bookings, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getAnnulledBookings()
    {
        $fieldsModel = new FieldsModel();
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();

        $getBookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->where('annulled', 1)
            ->orderBy('time_from', 'ASC')
            ->findAll();

        $bookings = [];
        $bookingIds = array_column($getBookings, 'id');
        $paidByBooking = [];

        if (!empty($bookingIds)) {
            $paymentsRows = $paymentsModel
                ->select('id_booking, SUM(amount) as paid_total')
                ->whereIn('id_booking', $bookingIds)
                ->groupBy('id_booking')
                ->findAll();

            foreach ($paymentsRows as $pr) {
                $paidByBooking[(int)$pr['id_booking']] = (float)($pr['paid_total'] ?? 0);
            }
        }

        foreach ($getBookings as $booking) {
            $bookingId = (int)$booking['id'];
            $paymentsSum = $paidByBooking[$bookingId] ?? 0.0;
            $bookingPaid = (float)($booking['payment'] ?? 0);
            $paid = max($paymentsSum, $bookingPaid);
            $total = (float)($booking['total'] ?? 0);
            $difference = $total - $paid;
            if ($difference < 0) {
                $difference = 0;
            }

            $reserva = [
                'id' => $booking['id'],
                'cancha' => $fieldsModel->getField($booking['id_field'])['name'],
                'fecha' => date("d/m/Y", strtotime($booking['date'])),
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'creado_por' => $booking['created_by_name'] ?? $booking['created_by_type'] ?? 'N/D',
                'editado_por' => $booking['edited_by_name'] ?? null,
                'editado_en' => $booking['edited_at'] ?? null,
                'pago_total' => $paid >= $total ? 'Si' : 'No',
                'total_reserva' => $booking['total'],
                'diferencia' => $difference,
                'monto_reserva' => $paid,
                'descripcion' => $booking['description'],
                'metodo_pago' => $booking['payment_method'],
                'anulada'     => $booking['annulled'],
            ];

            array_push($bookings, $reserva);
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $bookings, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function checkCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $bookingsModel = new BookingsModel();
        $fieldsModel = new FieldsModel();

        $query = $bookingsModel->where('date', $date)->where('annulled', 0);
        if ($field !== 'all') {
            $query->where('id_field', $field);
        }

        $bookings = $query->findAll();

        $result = [];
        foreach ($bookings as $booking) {
            $fieldName = $fieldsModel->getField($booking['id_field'])['name'] ?? 'N/D';
            $result[] = [
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'cancha' => $fieldName,
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
            ];
        }

        $fieldLabel = 'Todas';
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
        }

        $payload = [
            'fecha' => $date,
            'canchaLabel' => $fieldLabel,
            'bookings' => $result,
        ];

        return $this->response->setJSON($this->setResponse(null, null, $payload, 'Respuesta exitosa'));
    }

    public function saveCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $today = date('Y-m-d');
        if ($date < $today) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'No se pueden informar cierres con fecha anterior a hoy.'));
        }

        $fieldsModel = new FieldsModel();
        $cancelModel = new CancelReservationsModel();

        $sameDateRows = $cancelModel->where('cancel_date', $date)->findAll();
        $hasAllClosure = false;
        $hasSameFieldClosure = false;
        foreach ($sameDateRows as $row) {
            if (empty($row['field_id'])) {
                $hasAllClosure = true;
            }
            if ($field !== 'all' && (int)($row['field_id'] ?? 0) === (int)$field) {
                $hasSameFieldClosure = true;
            }
        }

        if ($field === 'all' && !empty($sameDateRows)) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existen cierres para esa fecha. Solo el primer registro puede editarse a "Todas".'));
        }
        if ($field !== 'all' && $hasAllClosure) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para Todas las canchas en esa fecha.'));
        }
        if ($field !== 'all' && $hasSameFieldClosure) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para esa cancha en esa fecha.'));
        }

        $fieldLabel = 'Todas';
        $fieldId = null;
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
            $fieldId = $field;
        }

        $userName = session()->get('name') ?? session()->get('user') ?? 'N/D';

        $payload = [
            'cancel_date' => $date,
            'field_id' => $fieldId,
            'field_label' => $fieldLabel,
            'user_name' => $userName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $cancelModel->insert($payload);
            return $this->response->setJSON($this->setResponse(null, null, null, 'CancelaciÃ³n registrada.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function getCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $dateFrom = $data->fechaDesde ?? null;
        $dateTo = $data->fechaHasta ?? null;
        $field = $data->cancha ?? 'all';

        $cancelModel = new CancelReservationsModel();
        $query = $cancelModel;
        if ($date) {
            $query = $query->where('cancel_date', $date);
        } elseif ($dateFrom || $dateTo) {
            if ($dateFrom) {
                $query = $query->where('cancel_date >=', $dateFrom);
            }
            if ($dateTo) {
                $query = $query->where('cancel_date <=', $dateTo);
            }
        } else {
            $query = $query->where('cancel_date >=', date('Y-m-d'));
        }
        if ($field !== 'all') {
            $query = $query->where('field_id', (int)$field);
        }
        $rows = $query
            ->orderBy('cancel_date', 'DESC')
            ->orderBy('field_label', 'ASC')
            ->findAll();

        return $this->response->setJSON($this->setResponse(null, null, $rows, 'Respuesta exitosa'));
    }

    public function updateCancelReservation()
    {
        $data = $this->request->getJSON();
        $id = $data->id ?? null;
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$id) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'ID invÃ¡lido.'));
        }
        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $today = date('Y-m-d');
        if ($date < $today) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'No se pueden editar cierres con fecha anterior a hoy.'));
        }

        $fieldsModel = new FieldsModel();
        $cancelModel = new CancelReservationsModel();
        $row = $cancelModel->find($id);
        if (!$row) {
            return $this->response->setJSON($this->setResponse(404, true, null, 'Cierre no encontrado.'));
        }

        $sameDateRows = $cancelModel->where('cancel_date', $date)->findAll();
        $firstId = null;
        $hasAllOther = false;
        $hasSameFieldOther = false;
        foreach ($sameDateRows as $r) {
            $rowId = (int)$r['id'];
            if ($firstId === null || $rowId < $firstId) {
                $firstId = $rowId;
            }
            if ($rowId === (int)$id) {
                continue;
            }
            if (empty($r['field_id'])) {
                $hasAllOther = true;
            }
            if ($field !== 'all' && (int)($r['field_id'] ?? 0) === (int)$field) {
                $hasSameFieldOther = true;
            }
        }

        if ($field === 'all') {
            if ($firstId !== null && (int)$id !== (int)$firstId) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Solo el primer cierre de la fecha puede cambiarse a "Todas".'));
            }
        } else {
            if ($hasAllOther) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para Todas las canchas en esa fecha.'));
            }
            if ($hasSameFieldOther) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para esa cancha en esa fecha.'));
            }
        }

        $fieldLabel = 'Todas';
        $fieldId = null;
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
            $fieldId = $field;
        }

        $userName = session()->get('name') ?? session()->get('user') ?? 'N/D';
        $payload = [
            'cancel_date' => $date,
            'field_id' => $fieldId,
            'field_label' => $fieldLabel,
            'user_name' => $userName,
        ];

        try {
            $cancelModel->update($id, $payload);
            if ($field === 'all') {
                $cancelModel->where('cancel_date', $date)
                    ->where('id !=', $id)
                    ->delete();
            }
            return $this->response->setJSON($this->setResponse(null, null, null, 'Cierre actualizado.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function deleteCancelReservation()
    {
        $data = $this->request->getJSON();
        $id = $data->id ?? null;

        if (!$id) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'ID invÃ¡lido.'));
        }

        $cancelModel = new CancelReservationsModel();
        try {
            $row = $cancelModel->find($id);
            if (!$row) {
                return $this->response->setJSON($this->setResponse(404, true, null, 'Cierre no encontrado.'));
            }

            $today = date('Y-m-d');
            if (!empty($row['cancel_date']) && $row['cancel_date'] < $today) {
                return $this->response->setJSON($this->setResponse(403, true, null, 'No se pueden editar o eliminar cierres con fecha anterior a hoy.'));
            }

            $cancelModel->delete($id);
            return $this->response->setJSON($this->setResponse(null, null, null, 'Cierre eliminado.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function saveConfigGeneral()
    {
        $data = $this->request->getJSON();
        $textoCierre = $data->textoCierre ?? '';
        $emailReservas = $data->emailReservas ?? '';

        $configModel = new ConfigModel();

        try {
            $existingText = $configModel->where('clave', 'texto_cierre')->first();
            if ($existingText) {
                $configModel->update($existingText['id'], ['valor' => $textoCierre]);
            } else {
                $configModel->insert(['clave' => 'texto_cierre', 'valor' => $textoCierre]);
            }

            $existingEmail = $configModel->where('clave', 'email_reservas')->first();
            if ($existingEmail) {
                $configModel->update($existingEmail['id'], ['valor' => $emailReservas]);
            } else {
                $configModel->insert(['clave' => 'email_reservas', 'valor' => $emailReservas]);
            }

            return $this->response->setJSON($this->setResponse(null, null, null, 'ConfiguraciÃ³n guardada.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function configMpView()
    {
        return view('mercadoPago/config', ['errors' => []]);
    }

    public function configMp()
    {
        $mpKeysModel = new MercadoPagoKeysModel();

        $publicKey = $this->request->getVar('publicKeyMp');
        $accessToken = $this->request->getVar('accesTokenMp');

        $query = [
            'public_key'   => $publicKey,
            'access_token' => $accessToken,
        ];

        try {
            $existing = $mpKeysModel->first();
            if ($existing) {
                $mpKeysModel->update($existing['id'], $query);
            } else {
                $mpKeysModel->insert($query);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Datos insertados con Ã©xito: ']);
        } catch (\Exception $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }
    }

    public function deleteUser($id)
    {
        $usersModel = new UsersModel();
        $isAjaxRequest = $this->request->isAJAX();
        try {
            $usersModel->update($id, ['active' => 0]);
            $deletedCurrentUser = (int) session()->get('id_user') === (int) $id;

            if ($deletedCurrentUser) {
                session()->destroy();
            }

            if ($isAjaxRequest) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Usuario eliminado correctamente',
                    'user' => [
                        'id' => $id,
                    ],
                    'loggedOut' => $deletedCurrentUser,
                    'csrf' => [
                        'name' => csrf_token(),
                        'hash' => csrf_hash(),
                    ],
                ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            if ($isAjaxRequest) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'error' => true,
                        'message' => 'Error al eliminar usuario: ' . $e->getMessage(),
                        'csrf' => [
                            'name' => csrf_token(),
                            'hash' => csrf_hash(),
                        ],
                    ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al eliminar usuario: ' . $e->getMessage()]);
        }
    }

    public function setResponse($code = 200, $error = false, $data = null, $message = '')
    {
        $response = [
            'error' => $error,
            'code' => $code,
            'data' => $data,
            'message' => $message,
        ];

        return $response;
    }
}

