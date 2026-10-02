<?php

namespace App\Http\Controllers;

use App\Services\MarketSummaryService;
use App\Services\PersianNumber;
use App\Services\RangeParser;
use App\Support\LastFetch;
use App\Support\MarketItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PricePageController extends Controller
{
    public function __construct(
        private MarketSummaryService $summaryService,
        private RangeParser          $rangeParser,
    )
    {
    }

    public function __invoke($range = null)
    {
        $availableRangeKeys = collect(config('gold.chart_available_ranges', ['1d', '7d', '30d', '90d', '180d', '365d']))
            ->map(fn($key) => $this->rangeParser->canonicalKey($key))
            ->unique()
            ->values();

        $seoDayRanges = $availableRangeKeys
            ->filter(fn(string $key) => $this->rangeParser->isSeoIndexedRange($key))
            ->map(fn(string $key) => (int)$key)
            ->filter(fn(int $days) => $days >= 1 && $days <= (int)config('gold.history_max_days', 365))
            ->unique()
            ->sort()
            ->values();

        $activeRangeKey = null;
        if ($range !== null) {
            $parsed = $this->rangeParser->tryParse($range);
            if ($parsed !== null && $availableRangeKeys->contains($parsed['key'])) {
                $activeRangeKey = $parsed['key'];
            }
        }

        try {
            $items = $this->summaryService->items();
            $lastFetch = $this->summaryService->lastFetch();
        } catch (Throwable $exception) {
            Log::error('Price page query failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $items = collect();
            $lastFetch = null;
        }

        return view('app', [
            'seo' => $this->seoPayload($items, $seoDayRanges, $activeRangeKey, $lastFetch),
            'seoItems' => $items,
            'marketSummary' => $this->embeddedMarketSummary(),
        ]);
    }

    private function seoPayload(Collection $items, Collection $seoDayRanges, ?string $activeRangeKey, ?LastFetch $lastFetch): array
    {
        $primaryGold = $items->first(fn($item) => str_contains($item->name, '۱۸') || str_contains($item->name, '18'))
            ?: $items->firstWhere('category', 'gold');
        $primaryCoin = $items->firstWhere('category', 'coin');
        $goldPrice = $this->formatDisplayPrice($primaryGold, $primaryGold?->latestPrice?->current_value);
        $coinPrice = $this->formatDisplayPrice($primaryCoin, $primaryCoin?->latestPrice?->current_value);
        $canonical = $activeRangeKey
            ? $this->rangeParser->trendUrl($activeRangeKey)
            : url('/price/');
        $updatedAt = optional($lastFetch?->finishedAt ?: $items->pluck('latestPrice.fetched_at')->filter()->max())->toIso8601String();
        $rangeLabel = $activeRangeKey ? $this->rangeParser->seoRangeLabel($activeRangeKey) : null;
        $title = $rangeLabel
            ? "نمودار {$rangeLabel}ه قیمت طلا و سکه | قیمت لحظه‌ای بازار ایران"
            : 'قیمت طلا امروز و قیمت لحظه‌ای سکه | داشبورد بازار ایران';
        $description = $rangeLabel
            ? "بررسی روند {$rangeLabel}ه قیمت طلا و سکه با داده‌های تاریخی، نمودار تعاملی و آخرین قیمت‌های ثبت‌شده بازار ایران."
            : ($items->isNotEmpty()
                ? "قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران. طلای ۱۸ عیار: {$goldPrice}، سکه: {$coinPrice}. مشاهده تغییرات زنده و نمودار تاریخی."
                : 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار تعاملی، تاریخچه تغییرات و داده‌های به‌روزشونده.');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1',
            'updatedAt' => $updatedAt,
            'keywords' => 'قیمت طلا امروز,قیمت سکه امروز,قیمت طلای ۱۸ عیار,نمودار قیمت طلا,قیمت لحظه‌ای طلا,بازار طلا ایران,قیمت مظنه,حباب سکه,انس جهانی',
            'ogImage' => url(config('learn.price_og_image', config('learn.default_og_image'))),
            'alternateRanges' => $seoDayRanges->map(fn(int $days) => [
                'days' => $days,
                'range' => "{$days}d",
                'url' => $this->rangeParser->trendUrl("{$days}d"),
                'title' => "روند {$days} روزه قیمت طلا و سکه",
            ])->all(),
            'jsonLd' => $this->cachedJsonLd($items, $seoDayRanges, $activeRangeKey, $updatedAt),
        ];
    }

    private function formatDisplayPrice(?MarketItem $item, $value): string
    {
        if ($value === null) {
            return 'نامشخص';
        }

        if ($item?->isUsd()) {
            return number_format((float)$value, 2, '.', ',') . ' دلار';
        }

        return number_format((float)$value, 0, '.', ',') . ' تومان';
    }

    private function cachedJsonLd(Collection $items, Collection $seoDayRanges, ?string $activeRangeKey, ?string $updatedAt): array
    {
        $version = (int)Cache::get('gold:price-data-version', 0);
        $ttl = max(5, (int)config('gold.summary_cache_seconds', 10));

        return Cache::remember(
            'gold:price-jsonld:v4:' . $version . ':' . ($activeRangeKey ?? 'home'),
            $ttl,
            fn() => $this->jsonLd($items, $seoDayRanges, $activeRangeKey, $updatedAt)
        );
    }

    private function jsonLd(Collection $items, Collection $seoDayRanges, ?string $activeRangeKey, ?string $updatedAt): array
    {
        $pageUrl = $activeRangeKey
            ? $this->rangeParser->trendUrl($activeRangeKey)
            : url('/price/');
        $rangeLabel = $activeRangeKey ? $this->rangeParser->seoRangeLabel($activeRangeKey) : null;
        $pageName = $rangeLabel
            ? "نمودار {$rangeLabel}ه قیمت طلا و سکه"
            : 'قیمت طلا امروز و قیمت لحظه‌ای سکه';

        $listElements = $this
            ->primaryJsonLdItems($items)
            ->map(function (MarketItem $item, int $index) use ($pageUrl) {
                $price = $item->latestPrice;

                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => [
                        '@type' => 'FinancialProduct',
                        '@id' => url('/price/#item-' . $item->id),
                        'name' => $item->name,
                        'category' => $item->category === 'coin' ? 'سکه طلا' : 'طلا',
                        'description' => "قیمت لحظه‌ای {$item->name} در بازار ایران بر اساس داده‌های ثبت‌شده از " . config('gold.source_name') . '.',
                        'url' => $pageUrl,
                        'provider' => [
                            '@type' => 'Organization',
                            'name' => config('gold.source_name'),
                            'url' => config('gold.source_url'),
                        ],
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => $this->schemaOfferPrice($item, $price?->current_value),
                            'priceCurrency' => $item->isUsd() ? 'USD' : 'IRR',
                            'availability' => 'https://schema.org/InStock',
                            'url' => $pageUrl,
                            'validFrom' => optional($price?->fetched_at)->toIso8601String(),
                            'priceValidUntil' => $this->schemaPriceValidUntil($price?->fetched_at),
                        ],
                        'additionalProperty' => [
                            ['@type' => 'PropertyValue', 'name' => 'changePercent', 'value' => $this->schemaNumber(
                                PersianNumber::signedByDirection($price?->change_percent, $price?->direction ?? 'none')
                            ), 'unitText' => 'PERCENT'],
                        ],
                    ],
                ];
            })->values()->all();

        $marketList = [
            '@type' => 'ItemList',
            '@id' => url('/price/#market-items'),
            'name' => 'قیمت لحظه‌ای طلا و سکه ایران',
            // Primary items only (18k + coin + ounce); full table is in noscript.
            'numberOfItems' => count($listElements),
            'itemListElement' => $listElements,
        ];

        $dataset = [
            '@type' => 'Dataset',
            '@id' => url('/price/#historical-price-dataset'),
            'name' => 'داده‌های تاریخی قیمت طلا و سکه ایران',
            'description' => 'مجموعه داده تاریخی برای بررسی روند قیمت طلا و سکه در بازه‌های ۱ تا ۳۶۵ روزه.',
            'url' => url('/price/'),
            'inLanguage' => 'fa-IR',
            'isAccessibleForFree' => true,
            'dateModified' => $updatedAt,
            'creator' => [
                '@type' => 'Organization',
                'name' => 'Ernoxin',
                'url' => url('/'),
            ],
            'temporalCoverage' => 'P365D',
            'variableMeasured' => ['current_value', 'high_value', 'low_value', 'change_value', 'change_percent'],
            'distribution' => $seoDayRanges->map(fn(int $days) => [
                '@type' => 'DataDownload',
                'encodingFormat' => 'text/html',
                'name' => "تاریخچه {$days} روزه قیمت طلا و سکه",
                'contentUrl' => $this->rangeParser->trendUrl("{$days}d"),
            ])->all(),
        ];

        $breadcrumbItems = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'قیمت طلا و سکه', 'item' => url('/price/')],
        ];
        if ($rangeLabel) {
            $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => "روند {$rangeLabel}ه", 'item' => $pageUrl];
        }

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => url('/#organization'),
                'name' => 'Ernoxin Gold',
                'url' => url('/'),
                'logo' => url(config('learn.default_og_image')),
                'description' => 'داشبورد قیمت لحظه‌ای طلا و سکه و بلاگ آموزشی بازار طلا ایران',
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/#website'),
                'name' => 'Ernoxin Gold',
                'url' => url('/'),
                'inLanguage' => 'fa-IR',
                'publisher' => ['@id' => url('/#organization')],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url(config('learn.base_path', '/blog')) . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $pageUrl . '#webpage',
                'url' => $pageUrl,
                'name' => $pageName,
                'description' => 'داشبورد لحظه‌ای و تاریخی بازار طلا و سکه ایران.',
                'dateModified' => $updatedAt,
                'datePublished' => config('learn.reviewed_at_iso'),
                'inLanguage' => 'fa-IR',
                'breadcrumb' => [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbItems,
                ],
                'isPartOf' => ['@id' => url('/#website')],
                'mainEntity' => $activeRangeKey ? ['@id' => url('/price/#historical-price-dataset')] : ['@id' => url('/price/#market-items')],
                'speakable' => [
                    '@type' => 'SpeakableSpecification',
                    'cssSelector' => ['h1', '.hero p', '.marketItem small'],
                ],
            ],
            $dataset,
            $marketList,
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * The subset of market items surfaced in the inline JSON-LD ItemList.
     * Keeps the inlined payload small (one gold + one coin + USD ounce when
     * present) while the full list stays available in the rendered table.
     */
    private function primaryJsonLdItems(Collection $items): Collection
    {
        $primary = collect()
            ->push($items->first(fn($item) => str_contains($item->name, '۱۸') || str_contains($item->name, '18')))
            ->push($items->firstWhere('category', 'coin'))
            ->push($items->first(fn($item) => $item->isUsd()))
            ->filter();

        return $primary->isNotEmpty() ? $primary : $items->take(3);
    }

    /** Stored prices are toman; schema.org IRR requires rial (×10). */
    private function schemaOfferPrice(MarketItem $item, $value): ?float
    {
        if ($value === null || !is_numeric($value) || (float)$value <= 0) {
            return null;
        }

        $price = (float)$value;

        return $item->isUsd() ? $price : $price * 10;
    }

    private function schemaPriceValidUntil(?Carbon $fetchedAt): string
    {
        $intervalMinutes = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $base = $fetchedAt ? $fetchedAt->copy() : now();

        return $base->addMinutes($intervalMinutes)->toIso8601String();
    }

    private function schemaNumber($value): ?float
    {
        return $value === null ? null : (float)$value;
    }

    private function embeddedMarketSummary(): ?array
    {
        try {
            return $this->summaryService->apiPayload();
        } catch (Throwable $exception) {
            Log::warning('Embedded market summary failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
