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

    private const COLUMN_ALIASES = [
        'type' => ['نوع طلا', 'نوع سکه', 'نوع', 'نماد', 'عنوان'],
        'current' => ['نرخ فعلی', 'قیمت فعلی', 'نرخ لحظه‌ای', 'قیمت لحظه‌ای', 'لحظه‌ای', 'فعلی', 'جاری', 'آخرین نرخ'],
        'high' => ['بالاترین قیمت', 'بالاترین', 'بیشترین قیمت', 'بیشترین', 'بیشینه', 'سقف'],
        'low' => ['کمترین قیمت', 'کمترین', 'پایین‌ترین قیمت', 'پایین‌ترین', 'کمینه', 'کف'],
        'yesterday' => ['میانگین دیروز', 'قیمت دیروز', 'نرخ دیروز', 'دیروز', 'میانگین'],
        'change' => ['تغییر از دیروز', 'درصد تغییر', 'میزان تغییر', 'تغییر', 'نوسان'],
    ];
    /** Soft cap so a hostile/oversized upstream cannot exhaust shared-hosting memory. */
    private const MAX_RESPONSE_BYTES = 2_000_000;

    public function fetch(): array
    {
        $html = $this->fetchHtml();
        return $this->parse($html, now()->toIso8601String());
    }

    public function fetchHtml(): string
    {
        $connect = max(1, (int)config('gold.timeout_connect', 3));
        $read = max(1, (int)config('gold.timeout_read', 5));
        $client = new Client([
            'timeout' => $connect + $read,
            'connect_timeout' => $connect,
            'http_errors' => false,
            'allow_redirects' => ['max' => 3, 'strict' => true, 'referer' => true, 'track_redirects' => false],
            // TLS verify on (Guzzle default). Do not disable — fix CA bundle if host SSL fails.
            'headers' => [
                'User-Agent' => config('gold.http_headers.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => config('gold.http_headers.accept_language'),
                'Referer' => config('gold.http_headers.referer'),
            ],
        ]);
        $attempts = max(1, (int)config('gold.retry_count', 1) + 1);
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $response = $client->get($this->sourceUrl(), [
                    'stream' => true,
                ]);
                $status = $response->getStatusCode();
                $html = $this->readBodyBounded($response->getBody(), self::MAX_RESPONSE_BYTES);
                if ($status >= 400 || trim($html) === '' || $this->looksBlocked($html)) {
                    throw new RuntimeException('منبع قیمت‌ها در دسترس نیست یا درخواست را مسدود کرده است.');
                }
                return $html;
            } catch (\Throwable $e) {
                if ($i === $attempts) {
                    throw new RuntimeException('ارتباط با منبع برقرار نشد: ' . $e->getMessage(), 0, $e);
                }
                // Do not retry obvious client blocks (403/404) forever — still allow one bounded retry for flaky upstream.
                usleep(max(1, (int)config('gold.retry_backoff_milliseconds', 150)) * 1000 * $i);
            }
        }
        throw new RuntimeException('دریافت داده ناموفق بود.');
    }

    private function sourceUrl(): string
    {
        return (string)config('gold.source_url', 'https://www.estjt.ir/price/');
    }

    private function readBodyBounded($body, int $maxBytes): string
    {
        $chunks = '';
        $size = 0;
        while (!$body->eof()) {
            $chunk = $body->read(65536);
            if ($chunk === '') {
                break;
            }
            $size += strlen($chunk);
            if ($size > $maxBytes) {
                throw new RuntimeException('پاسخ منبع بیش از حد بزرگ است.');
            }
            $chunks .= $chunk;
        }

        return $chunks;
    }

    private function looksBlocked(string $html): bool
    {
        $body = strtolower($html);
        foreach ((array)config('gold.blocked_page_patterns', []) as $pattern) {
            if (str_contains($body, $pattern)) return true;
        }
        return false;
    }

    public function parse(string $html, string $fetchedAt): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
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

        return [
            'source' => [
                'key' => config('gold.source_key'),
                'name' => config('gold.source_name'),
                'url' => $this->sourceUrl(),
                'fetchedAt' => $fetchedAt,
            ],
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

    private function knownGoldTypes(): array
    {
        $names = app(MarketCatalog::class)->names('gold');

        return $names !== [] ? $names : self::GOLD_TYPES;
    }

    private function knownCoinTypes(): array
    {
        $names = app(MarketCatalog::class)->names('coin');

        return $names !== [] ? $names : self::COIN_TYPES;
    }

    private function orderedRows(array $rows, array $knownTypes, string $fallbackCategory): array
    {
        $ordered = [];
        foreach ($knownTypes as $type) {
            $key = PersianNumber::label($type);
            if (isset($rows[$key])) {
                $ordered[] = $rows[$key];
            }
        }
        if ($ordered === []) {
            throw new RuntimeException("هیچ نماد شناخته‌شده‌ای در جدول {$fallbackCategory} پیدا نشد.");
        }

        return $ordered;
    }

    private function extractRows(DOMElement $table, DOMXPath $xpath, bool $gold): array
    {
        $map = $this->columnMap($table, $xpath);
        $needed = max($map) + 1;
        $rows = [];
        foreach ($xpath->query('.//tr', $table) as $tr) {
            $cells = $xpath->query('.//td', $tr);
            if ($cells->length < $needed) {
                continue;
            }
            $type = PersianNumber::clean($cells->item($map['type'])->textContent);
            $currentRaw = PersianNumber::clean($cells->item($map['current'])->textContent);
            $yesterdayRaw = PersianNumber::clean($cells->item($map['yesterday'])->textContent);
            $highRaw = PersianNumber::clean($cells->item($map['high'])->textContent);
            $lowRaw = PersianNumber::clean($cells->item($map['low'])->textContent);
            [$currentValue, $currency] = PersianNumber::currencyAndValue($currentRaw);
            [$yesterdayValue, $yesterdayCurrency] = PersianNumber::currencyAndValue($yesterdayRaw);
            [$highValue, $highCurrency] = PersianNumber::currencyAndValue($highRaw);
            [$lowValue, $lowCurrency] = PersianNumber::currencyAndValue($lowRaw);
            if ($currency === null && $highCurrency !== null) {
                $currency = $highCurrency;
            }
            if ($currency === null && $lowCurrency !== null) {
                $currency = $lowCurrency;
            }
            $changeCell = $cells->item($map['change']);
            $item = [
                'type' => $type,
                'category' => $gold ? 'gold' : 'coin',
                'current' => ['value' => $currentValue, 'raw' => $currentRaw, 'currency' => $currency],
                'high' => ['value' => $highValue, 'raw' => $highRaw, 'currency' => $highCurrency ?? $currency],
                'low' => ['value' => $lowValue, 'raw' => $lowRaw, 'currency' => $lowCurrency ?? $currency],
                'yesterdayAvg' => ['value' => $yesterdayValue, 'raw' => $yesterdayRaw, 'currency' => $yesterdayCurrency],
                'change' => PersianNumber::change($changeCell->textContent, $this->direction($changeCell)),
            ];
            $rows[PersianNumber::label($type)] = $item;
        }
        return $rows;
    }

    /** @return array{type:int,current:int,high:int,low:int,yesterday:int,change:int} */
    private function columnMap(DOMElement $table, DOMXPath $xpath): array
    {
        $headers = [];
        foreach ($xpath->query('.//th', $table) as $index => $th) {
            $headers[$index] = PersianNumber::label($th->textContent);
        }
        if ($headers === []) {
            throw new RuntimeException('هدر جدول قیمت پیدا نشد.');
        }

        $map = [];
        // First pass: try exact match
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($headers as $index => $header) {
                foreach ($aliases as $alias) {
                    $needle = PersianNumber::label($alias);
                    if ($header === $needle) {
                        $map[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        // Second pass: fill in remaining fields with contains match, skipping already claimed columns
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            if (isset($map[$field])) {
                continue;
            }
            foreach ($headers as $index => $header) {
                if (in_array($index, $map, true)) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    $needle = PersianNumber::label($alias);
                    if (str_contains($header, $needle)) {
                        $map[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        foreach (array_keys(self::COLUMN_ALIASES) as $field) {
            if (!isset($map[$field])) {
                throw new RuntimeException('هدر جدول قیمت با قرارداد مورد انتظار جور نیست: ' . $field);
            }
        }

        return $map;
    }

    private function direction(DOMElement $cell): string
    {
        foreach ([$cell, ...iterator_to_array($cell->getElementsByTagName('*'))] as $element) {
            $class = (string)$element->getAttribute('class');
            if (str_contains($class, 'asc') || str_contains($class, 'up')) return 'asc';
            if (str_contains($class, 'desc') || str_contains($class, 'down')) return 'desc';
        }
        $raw = PersianNumber::clean($cell->textContent);
        return str_starts_with($raw, '-') ? 'desc' : (str_starts_with($raw, '+') ? 'asc' : 'none');
    }
}
