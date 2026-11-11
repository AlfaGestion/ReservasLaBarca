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
        $normalizedPhone = $service->normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        $model = new CustomersModel();
        $canonicalPhone = ltrim($normalizedPhone, '0');

        foreach ($model->findAll() as $customer) {
            if ($ignoreId !== null && (int) ($customer['id'] ?? 0) === $ignoreId) {
                continue;
            }

            $candidatePhone = $service->normalizePhone($customer['phone'] ?? '');
            if ($candidatePhone !== '' && ltrim($candidatePhone, '0') === $canonicalPhone) {
                return $customer;
            }
        }

        return null;
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

    private function getDistinctCustomers(?int $limit = null, ?int $offer = null, int $offset = 0, string $search = ''): array
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

        $search = trim($search);
        if ($search !== '') {
            $builder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.last_name', $search)
                ->orLike('c.dni', $search)
                ->orLike('c.phone', $search)
                ->orLike('c.city', $search)
                ->orLike('c.quantity', $search)
                ->orLike('c.offer', $search)
                ->groupEnd();
        }

        if ($limit !== null && $limit > 0 && $offer === null) {
            if ($offset > 0) {
                $builder->offset($offset);
            }
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
            if ($limit !== null && $limit > 0) {
                $customers = array_slice($customers, $offset, $limit);
            }
        }

        return $customers;
    }

    private function countDistinctCustomers(string $search = '', ?int $offer = null): int
    {
        $db = \Config\Database::connect();
        $latestIdsSql = $db->table('customers')
            ->select('MAX(id) AS id')
            ->groupBy('phone')
            ->getCompiledSelect();

        $builder = $db->table('customers c')
            ->join("($latestIdsSql) latest", 'latest.id = c.id', 'inner');

        $search = trim($search);
        if ($search !== '') {
            $builder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.last_name', $search)
                ->orLike('c.dni', $search)
                ->orLike('c.phone', $search)
                ->orLike('c.city', $search)
                ->orLike('c.quantity', $search)
                ->orLike('c.offer', $search)
                ->groupEnd();
        }

        $customers = $builder->select('c.*')->get()->getResultArray();
        if ($offer === null) {
            return count($customers);
        }

        $offerService = $this->getCustomerOfferService();
        return count(array_filter($customers, function (array $customer) use ($offerService, $offer): bool {
            $enriched = $this->enrichCustomerWithOffer($customer, $offerService);
            return (int) ($enriched['offer'] ?? 0) === $offer;
        }));
    }

    public function register()
    {
        return view('customers/register_window', [
            'embedded' => false,
            'adminMode' => false,
            'suppressPublicRegisterModal' => true,
        ]);
    }

    public function registerWindow()
    {
        $embedded = filter_var($this->request->getGet('iframe'), FILTER_VALIDATE_BOOLEAN);

        return view('customers/register_window', [
            'embedded' => $embedded,
            'adminMode' => false,
            'suppressPublicRegisterModal' => true,
        ]);
    }

    public function adminRegisterWindow()
    {
        return $this->renderEditWindow(null, true, true);
    }

    private function handleRegisterSubmission(bool $asJson = false)
    {
        $modelCustomers = new CustomersModel();

        $areaCode = trim((string) $this->request->getVar('areaCode'));
        $phoneInput = trim((string) $this->request->getVar('phone'));
        $phone = $this->getCustomerOfferService()->normalizePhone($areaCode . $phoneInput);
        $name = trim((string) $this->request->getVar('name'));
        $lastName = trim((string) $this->request->getVar('last_name'));
        $fullName = trim((string) $this->request->getVar('full_name'));
        if ($fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $name = trim((string) ($nameParts[0] ?? ''));
            $lastName = trim((string) ($nameParts[1] ?? ''));
        }
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $email = trim((string) $this->request->getVar('email'));

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
            'email' => $email !== '' ? $email : null,
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
    }

    public function registerAjax()
    {
        return $this->handleRegisterSubmission(true);
    }

    public function adminRegisterAjax()
    {
        $modelCustomers = new CustomersModel();
        $areaCode = trim((string) $this->request->getVar('areaCode'));
        $phoneInput = trim((string) $this->request->getVar('phone'));
        $phone = $this->getCustomerOfferService()->normalizePhone($areaCode . $phoneInput);
        $name = trim((string) $this->request->getVar('name'));
        $lastName = trim((string) $this->request->getVar('last_name'));
        $fullName = trim((string) $this->request->getVar('full_name'));
        if ($fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $name = trim((string) ($nameParts[0] ?? ''));
            $lastName = trim((string) ($nameParts[1] ?? ''));
        }
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $email = trim((string) $this->request->getVar('email'));

        if ($phone === '' || $name === '' || $lastName === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'code' => 422,
                'data' => null,
                'message' => 'Debe completar todos los campos obligatorios',
            ]);
        }

        $this->ensureLocalityExists($city);

        if ($this->findCustomerByPhoneVariants($phone)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'code' => 422,
                'data' => null,
                'message' => 'El teléfono coincide con un cliente ya registrado',
            ]);
        }

        try {
            $modelCustomers->insert([
                'name' => $name,
                'last_name' => $lastName,
                'dni' => $dni,
                'phone' => $phone,
                'offer' => 0,
                'city' => $city,
                'email' => $email !== '' ? $email : null,
            ]);
            $customerId = (int) $modelCustomers->getInsertID();

            // Reutiliza toda la validación y persistencia de ofertas de la edición.
            $postData = $this->request->getPost();
            $postData['idCustomer'] = $customerId;
            $postData['phone'] = $phone;
            $this->request->setGlobal('post', $postData);

            $result = $this->editAjax();
            if ($result->getStatusCode() >= 400) {
                $modelCustomers->delete($customerId);
            }
            return $result;
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'code' => 500,
                'data' => null,
                'message' => 'No se pudo guardar el cliente',
            ]);
        }
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
        return $this->renderEditWindow($id, false);
    }

    public function adminEditWindow($id)
    {
        return $this->renderEditWindow($id, true);
    }

    private function renderEditWindow($id, bool $adminMode = false, bool $newCustomer = false)
    {
        $customersModel = new CustomersModel();
        $customer = $customersModel->find($id);
        $customer = $customer ? $this->enrichCustomerWithOffer($customer, $this->getCustomerOfferService()) : null;
        $customer = $customer ?: [];
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
            'adminMode' => $adminMode,
            'newCustomer' => $newCustomer,
            'suppressPublicRegisterModal' => true,
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
        $fullName = trim((string) $this->request->getVar('full_name'));
        if ($fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $name = trim((string) ($nameParts[0] ?? ''));
            $lastName = trim((string) ($nameParts[1] ?? ''));
        }
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $email = trim((string) $this->request->getVar('email'));
        $activeSwitch = $this->request->getVar('customer_offer_active') ? 1 : 0;
        $value = (float) parse_price_ar($this->request->getVar('customer_offer_value'));
        $description = trim((string) $this->request->getVar('customer_offer_description'));
        $expirationDate = trim((string) $this->request->getVar('customer_offer_expiration_date'));
        $applyAllFields = $this->request->getVar('customer_offer_apply_all_fields') ? 1 : 0;
        $applyAllServices = $this->request->getVar('customer_offer_apply_all_services') ? 1 : 0;
        $fieldIds = $this->decodeSelectionList($this->request->getVar('customer_offer_fields_json') ?? $this->request->getVar('customer_offer_fields'));
        $serviceCodes = $this->decodeSelectionList($this->request->getVar('customer_offer_services_json') ?? $this->request->getVar('customer_offer_services'));

        $this->ensureLocalityExists($city);

        if ($id <= 0 || $phone === '' || $name === '' || $lastName === '') {
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

        $currentPhone = $this->getCustomerOfferService()->normalizePhone($existingCustomer['phone'] ?? '');
        $phoneConflict = $currentPhone !== '' && ltrim($currentPhone, '0') === ltrim($phone, '0')
            ? null
            : $this->findCustomerByPhoneVariants($phone, $id);
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
            'email' => $email !== '' ? $email : null,
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

    public function adminEditAjax($id = null)
    {
        if ((int) $this->request->getVar('idCustomer') <= 0 && (int) $id > 0) {
            $postData = $this->request->getPost();
            $postData['idCustomer'] = (int) $id;
            $this->request->setGlobal('post', $postData);
        }

        return $this->editAjax();
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
        $fullName = trim((string) $this->request->getVar('full_name'));
        if ($fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $name = trim((string) ($nameParts[0] ?? ''));
            $lastName = trim((string) ($nameParts[1] ?? ''));
        }
        $dni = trim((string) $this->request->getVar('dni'));
        $city = trim((string) $this->request->getVar('city'));
        $email = trim((string) $this->request->getVar('email'));
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

        if ($id <= 0 || $phone === '' || $name === '' || $lastName === '') {
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

        $currentPhone = $this->getCustomerOfferService()->normalizePhone($existingCustomer['phone'] ?? '');
        $phoneConflict = $currentPhone !== '' && ltrim($currentPhone, '0') === ltrim($phone, '0')
            ? null
            : $this->findCustomerByPhoneVariants($phone, $id);
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
            'email' => $email !== '' ? $email : null,
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
                'message' => 'Cliente guardado exitosamente',
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
        $search = trim((string) $this->request->getGet('search'));
        $export = filter_var($this->request->getGet('export'), FILTER_VALIDATE_BOOLEAN);
        $offer = $this->request->getGet('offer') === '1' ? 1 : null;
        $perPage = $export ? 10000 : max(1, min(100, (int) ($this->request->getGet('per_page') ?: 25)));
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $offset = $export ? 0 : ($page - 1) * $perPage;
        $total = $this->countDistinctCustomers($search, $offer);
        $customers = $this->getDistinctCustomers($perPage, $offer, $offset, $search);

        if ($export) {
            return $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        }

        try {
            return $this->response->setJSON([
                'error' => false,
                'code' => null,
                'data' => $customers,
                'message' => 'Respuesta exitosa',
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $perPage)),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getCustomersWithOffer()
    {
        $export = filter_var($this->request->getGet('export'), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = $export ? 10000 : max(1, min(100, (int) ($this->request->getGet('per_page') ?: 25)));
        $search = trim((string) $this->request->getGet('search'));
        $total = $this->countDistinctCustomers($search, 1);
        $customers = $this->getDistinctCustomers($perPage, 1, $export ? 0 : ($page - 1) * $perPage, $search);

        if ($export) {
            return $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        }

        try {
            return $this->response->setJSON([
                'error' => false,
                'code' => null,
                'data' => $customers,
                'message' => 'Respuesta exitosa',
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $perPage)),
                ],
            ]);
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
