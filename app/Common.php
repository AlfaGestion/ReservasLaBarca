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
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }
}

if (! function_exists('minutesToHuman')) {
    function minutesToHuman($minutes): string {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ' hs';
        }
        if ($remaining > 0) {
            $parts[] = $remaining . ' min';
        }

        $human = $parts === [] ? '0 min' : implode(' ', $parts);

        return $minutes . ' min (' . $human . ')';
    }
}

if (! function_exists('combine_duration_minutes')) {
    function combine_duration_minutes($hours, $minutes): int {
        return (max(0, (int) $hours) * 60) + max(0, (int) $minutes);
    }
}
