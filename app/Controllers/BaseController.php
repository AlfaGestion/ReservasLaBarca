<?php

namespace App\Controllers;

use App\Models\BookingSlotsModel;
use App\Models\LocalitiesModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    protected $session;

    function __construct()
    {
        $this->session = \Config\Services::session();
        $this->session->start();
    }
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
    }

    protected function ensureLocalityExists(?string $locality): void
    {
        if (!is_string($locality)) {
            return;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $locality));
        if ($normalized === '') {
            return;
        }

        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);

        $localitiesModel = new LocalitiesModel();
        $existing = $localitiesModel->where('LOWER(name)', $lower)->first();
        if (!$existing) {
            $localitiesModel->insert(['name' => $normalized]);
        }
    }

    protected function expireActiveBookingSlots(BookingSlotsModel $bookingSlotsModel, array $where = [], array $whereIn = []): void
    {
        $query = $bookingSlotsModel->select('id, date, id_field, time_from, time_until')
            ->where('active', 1);

        foreach ($where as $field => $value) {
            $query->where($field, $value);
        }

        foreach ($whereIn as $field => $values) {
            if (!empty($values)) {
                $query->whereIn($field, $values);
            }
        }

        $slots = $query->findAll();
        if (empty($slots)) {
            return;
        }

        foreach ($slots as $slot) {
            $hasInactiveDuplicate = $bookingSlotsModel
                ->where('active', 0)
                ->where('date', $slot['date'])
                ->where('id_field', $slot['id_field'])
                ->where('time_from', $slot['time_from'])
                ->where('time_until', $slot['time_until'])
                ->first();

            if ($hasInactiveDuplicate) {
                $bookingSlotsModel->delete($slot['id']);
                continue;
            }

            $bookingSlotsModel->update($slot['id'], ['active' => 0, 'status' => 'expired']);
        }
    }
}
