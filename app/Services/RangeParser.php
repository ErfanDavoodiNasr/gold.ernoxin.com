<?php

namespace App\Services;

final class RangeParser
{
    public function canonicalKey(mixed $value): string
    {
        return $this->parse($value)['key'];
    }

    public function parse(mixed $value): array
    {
        $raw = strtolower(trim((string)$value));

        if (preg_match('/^(\d+)\s*([hd])$/', $raw, $matches)) {
            $amount = max(1, (int)$matches[1]);
            $unit = $matches[2];
        } else {
            $amount = max(1, (int)$raw);
            $unit = 'd';
        }

        $minutes = $unit === 'h' ? $amount * 60 : $amount * 1440;
        $maxMinutes = max(1, (int)config('gold.history_max_days', 365)) * 1440;

        return [
            'key' => $unit === 'h' ? "{$amount}h" : "{$amount}d",
            'minutes' => min($minutes, $maxMinutes),
        ];
    }
}
