<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $blogPath = config('learn.base_path', '/blog');
        $trendUrls = collect(config('gold.chart_available_ranges', ['1d', '7d', '30d', '90d', '180d', '365d']))
            ->map(fn($range) => $this->rangeDays($range))
            ->filter(fn($days) => $days >= 7 && $days <= (int)config('gold.history_max_days', 365))
            ->unique()
            ->sort()
            ->values()
            ->map(fn($days) => [
                'loc' => url("/price/trends/{$days}"),
                'changefreq' => $days <= 30 ? 'daily' : 'weekly',
                'priority' => $days <= 30 ? '0.8' : '0.7',
            ])
            ->all();

        $blogPath = config('learn.base_path', '/blog');
        $hubUrls = collect(config('seo_hubs.hubs', []))->map(fn($hub) => [
            'loc' => url($hub['path']),
            'lastmod' => config('learn.reviewed_at_iso'),
            'changefreq' => 'hourly',
            'priority' => '0.9',
        ])->values()->all();

        $staticUrls = array_merge([
            ['loc' => url('/price/'), 'changefreq' => 'hourly', 'priority' => '1.0'],
            ['loc' => url($blogPath), 'lastmod' => config('learn.reviewed_at_iso'), 'changefreq' => 'weekly', 'priority' => '0.8'],
        ], $hubUrls, $trendUrls);

        $articleUrls = collect(config('learn.pages', []))->keys()->map(fn($slug) => [
            'loc' => url("{$blogPath}/{$slug}"),
            'lastmod' => config('learn.reviewed_at_iso'),
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ])->all();

        return response()
            ->view('sitemap', ['urls' => array_merge($staticUrls, $articleUrls)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function rangeDays($range): int
    {
        $value = strtolower(trim((string)$range));
        if (str_ends_with($value, 'h')) {
            return 1;
        }

        return max(1, (int)$value);
    }
}
