<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $blogPath = config('learn.base_path', '/blog');
        $staticUrls = [
            ['loc' => url('/price/'), 'changefreq' => 'hourly', 'priority' => '1.0'],
            ['loc' => url('/price/trends/7'), 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => url('/price/trends/30'), 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => url('/price/trends/90'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => url($blogPath), 'lastmod' => config('learn.reviewed_at_iso'), 'changefreq' => 'monthly', 'priority' => '0.8'],
        ];

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
}
