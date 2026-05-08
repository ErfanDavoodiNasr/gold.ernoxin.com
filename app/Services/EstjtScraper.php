<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Client;
use RuntimeException;

class EstjtScraper
{
    private const GOLD_TYPES = ['انس طلا', 'مظنه تهران', 'طلای ۱۸ عیار', 'طلای ۲۴ عیار'];
    private const COIN_TYPES = ['سکه طرح قدیم', 'سکه طرح جدید', 'نیم سکه', 'ربع سکه', 'سکه یک گرمی'];

    public function fetch(): array
    {
        $html = $this->fetchHtml();
        return $this->parse($html, now()->toIso8601String());
    }

    public function fetchHtml(): string
    {
        $client = new Client([
            'timeout' => config('gold.timeout_connect') + config('gold.timeout_read'),
            'connect_timeout' => config('gold.timeout_connect'),
            'http_errors' => false,
            'headers' => [
                'User-Agent' => config('gold.http_headers.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => config('gold.http_headers.accept_language'),
                'Referer' => config('gold.http_headers.referer'),
            ],
        ]);
        $attempts = max(1, (int) config('gold.retry_count', 2) + 1);
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $response = $client->get($this->sourceUrl());
                $status = $response->getStatusCode();
                $html = (string) $response->getBody();
                if ($status >= 400 || trim($html) === '' || $this->looksBlocked($html)) {
                    throw new RuntimeException('منبع قیمت‌ها در دسترس نیست یا درخواست را مسدود کرده است.');
                }
                return $html;
            } catch (\Throwable $e) {
                if ($i === $attempts) {
                    throw new RuntimeException('ارتباط با منبع برقرار نشد: '.$e->getMessage(), 0, $e);
                }
                usleep(max(1, (int) config('gold.retry_backoff_milliseconds', 300)) * 1000 * $i);
            }
        }
        throw new RuntimeException('دریافت داده ناموفق بود.');
    }

    public function parse(string $html, string $fetchedAt): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);
        $tables = iterator_to_array($xpath->query('//table'));
        if (!$tables) {
            throw new RuntimeException('ساختار صفحه منبع تغییر کرده است.');
        }
        [$goldTable, $coinTable] = $this->locateTables($tables, $xpath);
        if (!$goldTable || !$coinTable) {
            throw new RuntimeException('جدول‌های قیمت در منبع پیدا نشدند.');
        }
        $goldRows = $this->orderedRows($this->extractRows($goldTable, $xpath, true), $this->knownGoldTypes(), 'طلا');
        $coinRows = $this->orderedRows($this->extractRows($coinTable, $xpath, false), $this->knownCoinTypes(), 'سکه');
        if (!$goldRows || !$coinRows) {
            throw new RuntimeException('ساختار جدول قیمت‌ها تغییر کرده است.');
        }

        return [
            'source' => ['name' => config('gold.source_name'), 'url' => $this->sourceUrl(), 'fetchedAt' => $fetchedAt],
            'gold' => $goldRows,
            'coin' => $coinRows,
        ];
    }

    private function locateTables(array $tables, DOMXPath $xpath): array
    {
        $gold = $coin = null;
        foreach ($tables as $table) {
            $headers = [];
            foreach ($xpath->query('.//th', $table) as $th) {
                $headers[] = PersianNumber::label($th->textContent);
            }
            $first = $headers[0] ?? '';
            if (str_contains($first, config('gold.table_header_labels.gold', 'نوع طلا'))) {
                $gold = $table;
            } elseif (str_contains($first, config('gold.table_header_labels.coin', 'نوع سکه'))) {
                $coin = $table;
            }
        }
        $goldKeys = array_map([PersianNumber::class, 'label'], $this->knownGoldTypes());
        $coinKeys = array_map([PersianNumber::class, 'label'], $this->knownCoinTypes());
        foreach ($tables as $table) {
            $types = [];
            foreach ($xpath->query('.//tr', $table) as $tr) {
                $cells = $xpath->query('.//td', $tr);
                if ($cells->length) {
                    $types[] = PersianNumber::label($cells->item(0)->textContent);
                }
            }
            if (!$gold && count(array_intersect($types, $goldKeys))) {
                $gold = $table;
            }
            if (!$coin && count(array_intersect($types, $coinKeys))) {
                $coin = $table;
            }
        }
        return [$gold, $coin];
    }

    private function extractRows(DOMElement $table, DOMXPath $xpath, bool $gold): array
    {
        $rows = [];
        foreach ($xpath->query('.//tr', $table) as $tr) {
            $cells = $xpath->query('.//td', $tr);
            if ($cells->length < 6) {
                continue;
            }
            $type = PersianNumber::clean($cells->item(0)->textContent);
            $currentRaw = PersianNumber::clean($cells->item(1)->textContent);
            $yesterdayRaw = PersianNumber::clean($cells->item(4)->textContent);
            [$currentValue, $currency] = $gold ? PersianNumber::currencyAndValue($currentRaw) : [PersianNumber::numeric($currentRaw), null];
            [$yesterdayValue, $yesterdayCurrency] = $gold ? PersianNumber::currencyAndValue($yesterdayRaw) : [PersianNumber::numeric($yesterdayRaw), null];
            $item = [
                'type' => $type,
                'category' => $gold ? 'gold' : 'coin',
                'current' => ['value' => $currentValue, 'raw' => $currentRaw, 'currency' => $currency],
                'high' => ['value' => PersianNumber::numeric($cells->item(2)->textContent), 'raw' => PersianNumber::clean($cells->item(2)->textContent)],
                'low' => ['value' => PersianNumber::numeric($cells->item(3)->textContent), 'raw' => PersianNumber::clean($cells->item(3)->textContent)],
                'yesterdayAvg' => ['value' => $yesterdayValue, 'raw' => $yesterdayRaw, 'currency' => $yesterdayCurrency],
                'change' => PersianNumber::change($cells->item(5)->textContent, $this->direction($cells->item(5))),
            ];
            $rows[PersianNumber::label($type)] = $item;
        }
        return $rows;
    }

    private function orderedRows(array $rows, array $knownTypes, string $fallbackCategory): array
    {
        $ordered = [];
        foreach ($knownTypes as $type) {
            $key = PersianNumber::label($type);
            if (isset($rows[$key])) {
                $ordered[] = $rows[$key];
                unset($rows[$key]);
            }
        }
        return array_merge($ordered, array_values($rows));
    }

    private function direction(DOMElement $cell): string
    {
        foreach ([$cell, ...iterator_to_array($cell->getElementsByTagName('*'))] as $element) {
            $class = $element->getAttribute('class');
            if (str_contains($class, 'asc')) return 'asc';
            if (str_contains($class, 'desc')) return 'desc';
        }
        $raw = PersianNumber::clean($cell->textContent);
        return str_starts_with($raw, '-') ? 'desc' : (str_starts_with($raw, '+') ? 'asc' : 'none');
    }

    private function looksBlocked(string $html): bool
    {
        $body = strtolower($html);
        foreach ((array) config('gold.blocked_page_patterns', []) as $pattern) {
            if (str_contains($body, $pattern)) return true;
        }
        return false;
    }

    private function sourceUrl(): string
    {
        return (string) config('gold.source_url', 'https://www.estjt.ir/price/');
    }

    private function knownGoldTypes(): array
    {
        return config('gold.known_items.gold') ?: self::GOLD_TYPES;
    }

    private function knownCoinTypes(): array
    {
        return config('gold.known_items.coin') ?: self::COIN_TYPES;
    }
}
