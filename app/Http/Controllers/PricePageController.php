<?php

namespace App\Http\Controllers;

use App\Models\FetchLog;
use App\Models\MarketItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PricePageController extends Controller
{
    public function __invoke($days = null)
    {
        $availableRanges = collect(config('gold.chart_available_ranges', ['1d', '7d', '30d', '90d', '180d', '365d']))
            ->map(fn($range) => $this->rangeDays($range))
            ->filter(fn($range) => $range >= 1 && $range <= (int)config('gold.history_max_days', 365))
            ->unique()
            ->values();

        $requestedDays = filter_var($days, FILTER_VALIDATE_INT) ?: null;
        $days = $requestedDays ? $availableRanges->first(fn($range) => $range === $requestedDays) : null;
        try {
            $items = MarketItem::with('latestPrice')
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();
            $lastFetch = FetchLog::latest('finished_at')->first();
        } catch (Throwable $exception) {
            Log::error('Price page query failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $items = collect();
            $lastFetch = null;
        }

        return view('app', [
            'seo' => $this->seoPayload($items, $availableRanges, $days, $lastFetch),
            'seoItems' => $items,
        ]);
    }

    private function rangeDays($range): int
    {
        $value = strtolower(trim((string)$range));
        if (str_ends_with($value, 'h')) {
            return 1;
        }

        return max(1, (int)$value);
    }

    private function seoPayload(Collection $items, Collection $availableRanges, ?int $days, ?FetchLog $lastFetch): array
    {
        $primaryGold = $items->first(fn($item) => str_contains($item->name, '۱۸') || str_contains($item->name, '18'))
            ?: $items->firstWhere('category', 'gold');
        $primaryCoin = $items->firstWhere('category', 'coin');
        $goldPrice = $this->formatDisplayPrice($primaryGold, $primaryGold?->latestPrice?->current_value);
        $coinPrice = $this->formatDisplayPrice($primaryCoin, $primaryCoin?->latestPrice?->current_value);
        $canonical = url($days ? "/price/trends/{$days}" : '/price/');
        $updatedAt = optional($lastFetch?->finished_at ?: $items->pluck('latestPrice.fetched_at')->filter()->max())->toIso8601String();
        $title = $days
            ? "نمودار {$days} روزه قیمت طلا و سکه | قیمت لحظه‌ای بازار ایران"
            : 'قیمت طلا امروز و قیمت لحظه‌ای سکه | داشبورد بازار ایران';
        $description = $days
            ? "بررسی روند {$days} روزه قیمت طلا و سکه با داده‌های تاریخی، نمودار تعاملی و آخرین قیمت‌های ثبت‌شده بازار ایران."
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
            'alternateRanges' => $availableRanges->map(fn($range) => [
                'days' => $range,
                'url' => url("/price/trends/{$range}"),
                'title' => "روند {$range} روزه قیمت طلا و سکه",
            ])->all(),
            'jsonLd' => $this->jsonLd($items, $availableRanges, $days, $updatedAt, $goldPrice, $coinPrice),
        ];
    }

    private function formatDisplayPrice(?MarketItem $item, $value): string
    {
        if ($value === null) {
            return 'نامشخص';
        }

        if ($this->isUsdItem($item)) {
            return number_format((float)$value, 2, '.', ',') . ' دلار';
        }

        return number_format((float)$value, 0, '.', ',') . ' تومان';
    }

    private function isUsdItem(?MarketItem $item): bool
    {
        return $item && (str_contains($item->name, 'انس') || strtoupper((string)$item->currency) === 'USD');
    }

    private function jsonLd(Collection $items, Collection $availableRanges, ?int $days, ?string $updatedAt, string $goldPrice, string $coinPrice): array
    {
        $pageUrl = url($days ? "/price/trends/{$days}" : '/price/');
        $pageName = $days
            ? "نمودار {$days} روزه قیمت طلا و سکه"
            : 'قیمت طلا امروز و قیمت لحظه‌ای سکه';

        $marketList = [
            '@type' => 'ItemList',
            '@id' => url('/price/#market-items'),
            'name' => 'قیمت لحظه‌ای طلا و سکه ایران',
            'numberOfItems' => $items->count(),
            'itemListElement' => $items->values()->map(function (MarketItem $item, int $index) use ($updatedAt, $pageUrl) {
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
                            'price' => $this->schemaNumber($price?->current_value),
                            'priceCurrency' => $this->isUsdItem($item) ? 'USD' : 'IRR',
                            'availability' => 'https://schema.org/InStock',
                            'url' => $pageUrl,
                            'priceValidUntil' => now()->addDay()->toDateString(),
                        ],
                        'additionalProperty' => [
                            ['@type' => 'PropertyValue', 'name' => 'changePercent', 'value' => $this->schemaNumber($price?->change_percent), 'unitText' => 'PERCENT'],
                            ['@type' => 'PropertyValue', 'name' => 'lastUpdated', 'value' => $price?->fetched_at?->toIso8601String() ?: $updatedAt],
                        ],
                    ],
                ];
            })->all(),
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
            'distribution' => $availableRanges->map(fn($range) => [
                '@type' => 'DataDownload',
                'encodingFormat' => 'text/html',
                'name' => "تاریخچه {$range} روزه قیمت طلا و سکه",
                'contentUrl' => url("/price/trends/{$range}"),
            ])->all(),
        ];

        $faq = null;
        if (!$days && $items->isNotEmpty()) {
            $sourceName = config('gold.source_name');
            $faq = [
                '@type' => 'FAQPage',
                '@id' => url('/price/#faq'),
                'mainEntity' => array_values(array_filter([
                    [
                        '@type' => 'Question',
                        'name' => 'قیمت طلای ۱۸ عیار امروز چقدر است؟',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => "بر اساس آخرین داده ثبت‌شده از {$sourceName}، قیمت طلای ۱۸ عیار: {$goldPrice}. این عدد فقط برای اطلاع‌رسانی است و ممکن است با قیمت فروشگاه متفاوت باشد.",
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'قیمت سکه امروز چقدر است؟',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => "بر اساس آخرین داده ثبت‌شده از {$sourceName}، قیمت سکه: {$coinPrice}. برای جزئیات بیشتر به داشبورد قیمت زنده مراجعه کنید.",
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'منبع قیمت طلا در این سایت چیست؟',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => "قیمت‌ها از {$sourceName} (estjt.ir) دریافت و به‌صورت دوره‌ای به‌روزرسانی می‌شوند. این سایت مرجع رسمی اعلام قیمت طلا و سکه در تهران است.",
                        ],
                    ],
                ])),
            ];
        }

        $breadcrumbItems = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'قیمت طلا و سکه', 'item' => url('/price/')],
        ];
        if ($days) {
            $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => "روند {$days} روزه", 'item' => $pageUrl];
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
                'mainEntity' => $days ? ['@id' => url('/price/#historical-price-dataset')] : ['@id' => url('/price/#market-items')],
                'speakable' => [
                    '@type' => 'SpeakableSpecification',
                    'cssSelector' => ['h1', '.hero p', '.marketItem small'],
                ],
            ],
            $dataset,
            $marketList,
        ];

        if ($faq) {
            $graph[] = $faq;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function schemaNumber($value): ?float
    {
        return $value === null ? null : (float)$value;
    }
}
