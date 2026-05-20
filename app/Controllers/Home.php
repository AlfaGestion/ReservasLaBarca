<?php

namespace App\Controllers;

use App\Models\BookingsModel;
use App\Models\BookingSlotsModel;
use App\Models\CustomersModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\FieldsModel;
use App\Models\LocalitiesModel;
use App\Models\MercadoPagoModel;
use App\Models\OffersModel;
use App\Models\ServicesModel;
use App\Models\TimeModel;
use DateInterval;
use DateTime;

class Home extends BaseController
{
    public function index()
    {
        $offersModel = new OffersModel();

        $currentDate = date("Y-m-d");
        $existingOffer = $offersModel->first();

        if (isset($existingOffer)) {


            if ($existingOffer['expiration_date'] == $currentDate) {
                $update = [
                    'value' => 0,
                    'description' => '',
                    'expiration_date' => '',
                ];

                $offersModel->update($existingOffer['id'], $update);
            }
        }

        $oferta = $offersModel->first();

        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();
        $services = $servicesModel->getActiveServices(true);
        $fieldsModel = new FieldsModel();
        $fields = $fieldsModel->getFields();

        $localitiesModel = new LocalitiesModel();
        $localities = $localitiesModel->orderBy('name', 'ASC')->findAll();

        // dd($fields);

        $timeModel = new TimeModel();
        $openingTime = $timeModel->getOpeningTime();
        $isSunday = $timeModel->first()['is_sunday'];


        // $time = [];

        // if ($openingTime) {
        //     if ($openingTime[0]['from']) {
        //         $from = $openingTime[0]['from'] - 1;
        //         $until = $openingTime[0]['until'];

        //         while ($from != $until) {
        //             $from++;
        //             if($from == '24') $from = '00';

        //             array_push($time, $from);
        //         }
        //     }
        // }

        // if ($openingTime) {
        //     if ($openingTime[0]['from_cut']) {
        //         $from_cut = $openingTime[0]['from_cut'] - 1;
        //         $until_cut = $openingTime[0]['until_cut'];

        //         while ($from_cut != $until_cut) {
        //             $from_cut++;
        //             array_push($time, $from_cut);
        //         }
        //     }
        // }

        return view('index', ['fields' => $fields, 'services' => $services, 'time' => $openingTime, 'oferta' => $oferta, 'esDomingo' => $isSunday, 'localities' => $localities]);
    }

    // public function deleteRejected()
    // {
    //     $bookingsModel = new BookingsModel();
    //     $mercadoPagoModel = new MercadoPagoModel();

    //     try {
    //         $mercadoPagoModel->where('status', 'rejected')->delete();
    //         $bookingsModel->where('approved', 0)
    //             ->orWhere('approved', null)->delete();
    //         return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
    //     } catch (\Exception $e) {
    //         return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
    //     }
    // }

