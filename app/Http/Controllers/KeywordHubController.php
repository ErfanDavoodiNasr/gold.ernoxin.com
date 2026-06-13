<?php

namespace App\Http\Controllers;

use App\Models\MarketItem;
use App\Services\MarketSummaryService;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class KeywordHubController extends Controller
{
    public function __construct(private MarketSummaryService $summaryService)
    {
    }

    public function __invoke(string $hub): Response
    {
        $config = config("seo_hubs.hubs.{$hub}");
        abort_unless($config, 404);

        try {
            $items = $this->summaryService->items();
            $lastFetch = $this->summaryService->lastFetch();
        } catch (Throwable $exception) {
            Log::error('Keyword hub query failed', [
                'hub' => $hub,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $items = collect();
            $lastFetch = null;
        }

        $featuredItems = $this->featuredItems($items, $config);
        $primaryItem = $featuredItems->first();
        $primaryPrice = $this->formatDisplayPrice($primaryItem, $primaryItem?->latestPrice?->current_value);
        $updatedAt = optional($lastFetch?->finished_at ?: $items->pluck('latestPrice.fetched_at')->filter()->max())->toIso8601String();
        $canonical = url($config['path']);
        $blogPath = config('learn.base_path', '/blog');

        $description = $primaryItem
            ? str_replace('{price}', $primaryPrice, "{$config['description']} آخرین قیمت: {$primaryPrice}.")
            : $config['description'];

        return response()
            ->view('keyword-hub', [
                'hub' => $config,
                'hubKey' => $hub,
                'featuredItems' => $featuredItems,
                'primaryPrice' => $primaryPrice,
                'blogPath' => $blogPath,
                'updatedAt' => $updatedAt,
                'seo' => [
                    'title' => $config['meta_title'] ?? $config['title'],
                    'description' => $description,
                    'canonical' => $canonical,
                    'keywords' => implode(',', $config['keywords'] ?? []),
                    'robots' => 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1',
                    'ogImage' => url($config['og_image'] ?? config('learn.default_og_image')),
                    'updatedAt' => $updatedAt,
                    'jsonLd' => $this->jsonLd($config, $canonical, $featuredItems, $primaryPrice, $updatedAt, $blogPath),
                ],
            ])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=120, stale-while-revalidate=300');
    }

    private function featuredItems(Collection $items, array $config): Collection
    {
        $patterns = $config['item_patterns'] ?? [];
        $category = $config['category'] ?? null;

        $matched = $items->filter(function (MarketItem $item) use ($patterns, $category) {
            if ($category && $item->category !== $category) {
                return false;
            }

            foreach ($patterns as $pattern) {
                if (str_contains($item->name, $pattern)) {
                    return true;
                }
            }

            return false;
        });

        return $matched->isNotEmpty() ? $matched->values() : $items->take(4);
    }

    private function formatDisplayPrice(?MarketItem $item, $value): string
    {
        if ($value === null) {
            return 'نامشخص';
        }

        if ($item && (str_contains($item->name, 'انس') || strtoupper((string)$item->currency) === 'USD')) {
            return number_format((float)$value, 2, '.', ',') . ' دلار';
        }

        return number_format((float)$value, 0, '.', ',') . ' تومان';
    }

    private function jsonLd(array $config, string $canonical, Collection $items, string $primaryPrice, ?string $updatedAt, string $blogPath): array
    {
        $faqEntities = collect($config['faqs'] ?? [])->map(function ($faq) use ($primaryPrice) {
            $answer = str_replace('{price}', $primaryPrice, $faq['answer']);

            return [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        })->all();

        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical . '#webpage',
                    'url' => $canonical,
                    'name' => $config['title'],
                    'description' => $config['description'],
                    'inLanguage' => 'fa-IR',
                    'dateModified' => $updatedAt,
                    'isPartOf' => ['@id' => url('/#website')],
                    'breadcrumb' => [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => url('/')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'قیمت طلا', 'item' => url('/price/')],
                            ['@type' => 'ListItem', 'position' => 3, 'name' => $config['h1'], 'item' => $canonical],
                        ],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => $canonical . '#items',
                    'name' => $config['h1'],
                    'numberOfItems' => $items->count(),
                    'itemListElement' => $items->values()->map(fn(MarketItem $item, int $index) => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $item->name,
                        'url' => $canonical . '#item-' . $item->id,
                    ])->all(),
                ],
                $faqEntities ? [
                    '@type' => 'FAQPage',
                    '@id' => $canonical . '#faq',
                    'mainEntity' => $faqEntities,
                ] : null,
                [
                    '@type' => 'ItemList',
                    '@id' => $canonical . '#articles',
                    'name' => 'مقالات مرتبط',
                    'itemListElement' => collect($config['articles'] ?? [])
                        ->map(fn($title, $slug) => ['title' => $title, 'slug' => $slug])
                        ->values()
                        ->map(fn(array $item, int $index) => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $item['title'],
                            'url' => url("{$blogPath}/{$item['slug']}"),
                        ])->all(),
                ],
            ])),
        ];
    }
}
