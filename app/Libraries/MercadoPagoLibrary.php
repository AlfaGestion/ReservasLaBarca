<?php

namespace App\Libraries;

use App\Models\MercadoPagoKeysModel;
// Quitamos la importación de MPApiException para evitar el error P1009
use MercadoPago\SDK; 
use MercadoPago\Preference;
use MercadoPago\Item;
// use MercadoPago\Exceptions\MPApiException; // <--- COMENTADO/ELIMINADO

class MercadoPagoLibrary
{
    public $preferenceId = null;

    function setPreference(string $bookingTitle, float $bookingAmount, int $quantity)
    {
        $mpKeysModel = new MercadoPagoKeysModel();
        $mpKeys = $mpKeysModel->first();

        if (empty($mpKeys) || empty($mpKeys['access_token'])) {
            throw new \Exception("Mercado Pago Access Token no encontrado.");
        }

        $this->ensureCaBundle();
        SDK::setAccessToken($mpKeys['access_token']);

        try {
            // 1. Validar que el monto no sea cero o negativo (Causa común de fallo silencioso)
            if ($bookingAmount <= 0) {
                 throw new \Exception("El monto de la reserva debe ser un valor positivo.");
            }

            $preference = new Preference();

            $item = new Item();
            $item->title = $bookingTitle;
            $item->quantity = $quantity;
            $item->unit_price = $bookingAmount;
            $item->currency_id = 'ARS';

            $preference->items = [$item];
            $envBaseUrl = getenv('MP_BACK_URL_BASE');
            $appConfig = config('App');
            $baseUrl = rtrim($envBaseUrl ?: $appConfig->baseURL, '/') . '/';
            $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: '';
            if ($baseHost === 'localhost' || $baseHost === '127.0.0.1') {
                throw new \Exception('Configuracion invalida de MP_BACK_URL_BASE: no puede apuntar a localhost para Mercado Pago.');
            }
            $preference->back_urls = [
            "success" => $baseUrl . 'payment/success',
            "failure" => $baseUrl . 'payment/failure',
            ];

            $preference->auto_return = "approved";
            $preference->binary_mode = true;
            
            // Llamada a la API
            $preference->save();

            // 🚨 DIAGNÓSTICO CLAVE: Revisar si el ID es nulo
            if (empty($preference->id)) {
                
                // Muestra el objeto completo en el log de PHP para ver la respuesta de error de la API
                log_message('error', 'FALLO MP: Preference ID NULL. base_url=' . $baseUrl . ' token_prefix=' . substr((string)$mpKeys['access_token'], 0, 10) . ' preference=' . print_r($preference, true));

                // Lanza una excepción con un mensaje genérico, pero el detalle está en el log.
                throw new \Exception("La API de Mercado Pago devolvió un error (revisa los logs de PHP para ver la respuesta de validación).");
            }

            $this->preferenceId = $preference->id;
            
        } catch (\Exception $e) {
            log_message('error', 'MP setPreference exception: ' . $e->getMessage());
            throw new \Exception("Error al crear la preferencia de pago: " . $e->getMessage());
        }
    }

    private function ensureCaBundle()
    {
        $caFile = ini_get('curl.cainfo');
        if (!$caFile) {
            $caFile = ini_get('openssl.cafile');
        }
        if (!$caFile) {
            $candidate = 'C:\\php\\cacert.pem';
            if (is_file($candidate)) {
                $caFile = $candidate;
            }
        }

        if ($caFile && is_file($caFile)) {
            ini_set('curl.cainfo', $caFile);
            ini_set('openssl.cafile', $caFile);
            putenv("CURL_CA_BUNDLE={$caFile}");
            putenv("SSL_CERT_FILE={$caFile}");
        }
    }
}

