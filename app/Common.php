<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('format_price_ar')) {
    function format_price_ar($amount) {
        return '$ ' . number_format((float)$amount, 2, ',', '.');
    }
}

if (! function_exists('parse_price_ar')) {
    function parse_price_ar($amount): float {
        if (is_numeric($amount)) {
            return (float) $amount;
        }

        $value = trim((string) $amount);
        $value = str_replace(['$', ' '], '', $value);
        $value = preg_replace('/[^0-9,.\-]/', '', $value);

        $commaPos = strrpos($value, ',');
        $dotPos = strrpos($value, '.');

        if ($commaPos !== false && $dotPos !== false) {
            if ($commaPos > $dotPos) {
                // 40.500,75 => 40500.75
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // 40,500.75 => 40500.75
                $value = str_replace(',', '', $value);
            }
        } elseif ($commaPos !== false) {
            // 40,5 o 40,50 => decimal con coma; 40,500 => miles
            $value = preg_match('/,\d{1,2}$/', $value)
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        } elseif ($dotPos !== false) {
            // 40.5 o 40.50 => decimal con punto; 40.500 => miles
            if (!preg_match('/\.\d{1,2}$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        return (float) $value;
    }
}

if (! function_exists('minutesToHuman')) {
    function minutesToHuman($minutes): string {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        return $hours . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('combine_duration_minutes')) {
    function combine_duration_minutes($hours, $minutes): int {
        return (max(0, (int) $hours) * 60) + max(0, (int) $minutes);
    }
}
