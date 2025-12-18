<?php

namespace App\MarketData\Support;

class NumberParser
{
    public static function parseLocaleNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim(str_replace("\xc2\xa0", ' ', $value));
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            return null;
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma && !$hasDot) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        if ($value === '' || $value === '-' || $value === '.') {
            return null;
        }

        return (float) $value;
    }
}

