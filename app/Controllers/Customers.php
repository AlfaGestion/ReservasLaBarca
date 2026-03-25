<?php

namespace App\Controllers;

use App\Models\CustomersModel;

class Customers extends BaseController
{
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

        if ($offer !== null) {
            $builder->where('c.offer', $offer);
        }

        if ($limit !== null && $limit > 0) {
            $builder->limit($limit);
        }

        return $builder->get()->getResultArray();
    }

    public function register()
    {
        return view('customers/register');
    }

    public function dbRegister()
    {
        $modelCustomers = new CustomersModel();

        $phone = $this->request->getVar('areaCode') . $this->request->getVar('phone');
        $name = $this->request->getVar('name');
        $lastName = $this->request->getVar('last_name');
        $dni = $this->request->getVar('dni');
        $city = $this->request->getVar('city');
        $this->ensureLocalityExists($city);


        $existingPhone = $modelCustomers->where('phone', $phone)->findAll();

        if ($phone == '' || $name == '' || $lastName == '' || $dni == '') {
            return redirect()->to('customers/register')->with('msg', ['type' => 'danger', 'body' => 'Debe completar todos los campos']);
        }

        if ($existingPhone) {
            return redirect()->to('customers/register')->with('msg', ['type' => 'danger', 'body' => 'El teléfono coincide con un usuario ya registrado']);
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
            return "Error al insertar datos: " . $e->getMessage();
        }

        return redirect()->to(base_url())->with('msg', ['type' => 'success', 'body' => 'Usuario registrado correctamente']);
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

        return view('customers/editar', ['customer' => $customer]);
    }

    public function edit()
    {
        $customersModel = new CustomersModel();

        $id = $this->request->getVar('idCustomer');
        $phone = $this->request->getVar('phone');
        $name = $this->request->getVar('name');
        $lastName = $this->request->getVar('last_name');
        $dni = $this->request->getVar('dni');
        $offer = $this->request->getVar('offer');
        $city = $this->request->getVar('city');
        $this->ensureLocalityExists($city);

        $query = [
            'name' => $name,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone' => $phone,
            'offer' => $offer,
            'city' => $city
        ];

        try {
            $customersModel->update($id, $query);
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Cliente editado existosamente']);
        } catch (\Exception $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El cliente no se pudo editar']);
        }
    }

    public function getCustomer($phone)
    {
        $customersModel = new CustomersModel();
        $rawPhone = trim((string)$phone);
        $digits = preg_replace('/\D+/', '', $rawPhone);
        $base = $digits !== '' ? $digits : $rawPhone;

        $variants = [$base];
        if ($base !== '') {
            $withoutLeadingZero = ltrim($base, '0');
            if ($withoutLeadingZero !== '') {
                $variants[] = $withoutLeadingZero;
                $variants[] = '0' . $withoutLeadingZero;
            }
        }
        $variants = array_values(array_unique(array_filter($variants, fn($v) => $v !== null && $v !== '')));

        $query = $customersModel;
        $first = true;
        foreach ($variants as $variant) {
            if ($first) {
                $query = $query->where('phone', $variant);
                $first = false;
            } else {
                $query = $query->orWhere('phone', $variant);
            }
        }
        $customer = $query->first();

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $customer, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getCustomers()
    {
        $limitParam = $this->request->getGet('limit');
        $limit = is_numeric($limitParam) ? (int)$limitParam : 0;
        if ($limit > 0) {
            $limit = min($limit, 200);
            $customers = $this->getDistinctCustomers($limit);
        } else {
            $customers = $this->getDistinctCustomers();
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getCustomersWithOffer()
    {
        $customers = $this->getDistinctCustomers(null, 1);

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $customers, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }
    
    public function setOfferTrue(){
        $customersModel = new CustomersModel();
        
        try {
            // Aplicar oferta a todos los clientes sin depender del estado previo.
            $customersModel->builder()->set('offer', 1)->update();

            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }


    public function setOfferFalse(){
        $customersModel = new CustomersModel();
        
        try {
            // Quitar oferta a todos los clientes sin depender del estado previo.
            $customersModel->builder()->set('offer', 0)->update();

            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
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
