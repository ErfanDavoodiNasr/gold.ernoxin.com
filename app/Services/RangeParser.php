<?php

namespace App\Services;

final class RangeParser
{
    public function canonicalKey(mixed $value): string
    {
        return $this->parse($value)['key'];
    }

    /**
     * Parse a range that is known to be valid (e.g. config default).
     * Invalid input falls back to chart_default_range, then 1d.
     */
    public function parse(mixed $value): array
    {
        return $this->tryParse($value)
            ?? $this->tryParse(config('gold.chart_default_range', '1d'))
            ?? ['key' => '1d', 'minutes' => 1440];
    }

    /**
     * Strict parse: only keys listed in chart_available_ranges (or Nh/Nd / bare days
     * that canonicalize to one of those keys). Returns null when invalid.
     */
    public function tryParse(mixed $value): ?array
    {
        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*([hd])$/', $raw, $matches)) {
            $amount = max(1, (int)$matches[1]);
            $unit = $matches[2];
        } elseif (ctype_digit($raw)) {
            $amount = max(1, (int)$raw);
            $unit = 'd';
        } else {
            return null;
        }

        $key = $unit === 'h' ? "{$amount}h" : "{$amount}d";
        $allowed = array_map('strtolower', config('gold.chart_available_ranges', []));
        if ($allowed !== [] && !in_array($key, $allowed, true)) {
            return null;
        }

        $minutes = $unit === 'h' ? $amount * 60 : $amount * 1440;
        $maxMinutes = max(1, (int)config('gold.history_max_days', 365)) * 1440;

        return [
            'key' => $key,
            'minutes' => min($minutes, $maxMinutes),
        ];
    }

    public function trendUrl(string $key): string
    {
        return url('/price/trends/' . $this->trendUrlSlug($key));
    }

    /** Slug for /price/trends/{slug} — hours keep suffix (1h), days are bare number (7). */
    public function trendUrlSlug(string $key): string
    {
        $key = strtolower(trim($key));
        if (str_ends_with($key, 'h')) {
            return $key;
        }

        if (preg_match('/^(\d+)d$/', $key, $matches)) {
            return $matches[1];
        }

        return $key;
    }

    /** Day-only ranges suitable for SEO index / sitemap (excludes hour windows). */
    public function isSeoIndexedRange(string $key): bool
    {
        return (bool)preg_match('/^(\d+)d$/', strtolower(trim($key)), $matches)
            && (int)$matches[1] >= 1;
    }

    public function seoRangeLabel(string $key): string
    {
        $key = strtolower(trim($key));
        if (preg_match('/^(\d+)h$/', $key, $matches)) {
            return $matches[1] . ' ساعت';
        }
        if (preg_match('/^(\d+)d$/', $key, $matches)) {
            return $matches[1] . ' روز';
        }

        return $key;
    }
}