    public function deleteRejected()
    {
        $bookingsModel = new BookingsModel();
        $mercadoPagoModel = new MercadoPagoModel();
        // Necesitas cargar el modelo de la tabla que causa el error
        $paymentsModel = new \App\Models\PaymentsModel();
        $bookingSlotsModel = new BookingSlotsModel();

        $nueva_hora = date("Y-m-d H:i:s", strtotime("-5 minutes"));

        try {
            // Expira slots pendientes vencidos
            $this->expireActiveBookingSlots($bookingSlotsModel, [
                'status' => 'pending',
                'expires_at <' => date('Y-m-d H:i:s'),
            ]);

            // 1. Buscamos las reservas candidatas
            $bookingsCandidates = $bookingsModel->groupStart()
                ->where('approved', 0)
                ->orWhere('approved', null)
                ->groupEnd()
                ->where('booking_time <', $nueva_hora)
                ->findAll();

            if (empty($bookingsCandidates)) {
                return $this->response->setJSON($this->setResponse(null, null, null, 'Sin registros para limpiar'));
            }

            $idsToDelete = [];
            foreach ($bookingsCandidates as $booking) {
                // Verificamos en MP si hay pago aprobado para no borrar por error
                $hasApproved = $mercadoPagoModel->where('id_booking', $booking['id'])
                    ->where('status', 'approved')
                    ->first();
                if (!$hasApproved) {
                    $idsToDelete[] = $booking['id'];
                }
            }

            if (!empty($idsToDelete)) {
                // ORDEN DE BORRADO (Hijos primero, Padre al final)

                // 0. Expirar slots de esas reservas
                $this->expireActiveBookingSlots($bookingSlotsModel, [], [
                    'booking_id' => $idsToDelete,
                ]);

                // 1. Borrar de la tabla 'payments' (la que dio el error ahora)
                $paymentsModel->whereIn('id_booking', $idsToDelete)->delete();

                // 2. Borrar de la tabla 'mercado_pago'
                $mercadoPagoModel->whereIn('id_booking', $idsToDelete)->delete();

                // 3. Finalmente borrar la reserva 'bookings'
                $bookingsModel->delete($idsToDelete);
            }

            return $this->response->setJSON($this->setResponse(null, null, null, 'Limpieza exitosa de todas las tablas'));
        } catch (\Exception $e) {
            // Devuelve el error detallado para seguir debugueando si falta otra tabla
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }


    public function infoReserva()
    {
        $data = $this->request->getJSON();
        $fieldsModel = new FieldsModel();

        $datosReserva = [
            'fecha'        => $data->fecha,
            'cancha'       => $fieldsModel->getField($data->cancha)['name'],
            'horarioDesde' => $data->horarioDesde,
            'horarioHasta' => $data->horarioHasta,
            'nombre'       => $data->nombre,
            'telefono'     => $data->telefono,
            'localidad'    => $data->localidad ?? null,
        ];

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $datosReserva, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function checkClosure()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $cancelModel = new CancelReservationsModel();
        $fieldsModel = new FieldsModel();
        $configModel = new ConfigModel();

        $closures = $cancelModel->where('cancel_date', $date)->findAll();
        $closureTextRow = $configModel->where('clave', 'texto_cierre')->first();
        $closureText = $closureTextRow['valor'] ?? '';
        if (!is_string($closureText) || trim($closureText) === '') {
            $closureText = "Aviso importante\n\n"
                . "Queremos informarles que el día <fecha> las canchas permanecerán cerradas.\n"
                . "Pedimos disculpas por las molestias que esto pueda ocasionar.\n\n"
                . "De todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\n"
                . "Muchas gracias por la comprensión y por seguir eligiéndonos.";
        }
        if (empty($closures)) {
            return $this->response->setJSON($this->setResponse(null, null, [
                'closed' => false,
                'scope' => 'none',
                'closedAll' => false,
                'closedFields' => [],
                'label' => '',
                'fecha' => $date,
                'message' => $closureText,
            ], 'Respuesta exitosa'));
        }

        $closedAll = false;
        $closedField = false;
        $fieldLabel = '';
        $closedFields = [];

        foreach ($closures as $c) {
            if (empty($c['field_id'])) {
                $closedAll = true;
            }
            if (!empty($c['field_id'])) {
                $closedFields[] = (int)$c['field_id'];
            }
            if ($field !== 'all' && !empty($c['field_id']) && (int)$c['field_id'] === (int)$field) {
                $closedField = true;
                $fieldLabel = $c['field_label'] ?? '';
            }
        }

        if ($field === 'all') {
            return $this->response->setJSON($this->setResponse(null, null, [
                'closed' => $closedAll,
                'scope' => $closedAll ? 'all' : 'none',
                'closedAll' => $closedAll,
                'closedFields' => $closedFields,
                'label' => 'Todas',
                'fecha' => $date,
                'message' => $closureText,
            ], 'Respuesta exitosa'));
        }

        if ($closedAll) {
            return $this->response->setJSON($this->setResponse(null, null, [
                'closed' => true,
                'scope' => 'all',
                'closedAll' => true,
                'closedFields' => $closedFields,
                'label' => 'Todas',
                'fecha' => $date,
                'message' => $closureText,
            ], 'Respuesta exitosa'));
        }

        if ($closedField) {
            if ($fieldLabel === '') {
                $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
            }
            return $this->response->setJSON($this->setResponse(null, null, [
                'closed' => true,
                'scope' => 'field',
                'closedAll' => false,
                'closedFields' => $closedFields,
                'fieldId' => (int)$field,
                'label' => $fieldLabel,
                'fecha' => $date,
                'message' => $closureText,
            ], 'Respuesta exitosa'));
        }

        return $this->response->setJSON($this->setResponse(null, null, [
            'closed' => false,
            'scope' => 'none',
            'closedAll' => false,
            'closedFields' => $closedFields,
            'label' => '',
            'fecha' => $date,
            'message' => $closureText,
        ], 'Respuesta exitosa'));
    }

    public function getUpcomingClosure()
    {
        $cancelModel = new CancelReservationsModel();
        $configModel = new ConfigModel();

        $today = date('Y-m-d');
        $nextClosure = $cancelModel
            ->where('cancel_date >', $today)
            ->orderBy('cancel_date', 'ASC')
            ->first();

        if (!$nextClosure) {
            return $this->response->setJSON($this->setResponse(null, null, null, 'Sin cierres proximos'));
        }

        $closureDate = $nextClosure['cancel_date'];
        $closureTextRow = $configModel->where('clave', 'texto_cierre')->first();
        $closureText = $closureTextRow['valor'] ?? '';
        if (!is_string($closureText) || trim($closureText) === '') {
            $closureText = "Aviso importante\n\n"
                . "Queremos informarles que el dia <fecha> las canchas permaneceran cerradas.\n"
                . "Pedimos disculpas por las molestias que esto pueda ocasionar.\n\n"
                . "De todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\n"
                . "Muchas gracias por la comprension y por seguir eligiendonos.";
        }

        $isAllFields = empty($nextClosure['field_id']);
        $scopeText = $isAllFields
            ? 'Cierre informado para todas las canchas.'
            : 'Cierre informado para una cancha especifica.';

        return $this->response->setJSON($this->setResponse(null, null, [
            'fecha' => $closureDate,
            'closedAll' => $isAllFields,
            'message' => $closureText,
            'scopeText' => $scopeText,
        ], 'Respuesta exitosa'));
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
