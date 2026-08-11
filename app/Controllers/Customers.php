<?php

namespace App\Controllers;

use App\Libraries\CustomerOfferService;
use App\Models\CustomerOffersModel;
use App\Models\CustomersModel;
use App\Models\CustomerOfferFieldsModel;
use App\Models\CustomerOfferServicesModel;
use App\Models\FieldsModel;
use App\Models\ServicesModel;

class Customers extends BaseController
{
    private function getCustomerOfferService(): CustomerOfferService
    {
        return new CustomerOfferService();
    }

    private function decodeSelectionList($value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('trim', $value), static fn ($item) => $item !== '')));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_unique(array_filter(array_map('trim', $decoded), static fn ($item) => $item !== '')));
        }

        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw) ?: []), static fn ($item) => $item !== '')));
    }

    private function findCustomerByPhoneVariants(string $phone, ?int $ignoreId = null): ?array
    {
        $service = $this->getCustomerOfferService();
        $variants = [$service->normalizePhone($phone)];
        if ($variants[0] !== '') {
            $withoutLeadingZero = ltrim($variants[0], '0');
            if ($withoutLeadingZero !== '') {
                $variants[] = $withoutLeadingZero;
                $variants[] = '0' . $withoutLeadingZero;
            }
        }
        $variants = array_values(array_unique(array_filter($variants, static fn ($value) => $value !== '')));

        if ($variants === []) {
            return null;
        }

        $model = new CustomersModel();
        $builder = $model;
        $first = true;
        foreach ($variants as $variant) {
            if ($first) {
                $builder = $builder->where('phone', $variant);
                $first = false;
            } else {
                $builder = $builder->orWhere('phone', $variant);
            }
        }

        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->first();
    }

    private function isValidDate(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function enrichCustomerWithOffer(array $customer, CustomerOfferService $offerService): array
    {
        $summary = $offerService->getCustomerOfferSummary((int) ($customer['id'] ?? 0));
        $legacyOffer = (int) ($customer['offer'] ?? 0);

        $customer['customer_offer'] = $summary;
        $customer['customer_offer_active'] = (int) ($summary['active'] ?? 0);
        $customer['offer_value'] = $summary['value'] ?? 0;
        $customer['offer_description'] = $summary['description'] ?? '';
        $customer['offer_expiration_date'] = $summary['expiration_date'] ?? null;
        $customer['offer_summary'] = $summary['scope_label'] ?? '';
        $customer['offer_scope'] = $summary['scope_label'] ?? '';
        $customer['offer'] = $summary && (int) ($summary['active'] ?? 0) === 1 ? 1 : $legacyOffer;
        $customer['offer_badge'] = $summary && (int) ($summary['active'] ?? 0) === 1
            ? rtrim(rtrim(number_format((float) ($summary['value'] ?? 0), 2, ',', '.'), '0'), ',') . '% OFF'
            : ($legacyOffer === 1 ? 'Legacy' : 'No');

        return $customer;
    }

    private function getDistinctCustomers(?int $limit = null, ?int $offer = null): array
    {
        $db = \Config\Database::connect();

        $latestIdsSql = $db->table('customers')
            ->select('MAX(id) AS id')
            ->groupBy('phone')
            ->getCompiledSelect();

        $builder = $db->table('customers c')
            ->select('c.*')
            ->join("($latestIdsSql) latest", 'latest.id = c.id', 'inner')
            ->orderBy('c.id', 'DESC');

        if ($limit !== null && $limit > 0) {
            $builder->limit($limit);
        }

        $customers = $builder->get()->getResultArray();
        $offerService = $this->getCustomerOfferService();

        $customers = array_map(
            fn (array $customer) => $this->enrichCustomerWithOffer($customer, $offerService),
            $customers
        );

        if ($offer !== null) {
            $customers = array_values(array_filter($customers, static fn (array $customer) => (int) ($customer['offer'] ?? 0) === $offer));
        }

        return $customers;
    }

    public function register()
    {
        return view('customers/register');
    }

    public function registerWindow()
    {
        $embedded = filter_var($this->request->getGet('iframe'), FILTER_VALIDATE_BOOLEAN);

        return view('customers/register_window', [
            'embedded' => $embedded,
        ]);
    }

    private function handleRegisterSubmission(bool $asJson = false)
    {
        $modelCustomers = new CustomersModel();

        $areaCode = trim((string) $this->request->getVar('areaCode'));
        $phoneInput = trim((string) $this->request->getVar('phone'));
        $phone = $this->getCustomerOfferService()->normalizePhone($areaCode . $phoneInput);
        $name = trim((string) $this->request->getVar('name'));
        $lastName = trim((string) $this->request->getVar('last_name'));
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));

        $fail = static function (string $message, int $status = 422) use ($asJson) {
            if ($asJson) {
                return service('response')->setStatusCode($status)->setJSON([
                    'error' => true,
                    'code' => $status,
                    'data' => null,
                    'message' => $message,
                ]);
            }

            return redirect()->to('customers/register')->with('msg', ['type' => 'danger', 'body' => $message]);
        };

        if ($phone === '' || $name === '' || $lastName === '' || $dni === '') {
            return $fail('Debe completar todos los campos');
        }

        $this->ensureLocalityExists($city);

        $existingPhone = $this->findCustomerByPhoneVariants($phone);
        if ($existingPhone) {
            return $fail('El telefono coincide con un usuario ya registrado');
        }

        $query = [
            'name' => $name,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone' => $phone,
            'offer' => 0,
            'city' => $city,
        ];

        try {
            $modelCustomers->insert($query);
            $customerId = (int) $modelCustomers->getInsertID();
        } catch (\Throwable $e) {
            return $fail('Error al insertar datos: ' . $e->getMessage(), 500);
        }

        if ($asJson) {
            return $this->response->setJSON([
                'error' => false,
                'code' => null,
                'data' => [
                    'customer_id' => $customerId,
                ],
                'message' => 'Cliente registrado exitosamente',
            ]);
        }

        return redirect()->to(base_url())->with('msg', ['type' => 'success', 'body' => 'Usuario registrado correctamente']);
    }

    public function dbRegister()
    {
        return $this->handleRegisterSubmission(false);

        $modelCustomers = new CustomersModel();

        $phone = $this->request->getVar('areaCode') . $this->request->getVar('phone');
        $name = $this->request->getVar('name');
        $lastName = $this->request->getVar('last_name');
        $dni = $this->request->getVar('dni');
        $city = $this->request->getVar('city');
        $this->ensureLocalityExists($city);

        $existingPhone = $this->findCustomerByPhoneVariants($phone);

        if ($phone === '' || $name === '' || $lastName === '' || $dni === '') {
            return redirect()->to('customers/register')->with('msg', ['type' => 'danger', 'body' => 'Debe completar todos los campos']);
        }

        if ($existingPhone) {
            return redirect()->to('customers/register')->with('msg', ['type' => 'danger', 'body' => 'El telÃ©fono coincide con un usuario ya registrado']);
        }

        $query = [
            'name' => $name,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone' => $phone,
            'offer' => 0,
            'city' => $city,
        ];

        try {
            $modelCustomers->insert($query);
        } catch (\Exception $e) {
            return 'Error al insertar datos: ' . $e->getMessage();
        }

        return redirect()->to(base_url())->with('msg', ['type' => 'success', 'body' => 'Usuario registrado correctamente']);
    }

    public function registerAjax()
    {
        return $this->handleRegisterSubmission(true);
    }

    public function createOffer()
    {
        return view('customers/createOffer');
    }

    public function delete($id)
    {
        $customersModel = new CustomersModel();

        try {
            $customersModel->delete($id);
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Cliente eliminado existosamente']);
        } catch (\Exception $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El cliente no se pudo eliminar']);
        }
    }

    public function editWindow($id)
    {
        $customersModel = new CustomersModel();
        $customer = $customersModel->find($id);
        $customer = $customer ? $this->enrichCustomerWithOffer($customer, $this->getCustomerOfferService()) : null;
        $embedded = filter_var($this->request->getGet('iframe'), FILTER_VALIDATE_BOOLEAN);

        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();

        return view('customers/editar', [
            'customer' => $customer,
            'fields' => $fieldsModel->getFields(),
            'services' => $servicesModel->getServices(),
            'customerOffer' => $customer['customer_offer'] ?? null,
            'embedded' => $embedded,
        ]);
    }

    public function edit()
    {
        $customersModel = new CustomersModel();
        $customerOffersModel = new CustomerOffersModel();
        $customerOfferFieldsModel = new CustomerOfferFieldsModel();
        $customerOfferServicesModel = new CustomerOfferServicesModel();
        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();
        $db = \Config\Database::connect();

        $id = (int) $this->request->getVar('idCustomer');
        $phone = trim((string) $this->request->getVar('phone'));
        $name = trim((string) $this->request->getVar('name'));
        $lastName = trim((string) $this->request->getVar('last_name'));
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $activeSwitch = $this->request->getVar('customer_offer_active') ? 1 : 0;
        $value = (float) parse_price_ar($this->request->getVar('customer_offer_value'));
        $description = trim((string) $this->request->getVar('customer_offer_description'));
        $expirationDate = trim((string) $this->request->getVar('customer_offer_expiration_date'));
        $applyAllFields = $this->request->getVar('customer_offer_apply_all_fields') ? 1 : 0;
        $applyAllServices = $this->request->getVar('customer_offer_apply_all_services') ? 1 : 0;
        $fieldIds = $this->decodeSelectionList($this->request->getVar('customer_offer_fields_json') ?? $this->request->getVar('customer_offer_fields'));
        $serviceCodes = $this->decodeSelectionList($this->request->getVar('customer_offer_services_json') ?? $this->request->getVar('customer_offer_services'));

        $this->ensureLocalityExists($city);

        if ($id <= 0 || $phone === '' || $name === '' || $lastName === '' || $dni === '') {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe completar todos los campos obligatorios']);
        }

        if ($value < 0 || $value > 100) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El porcentaje de descuento debe estar entre 0 y 100']);
        }

        if (! $this->isValidDate($expirationDate)) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'La fecha de vencimiento no es valida']);
        }

        $existingCustomer = $customersModel->find($id);
        if (! $existingCustomer) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El cliente no existe']);
        }

        $phoneConflict = $this->findCustomerByPhoneVariants($phone, $id);
        if ($phoneConflict) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El teléfono ya pertenece a otro cliente']);
        }

        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn ($fieldId) => $fieldId > 0)));
        $serviceCodes = array_values(array_unique(array_filter(array_map(static fn ($code) => strtolower(trim((string) $code)), $serviceCodes), static fn ($code) => $code !== '')));

        $validatedFieldIds = [];
        foreach ($fieldIds as $fieldId) {
            if (! $fieldsModel->getField($fieldId)) {
                return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Una de las canchas seleccionadas no existe']);
            }
            $validatedFieldIds[] = $fieldId;
        }

        $validatedServiceCodes = [];
        foreach ($serviceCodes as $serviceCode) {
            if (! $servicesModel->getByCode($serviceCode)) {
                return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Uno de los servicios seleccionados no existe']);
            }
            $validatedServiceCodes[] = $serviceCode;
        }

        $customerOfferActive = ($activeSwitch === 1 && $value > 0) ? 1 : 0;
        $hasOfferConfig = $customerOfferActive === 1
            || $value > 0
            || $description !== ''
            || $expirationDate !== ''
            || $applyAllFields === 1
            || $applyAllServices === 1
            || $validatedFieldIds !== []
            || $validatedServiceCodes !== [];

        if ($customerOfferActive === 1
            && $applyAllFields !== 1
            && $applyAllServices !== 1
            && $validatedFieldIds === []
            && $validatedServiceCodes === []
        ) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debes seleccionar al menos una cancha o un servicio, o marcar todas las canchas/servicios']);
        }

        $customerPayload = [
            'name' => $name,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone' => $phone,
            'offer' => $customerOfferActive,
            'city' => $city,
        ];

        try {
            $db->transBegin();

            $customersModel->update($id, $customerPayload);

            $existingOffer = $customerOffersModel->where('customer_id', $id)->first();
            $offerPayload = [
                'customer_id' => $id,
                'value' => $value,
                'description' => $description !== '' ? $description : null,
                'expiration_date' => $expirationDate !== '' ? $expirationDate : null,
                'active' => $customerOfferActive,
                'apply_all_fields' => $applyAllFields,
                'apply_all_services' => $applyAllServices,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existingOffer) {
                $customerOffersModel->update($existingOffer['id'], $offerPayload);
                $offerId = (int) $existingOffer['id'];
            } else {
                $offerPayload['created_at'] = date('Y-m-d H:i:s');
                $customerOffersModel->insert($offerPayload);
                $offerId = (int) $customerOffersModel->getInsertID();
            }

            if ($offerId > 0 && $hasOfferConfig) {
                $customerOfferFieldsModel->where('customer_offer_id', $offerId)->delete();
                $customerOfferServicesModel->where('customer_offer_id', $offerId)->delete();

                $timestamp = date('Y-m-d H:i:s');
                foreach ($validatedFieldIds as $fieldId) {
                    $customerOfferFieldsModel->insert([
                        'customer_offer_id' => $offerId,
                        'field_id' => $fieldId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }

                foreach ($validatedServiceCodes as $serviceCode) {
                    $customerOfferServicesModel->insert([
                        'customer_offer_id' => $offerId,
                        'service_code' => $serviceCode,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'No se pudo guardar la oferta personalizada']);
            }

            $db->transCommit();

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Cliente editado existosamente']);
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El cliente no se pudo editar: ' . $e->getMessage()]);
        }
    }

    public function editAjax()
    {
        $customersModel = new CustomersModel();
        $customerOffersModel = new CustomerOffersModel();
        $customerOfferFieldsModel = new CustomerOfferFieldsModel();
        $customerOfferServicesModel = new CustomerOfferServicesModel();
        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();
        $db = \Config\Database::connect();

        $id = (int) $this->request->getVar('idCustomer');
        $phone = trim((string) $this->request->getVar('phone'));
        $name = trim((string) $this->request->getVar('name'));
        $lastName = trim((string) $this->request->getVar('last_name'));
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $activeSwitch = $this->request->getVar('customer_offer_active') ? 1 : 0;
        $value = (float) parse_price_ar($this->request->getVar('customer_offer_value'));
        $description = trim((string) $this->request->getVar('customer_offer_description'));
        $expirationDate = trim((string) $this->request->getVar('customer_offer_expiration_date'));
        $applyAllFields = $this->request->getVar('customer_offer_apply_all_fields') ? 1 : 0;
        $applyAllServices = $this->request->getVar('customer_offer_apply_all_services') ? 1 : 0;
        $fieldIds = $this->decodeSelectionList($this->request->getVar('customer_offer_fields_json') ?? $this->request->getVar('customer_offer_fields'));
        $serviceCodes = $this->decodeSelectionList($this->request->getVar('customer_offer_services_json') ?? $this->request->getVar('customer_offer_services'));

        $fail = static function (string $message, int $status = 422) {
            return service('response')->setStatusCode($status)->setJSON([
                'error' => true,
                'code' => $status,
                'data' => null,
                'message' => $message,
            ]);
        };

        if ($id <= 0 || $phone === '' || $name === '' || $lastName === '' || $dni === '') {
            return $fail('Debe completar todos los campos obligatorios');
        }

        if ($value < 0 || $value > 100) {
            return $fail('El porcentaje de descuento debe estar entre 0 y 100');
        }

        if (! $this->isValidDate($expirationDate)) {
            return $fail('La fecha de vencimiento no es valida');
        }

        $existingCustomer = $customersModel->find($id);
        if (! $existingCustomer) {
            return $fail('El cliente no existe');
        }

        $phoneConflict = $this->findCustomerByPhoneVariants($phone, $id);
        if ($phoneConflict) {
            return $fail('El teléfono ya pertenece a otro cliente');
        }

        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn ($fieldId) => $fieldId > 0)));
        $serviceCodes = array_values(array_unique(array_filter(array_map(static fn ($code) => strtolower(trim((string) $code)), $serviceCodes), static fn ($code) => $code !== '')));

        $validatedFieldIds = [];
        foreach ($fieldIds as $fieldId) {
            if (! $fieldsModel->getField($fieldId)) {
                return $fail('Una de las canchas seleccionadas no existe');
            }
            $validatedFieldIds[] = $fieldId;
        }

        $validatedServiceCodes = [];
        foreach ($serviceCodes as $serviceCode) {
            if (! $servicesModel->getByCode($serviceCode)) {
                return $fail('Uno de los servicios seleccionados no existe');
            }
            $validatedServiceCodes[] = $serviceCode;
        }

        $customerOfferActive = ($activeSwitch === 1 && $value > 0) ? 1 : 0;
        $hasOfferConfig = $customerOfferActive === 1
            || $value > 0
            || $description !== ''
            || $expirationDate !== ''
            || $applyAllFields === 1
            || $applyAllServices === 1
            || $validatedFieldIds !== []
            || $validatedServiceCodes !== [];

        if ($customerOfferActive === 1
            && $applyAllFields !== 1
            && $applyAllServices !== 1
            && $validatedFieldIds === []
            && $validatedServiceCodes === []
        ) {
            return $fail('Debes seleccionar al menos una cancha o un servicio, o marcar todas las canchas/servicios');
        }

        $customerPayload = [
            'name' => $name,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone' => $phone,
            'offer' => $customerOfferActive,
            'city' => $city,
        ];

        try {
            $db->transBegin();

            $customersModel->update($id, $customerPayload);

            $existingOffer = $customerOffersModel->where('customer_id', $id)->first();
            $offerPayload = [
                'customer_id' => $id,
                'value' => $value,
                'description' => $description !== '' ? $description : null,
                'expiration_date' => $expirationDate !== '' ? $expirationDate : null,
                'active' => $customerOfferActive,
                'apply_all_fields' => $applyAllFields,
                'apply_all_services' => $applyAllServices,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existingOffer) {
                $customerOffersModel->update($existingOffer['id'], $offerPayload);
                $offerId = (int) $existingOffer['id'];
            } else {
                $offerPayload['created_at'] = date('Y-m-d H:i:s');
                $customerOffersModel->insert($offerPayload);
                $offerId = (int) $customerOffersModel->getInsertID();
            }

            if ($offerId > 0 && $hasOfferConfig) {
                $customerOfferFieldsModel->where('customer_offer_id', $offerId)->delete();
                $customerOfferServicesModel->where('customer_offer_id', $offerId)->delete();

                $timestamp = date('Y-m-d H:i:s');
                foreach ($validatedFieldIds as $fieldId) {
                    $customerOfferFieldsModel->insert([
                        'customer_offer_id' => $offerId,
                        'field_id' => $fieldId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }

                foreach ($validatedServiceCodes as $serviceCode) {
                    $customerOfferServicesModel->insert([
                        'customer_offer_id' => $offerId,
                        'service_code' => $serviceCode,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return $fail('No se pudo guardar la oferta personalizada', 500);
            }

            $db->transCommit();

            return $this->response->setJSON([
                'error' => false,
                'code' => null,
                'data' => [
                    'customer_id' => $id,
                ],
                'message' => 'Cliente editado exitosamente',
            ]);
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            return $fail('El cliente no se pudo editar: ' . $e->getMessage(), 500);
        }
    }

    public function getCustomer($phone)
    {
        $offerService = $this->getCustomerOfferService();
        $customer = $offerService->findCustomerByPhone((string) $phone);

        if ($customer) {
            $customer = $this->enrichCustomerWithOffer($customer, $offerService);
        }

        try {
            return $this->response->setJSON($this->setResponse(null, null, $customer, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getApplicableOffer()
    {
        $data = $this->request->getJSON(true);
        $phone = trim((string) ($data['phone'] ?? $this->request->getVar('phone') ?? ''));
        $fieldId = (int) ($data['field_id'] ?? $this->request->getVar('field_id') ?? 0);
        $amount = (float) parse_price_ar($data['amount'] ?? $data['base_amount'] ?? $this->request->getVar('amount') ?? $this->request->getVar('base_amount') ?? 0);
        $referenceDate = trim((string) ($data['date'] ?? $this->request->getVar('date') ?? ''));

        if ($phone === '' || $fieldId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'code' => 422,
                'data' => null,
                'message' => 'Debe informar telefono y cancha.',
            ]);
        }

        if ($amount <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'code' => 422,
                'data' => null,
                'message' => 'Debe informar un monto base valido.',
            ]);
        }

        $offerService = $this->getCustomerOfferService();
        $result = $offerService->getApplicableOfferByPhoneAndField($phone, $fieldId, $amount, $referenceDate !== '' ? $referenceDate : null);

        if (! empty($result['error'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'code' => 422,
                'data' => null,
                'message' => $result['message'] ?? 'No se pudo calcular la oferta.',
            ]);
        }

        return $this->response->setJSON($this->setResponse(null, false, $result, 'Respuesta exitosa'));
    }

    public function getCustomers()
    {
        $limitParam = $this->request->getGet('limit');
        $limit = is_numeric($limitParam) ? (int) $limitParam : 0;
        if ($limit > 0) {
            $limit = min($limit, 200);
            $customers = $this->getDistinctCustomers($limit);
        } else {
            $customers = $this->getDistinctCustomers();
        }

        try {
            return $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getCustomersWithOffer()
    {
        $customers = $this->getDistinctCustomers(null, 1);

        try {
            return $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function setOfferTrue()
    {
        $customersModel = new CustomersModel();

        try {
            $customersModel->builder()->set('offer', 1)->update();

            return $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function setOfferFalse()
    {
        $customersModel = new CustomersModel();

        try {
            $customersModel->builder()->set('offer', 0)->update();

            return $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function setResponse($code = 200, $error = false, $data = null, $message = '')
    {
        return [
            'error' => $error,
            'code' => $code,
            'data' => $data,
            'message' => $message,
        ];
    }
}
