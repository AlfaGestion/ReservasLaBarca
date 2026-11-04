<?php

namespace App\Libraries;

use App\Models\PlayerAccountDiscountsModel;

class PlayerDiscountService
{
    public function applyToQuote(array $quote, int $playerId, ?string $date = null): array
    {
        if ($playerId <= 0 || ! \Config\Database::connect()->tableExists('player_account_discounts')) return $quote;
        $date = $date ?: date('Y-m-d'); $discounts = (new PlayerAccountDiscountsModel())->where('player_id', $playerId)->where('active', 1)->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $date)->groupEnd()->groupStart()->where('ends_at', null)->orWhere('ends_at >=', $date)->groupEnd()->orderBy('id', 'DESC')->findAll();
        if (! $discounts) return $quote;
        $totalOriginal = 0.0; $totalDiscount = 0.0; $totalFinal = 0.0;
        foreach (($quote['items'] ?? []) as $index => $item) {
            $base = (float) ($item['original_amount'] ?? $item['base']['base_amount'] ?? 0); $currentDiscount = (float) ($item['discount_amount'] ?? 0); $serviceCode = (string) ($item['field']['service_type'] ?? ''); $selected = null;
            // No se combinan promociones: si ya hay una oferta mejor, se conserva.
            if ($currentDiscount <= 0) foreach ($discounts as $discount) { $codes = json_decode((string) ($discount['service_codes'] ?? ''), true); if (is_array($codes) && $codes !== [] && ! in_array($serviceCode, $codes, true)) continue; $selected = $discount; break; }
            if ($selected) { $amount = $selected['discount_type'] === 'fixed' ? (float) $selected['value'] : round($base * min(100, (float) $selected['value']) / 100, 2); $amount = min($base, max(0, $amount)); $quote['items'][$index]['discount_amount'] = $amount; $quote['items'][$index]['final_amount'] = round(max(0, $base - $amount), 2); }
            $totalOriginal += $base; $totalDiscount += (float) ($quote['items'][$index]['discount_amount'] ?? 0); $totalFinal += (float) ($quote['items'][$index]['final_amount'] ?? $base);
        }
        $quote['original_total'] = round($totalOriginal, 2); $quote['discount_total'] = round($totalDiscount, 2); $quote['final_total'] = round($totalFinal, 2); return $quote;
    }
}
