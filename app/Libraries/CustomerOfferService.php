<?php

namespace App\Libraries;

use App\Models\BookingSlotsModel;
use App\Models\CustomerOfferFieldsModel;
use App\Models\CustomerOfferServicesModel;
use App\Models\CustomerOffersModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\ServicesModel;
use App\Models\TimeModel;

class CustomerOfferService
{
    private CustomersModel $customersModel;
    private CustomerOffersModel $customerOffersModel;
    private CustomerOfferFieldsModel $customerOfferFieldsModel;
    private CustomerOfferServicesModel $customerOfferServicesModel;
    private FieldsModel $fieldsModel;
    private ServicesModel $servicesModel;
    private BookingSlotsModel $bookingSlotsModel;
    private TimeModel $timeModel;

    public function __construct()
    {
        $this->customersModel = new CustomersModel();
        $this->customerOffersModel = new CustomerOffersModel();
        $this->customerOfferFieldsModel = new CustomerOfferFieldsModel();
        $this->customerOfferServicesModel = new CustomerOfferServicesModel();
        $this->fieldsModel = new FieldsModel();
        $this->servicesModel = new ServicesModel();
        $this->bookingSlotsModel = new BookingSlotsModel();
        $this->timeModel = new TimeModel();
    }

    public function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', trim((string) $phone)) ?? '';
    }

    private function buildPhoneVariants(string $phone): array
    {
        $digits = $this->normalizePhone($phone);
        $base = $digits !== '' ? $digits : trim($phone);

        $variants = [$base];
        if ($base !== '') {
            $withoutLeadingZero = ltrim($base, '0');
            if ($withoutLeadingZero !== '') {
                $variants[] = $withoutLeadingZero;
                $variants[] = '0' . $withoutLeadingZero;
            }
        }

        return array_values(array_unique(array_filter($variants, static fn ($value) => $value !== null && $value !== '')));
    }

    public function findCustomerByPhone(?string $phone): ?array
    {
        $variants = $this->buildPhoneVariants((string) $phone);
        if ($variants === []) {
            return null;
        }

        $builder = $this->customersModel;
        $first = true;
        foreach ($variants as $variant) {
            if ($first) {
                $builder = $builder->where('phone', $variant);
                $first = false;
                continue;
            }

            $builder = $builder->orWhere('phone', $variant);
        }

        return $builder->first();
    }

    private function fetchOfferFields(int $offerId): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('customer_offer_fields')) {
            return [];
        }

        return $this->customerOfferFieldsModel->select('customer_offer_fields.field_id, fields.name AS field_name, fields.service_type')
            ->join('fields', 'fields.id = customer_offer_fields.field_id', 'left')
            ->where('customer_offer_id', $offerId)
            ->findAll();
    }

    private function fetchOfferServices(int $offerId): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('customer_offer_services')) {
            return [];
        }

        return $this->customerOfferServicesModel->select('customer_offer_services.service_code, services.name AS service_name')
            ->join('services', 'services.code = customer_offer_services.service_code', 'left')
            ->where('customer_offer_id', $offerId)
            ->findAll();
    }

    public function getCustomerOffer(?int $customerId): ?array
    {
        $db = \Config\Database::connect();
        if ($customerId === null || $customerId <= 0 || ! $db->tableExists('customer_offers')) {
            return null;
        }

        $offer = $this->customerOffersModel->where('customer_id', $customerId)->first();
        if (! $offer) {
            return null;
        }

        $offer['value'] = (float) ($offer['value'] ?? 0);
        $offer['active'] = (int) ($offer['active'] ?? 0);
        $offer['apply_all_fields'] = (int) ($offer['apply_all_fields'] ?? 0);
        $offer['apply_all_services'] = (int) ($offer['apply_all_services'] ?? 0);
        $offer['fields'] = $this->fetchOfferFields((int) $offer['id']);
        $offer['services'] = $this->fetchOfferServices((int) $offer['id']);
        $offer['field_count'] = count($offer['fields']);
        $offer['service_count'] = count($offer['services']);
        $offer['scope_label'] = $this->buildScopeLabel($offer);

        return $offer;
    }

    public function getCustomerOfferSummary(?int $customerId): ?array
    {
        $offer = $this->getCustomerOffer($customerId);
        if (! $offer) {
            return null;
        }

        return [
            'customer_offer_id' => (int) $offer['id'],
            'customer_id' => (int) ($offer['customer_id'] ?? 0),
            'active' => (int) ($offer['active'] ?? 0),
            'value' => (float) ($offer['value'] ?? 0),
            'description' => (string) ($offer['description'] ?? ''),
            'expiration_date' => $offer['expiration_date'] ?? null,
            'apply_all_fields' => (int) ($offer['apply_all_fields'] ?? 0),
            'apply_all_services' => (int) ($offer['apply_all_services'] ?? 0),
            'fields' => $offer['fields'],
            'services' => $offer['services'],
            'field_count' => (int) ($offer['field_count'] ?? 0),
            'service_count' => (int) ($offer['service_count'] ?? 0),
            'scope_label' => (string) ($offer['scope_label'] ?? ''),
        ];
    }

    private function isOfferExpired(array $offer, ?string $referenceDate = null): bool
    {
        $expirationDate = trim((string) ($offer['expiration_date'] ?? ''));
        if ($expirationDate === '') {
            return false;
        }

        $compareDate = $referenceDate ? substr(trim($referenceDate), 0, 10) : date('Y-m-d');
        return $expirationDate < $compareDate;
    }

    private function matchesField(array $offer, array $field): bool
    {
        if ((int) ($offer['apply_all_fields'] ?? 0) === 1) {
            return true;
        }

        $fieldId = (int) ($field['id'] ?? 0);
        if ($fieldId <= 0) {
            return false;
        }

        foreach ($offer['fields'] ?? [] as $offerField) {
            if ((int) ($offerField['field_id'] ?? 0) === $fieldId) {
                return true;
            }
        }

        return false;
    }

    private function matchesService(array $offer, array $field): bool
    {
        if ((int) ($offer['apply_all_services'] ?? 0) === 1) {
            return true;
        }

        $serviceCode = (string) ($field['service_type'] ?? '');
        if ($serviceCode === '') {
            return false;
        }

        foreach ($offer['services'] ?? [] as $offerService) {
            if ((string) ($offerService['service_code'] ?? '') === $serviceCode) {
                return true;
            }
        }

        return false;
    }

    private function buildScopeLabel(array $offer): string
    {
        $fields = [];
        foreach ($offer['fields'] ?? [] as $row) {
            $label = trim((string) ($row['field_name'] ?? ''));
            if ($label === '' && ! empty($row['field_id'])) {
                $label = 'Cancha #' . $row['field_id'];
            }
            if ($label !== '') {
                $fields[] = $label;
            }
        }

        $services = [];
        foreach ($offer['services'] ?? [] as $row) {
            $label = trim((string) ($row['service_name'] ?? ''));
            if ($label === '' && ! empty($row['service_code'])) {
                $label = ucfirst((string) $row['service_code']);
            }
            if ($label !== '') {
                $services[] = $label;
            }
        }

        $parts = [];
        if ((int) ($offer['apply_all_fields'] ?? 0) === 1) {
            $parts[] = 'Todas las canchas';
        } elseif ($fields !== []) {
            $parts[] = implode(', ', $fields);
        }

        if ((int) ($offer['apply_all_services'] ?? 0) === 1) {
            $parts[] = 'Todos los servicios';
        } elseif ($services !== []) {
            $parts[] = implode(', ', $services);
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Sin alcance';
    }

    private function getNocturnalHours(): array
    {
        $openingTime = $this->timeModel->getOpeningTime();
        $timeRow = $this->timeModel->first();
        if (! $timeRow || empty($openingTime) || empty($timeRow['nocturnal_time'])) {
            return [];
        }

        $index = array_search($timeRow['nocturnal_time'], $openingTime, true);
        if ($index === false) {
            return [];
        }

        return array_slice($openingTime, (int) $index);
    }

    private function isNocturnalSlot(string $from, string $until): bool
    {
        $nocturnalHours = $this->getNocturnalHours();
        if ($nocturnalHours === []) {
            return false;
        }

        $fromHour = substr($this->bookingSlotsModel->normalizeTime($from), 0, 2);
        $untilHour = substr($this->bookingSlotsModel->normalizeTime($until), 0, 2);

        return in_array($fromHour, $nocturnalHours, true) && in_array($untilHour, $nocturnalHours, true);
    }

    public function calculateBaseAmountForItem(array $item): array
    {
        $fieldId = (int) ($item['cancha'] ?? $item['field_id'] ?? 0);
        $field = $this->fieldsModel->getField($fieldId);
        if (! $field) {
            return [
                'error' => true,
                'message' => 'El servicio seleccionado no existe.',
                'field_id' => $fieldId,
            ];
        }

        $from = (string) ($item['horarioDesde'] ?? '');
        $until = (string) ($item['horarioHasta'] ?? '');
        $normalizedFrom = $this->bookingSlotsModel->normalizeTime($from);
        $normalizedUntil = $this->bookingSlotsModel->normalizeTime($until);
        $fromMinutes = $this->bookingSlotsModel->timeToMinutes($normalizedFrom);
        $untilMinutes = $this->bookingSlotsModel->timeToMinutes($normalizedUntil);

        if ($normalizedFrom === '' || $normalizedUntil === '' || $untilMinutes <= 0 || $fromMinutes < 0) {
            return [
                'error' => true,
                'message' => 'Faltan datos de horario.',
                'field_id' => $fieldId,
            ];
        }

        if ($untilMinutes <= $fromMinutes) {
            $untilMinutes += 24 * 60;
        }

        $durationMinutes = $untilMinutes - $fromMinutes;
        $blockMinutes = (int) ($field['duration_minutes'] ?? $field['block_minutes'] ?? $field['slot_interval_minutes'] ?? $field['booking_interval_minutes'] ?? 60);
        $units = $durationMinutes / max(1, $blockMinutes);
        $basePrice = (float) ($field['value'] ?? 0);
        $nightPrice = (float) ($field['ilumination_value'] ?? 0);
        $perBlockAmount = $this->isNocturnalSlot($normalizedFrom, $normalizedUntil) && $nightPrice > 0
            ? $nightPrice
            : $basePrice;

        $baseAmount = round($units * $perBlockAmount, 2);

        return [
            'error' => false,
            'field' => $field,
            'field_id' => $fieldId,
            'base_amount' => $baseAmount,
            'per_block_amount' => round($perBlockAmount, 2),
            'duration_minutes' => $durationMinutes,
            'block_minutes' => $blockMinutes,
            'is_nocturnal' => $this->isNocturnalSlot($normalizedFrom, $normalizedUntil),
        ];
    }

    public function getApplicableOfferQuote(?array $customer, array $field, float $baseAmount, ?string $referenceDate = null): array
    {
        $baseAmount = round(max(0, $baseAmount), 2);
        $result = [
            'applicable' => false,
            'customer_id' => $customer['id'] ?? null,
            'customer_offer_id' => null,
            'offer_id' => null,
            'value' => 0,
            'description' => '',
            'expiration_date' => null,
            'apply_all_fields' => false,
            'apply_all_services' => false,
            'field_match' => false,
            'service_match' => false,
            'original_amount' => $baseAmount,
            'discount_amount' => 0,
            'final_amount' => $baseAmount,
            'field_id' => $field['id'] ?? null,
            'field_name' => $field['name'] ?? null,
            'service_code' => $field['service_type'] ?? null,
            'service_name' => $field['service_name'] ?? null,
            'scope_label' => 'Sin oferta',
        ];

        if (! $customer) {
            return $result;
        }

        $offer = $this->getCustomerOffer((int) ($customer['id'] ?? 0));
        if (! $offer || (int) ($offer['active'] ?? 0) !== 1) {
            return $result;
        }

        if ($this->isOfferExpired($offer, $referenceDate)) {
            $result['scope_label'] = 'Oferta vencida';
            return $result;
        }

        $value = (float) ($offer['value'] ?? 0);
        if ($value <= 0) {
            $result['scope_label'] = 'Oferta sin porcentaje';
            return $result;
        }

        $fieldMatch = $this->matchesField($offer, $field);
        $serviceMatch = $this->matchesService($offer, $field);
        $applicable = $fieldMatch || $serviceMatch;
        if (! $applicable) {
            $result['scope_label'] = $offer['scope_label'] ?? 'Sin oferta';
            return $result;
        }

        $discountAmount = round($baseAmount * min(100, max(0, $value)) / 100, 2);
        $finalAmount = round(max(0, $baseAmount - $discountAmount), 2);

        $result['applicable'] = true;
        $result['customer_offer_id'] = (int) ($offer['id'] ?? 0);
        $result['offer_id'] = (int) ($offer['id'] ?? 0);
        $result['value'] = $value;
        $result['description'] = (string) ($offer['description'] ?? '');
        $result['expiration_date'] = $offer['expiration_date'] ?? null;
        $result['apply_all_fields'] = (int) ($offer['apply_all_fields'] ?? 0) === 1;
        $result['apply_all_services'] = (int) ($offer['apply_all_services'] ?? 0) === 1;
        $result['field_match'] = $fieldMatch;
        $result['service_match'] = $serviceMatch;
        $result['discount_amount'] = $discountAmount;
        $result['final_amount'] = $finalAmount;
        $result['scope_label'] = $offer['scope_label'] ?? 'Oferta activa';

        return $result;
    }

    public function getApplicableOfferByPhoneAndField(string $phone, $fieldId, float $baseAmount, ?string $referenceDate = null): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if (! $customer) {
            return [
                'applicable' => false,
                'customer_id' => null,
            ];
        }

        $field = $this->fieldsModel->getField((int) $fieldId);
        if (! $field) {
            return [
                'error' => true,
                'message' => 'El servicio seleccionado no existe.',
                'applicable' => false,
                'customer_id' => (int) $customer['id'],
            ];
        }

        return $this->getApplicableOfferQuote($customer, $field, $baseAmount, $referenceDate);
    }

    public function calculateBookingQuote(array $items, ?string $phone = null, ?string $referenceDate = null): array
    {
        $customer = $phone ? $this->findCustomerByPhone($phone) : null;
        $quoteItems = [];
        $originalTotal = 0.0;
        $discountTotal = 0.0;
        $finalTotal = 0.0;
        $offerId = null;
        $customerOffer = null;

        foreach ($items as $index => $item) {
            $baseResult = $this->calculateBaseAmountForItem($item);
            if (! empty($baseResult['error'])) {
                return $baseResult;
            }

            $field = $baseResult['field'];
            $baseAmount = (float) $baseResult['base_amount'];
            $offerQuote = $this->getApplicableOfferQuote($customer, $field, $baseAmount, $referenceDate);

            if ($offerQuote['applicable'] && $offerId === null) {
                $offerId = $offerQuote['offer_id'];
                $customerOffer = $offerQuote;
            }

            $quoteItems[] = [
                'index' => $index,
                'item' => $item,
                'field' => $field,
                'base' => $baseResult,
                'offer' => $offerQuote,
                'original_amount' => $baseAmount,
                'discount_amount' => (float) ($offerQuote['discount_amount'] ?? 0),
                'final_amount' => (float) ($offerQuote['final_amount'] ?? $baseAmount),
            ];

            $originalTotal += $baseAmount;
            $discountTotal += (float) ($offerQuote['discount_amount'] ?? 0);
            $finalTotal += (float) ($offerQuote['final_amount'] ?? $baseAmount);
        }

        return [
            'error' => false,
            'customer' => $customer,
            'customer_offer' => $customerOffer,
            'customer_offer_id' => $offerId,
            'items' => $quoteItems,
            'original_total' => round($originalTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'final_total' => round($finalTotal, 2),
        ];
    }
}
