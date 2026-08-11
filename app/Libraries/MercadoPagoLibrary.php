<?php

namespace App\Libraries;

use App\Models\MercadoPagoKeysModel;
use MercadoPago\Item;
use MercadoPago\Preference;
use MercadoPago\SDK;

class MercadoPagoLibrary
{
    public $preferenceId = null;

    public function setPreference(string $bookingTitle, float $bookingAmount, int $quantity, array $options = []): void
    {
        $mpKeysModel = new MercadoPagoKeysModel();
        $mpKeys = $mpKeysModel->first();

        if (empty($mpKeys) || empty($mpKeys['access_token'])) {
            throw new \Exception('Mercado Pago Access Token no encontrado.');
        }

        $this->ensureCaBundle();
        SDK::setAccessToken($mpKeys['access_token']);

        try {
            if ($bookingAmount <= 0) {
                throw new \Exception('El monto de la reserva debe ser un valor positivo.');
            }

            $preference = new Preference();

            $item = new Item();
            $item->title = $bookingTitle;
            $item->quantity = $quantity;
            $item->unit_price = $bookingAmount;
            $item->currency_id = 'ARS';

            $baseUrl = $this->resolveBaseUrl();

            $preference->items = [$item];
            $preference->back_urls = [
                'success' => $baseUrl . 'payment/success',
                'failure' => $baseUrl . 'payment/failure',
            ];
            $preference->notification_url = $baseUrl . 'payment/webhook';
            if (!empty($options['external_reference'])) {
                $preference->external_reference = (string) $options['external_reference'];
            }

            $preference->auto_return = 'approved';
            $preference->binary_mode = true;
            $preference->save();

            if (empty($preference->id)) {
                log_message(
                    'error',
                    'FALLO MP: Preference ID NULL. base_url=' . $baseUrl . ' token_prefix=' . substr((string) $mpKeys['access_token'], 0, 10) . ' preference=' . print_r($preference, true)
                );

                throw new \Exception('La API de Mercado Pago devolvio un error (revisa los logs de PHP para ver la respuesta de validacion).');
            }

            $this->preferenceId = $preference->id;
        } catch (\Exception $e) {
            log_message('error', 'MP setPreference exception: ' . $e->getMessage());
            throw new \Exception('Error al crear la preferencia de pago: ' . $e->getMessage());
        }
    }

    private function resolveBaseUrl(): string
    {
        $envBaseUrl = getenv('MP_BACK_URL_BASE');
        $appConfig = config('App');
        $baseUrl = rtrim($envBaseUrl ?: $appConfig->baseURL, '/') . '/';
        $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        if ($baseHost === 'localhost' || $baseHost === '127.0.0.1') {
            throw new \Exception('Configuracion invalida de MP_BACK_URL_BASE: no puede apuntar a localhost para Mercado Pago.');
        }

        return $baseUrl;
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
