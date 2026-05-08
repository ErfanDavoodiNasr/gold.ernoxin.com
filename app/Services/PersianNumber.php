<?php

namespace App\Services;

class PersianNumber
{
    private const MAP = ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    public static function clean(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text ?? ''));
    }

    public static function digits(?string $text): string
    {
        return strtr($text ?? '', self::MAP);
    }

    public static function label(?string $text): string
    {
        $value = self::digits($text);
        $value = str_replace(["ي", "ك", "\u{200c}"], ["ی", "ک", " "], $value);
        return self::clean($value);
    }

    public static function numeric(?string $text): ?float
    {
        $token = self::numericToken($text);
        if ($token === null) {
            return null;
        }
        $standard = self::standardize($token);
        return $standard === null ? null : (float) $standard;
    }

    public static function currencyAndValue(?string $text): array
    {
        $raw = self::clean($text);
        $normalized = self::digits($raw);
        if (!preg_match('/[-+]?\d[\d,\.٫٬]*/u', $normalized, $m, PREG_OFFSET_CAPTURE)) {
            return [null, null];
        }
        $token = $m[0][0];
        $start = $m[0][1];
        $currency = self::clean(substr($normalized, 0, $start).substr($normalized, $start + strlen($token)));
        return [self::numeric($token), in_array($currency, ['', '-', '—', '–', '―'], true) ? null : $currency];
    }

    public static function change(?string $rawText, string $directionHint = 'none'): array
    {
        $raw = self::clean($rawText);
        if (in_array($raw, ['', '-', '—', '–', '―'], true)) {
            return ['value' => null, 'percent' => null, 'direction' => 'none', 'raw' => $raw];
        }
        $normalized = self::digits($raw);
        $percent = null;
        $valuePart = $normalized;
        if (preg_match('/\(([^)]*)\)/u', $normalized, $m, PREG_OFFSET_CAPTURE)) {
            $percent = self::numeric($m[1][0]);
            $valuePart = substr($normalized, 0, $m[0][1]);
        }
        $value = self::numeric($valuePart);
        $direction = in_array($directionHint, ['asc', 'desc'], true) ? $directionHint : 'none';
        if ($direction === 'none') {
            $direction = $value === null || $value == 0 ? 'none' : ($value > 0 ? 'asc' : 'desc');
        } elseif ($value !== null) {
            $value = $direction === 'desc' ? -abs($value) : abs($value);
        }
        return ['value' => $value, 'percent' => $percent, 'direction' => $direction, 'raw' => $raw];
    }

    private static function numericToken(?string $text): ?string
    {
        $normalized = str_replace(['−', '–'], '-', self::digits(self::clean($text)));
        return preg_match('/[-+]?\d[\d,\.٫٬]*/u', $normalized, $m) ? $m[0] : null;
    }

    private static function standardize(string $token): ?string
    {
        $token = preg_replace('/[^0-9,\.\-+]/', '', str_replace(['٬', '٫'], [',', '.'], self::digits($token)));
        if ($token === '' || in_array($token, ['-', '+', '.', ','], true)) {
            return null;
        }
        $sign = '';
        if (in_array($token[0], ['-', '+'], true)) {
            $sign = $token[0];
            $token = substr($token, 1);
        }
        $separators = substr_count($token, '.') + substr_count($token, ',');
        if ($separators === 0) {
            $digits = preg_replace('/\D/', '', $token);
            return $digits === '' ? null : $sign.$digits;
        }
        if ($separators === 1) {
            $sep = strpos($token, '.') !== false ? '.' : ',';
            [$left, $right] = explode($sep, $token, 2);
            $left = preg_replace('/\D/', '', $left);
            $right = preg_replace('/\D/', '', $right);
            return $right !== '' && strlen($right) <= 2 ? $sign.($left ?: '0').'.'.$right : $sign.$left.$right;
        }
        $last = max(strrpos($token, '.'), strrpos($token, ','));
        $left = preg_replace('/\D/', '', substr($token, 0, $last));
        $right = preg_replace('/\D/', '', substr($token, $last + 1));
        return $right !== '' && strlen($right) <= 2 && $left !== '' ? $sign.$left.'.'.$right : $sign.preg_replace('/\D/', '', $token);
    }
}
