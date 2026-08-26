<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    <meta name="description"
          content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <meta name="keywords"
          content="{{ $seo['keywords'] ?? 'قیمت طلا امروز,قیمت سکه,قیمت طلای ۱۸ عیار,نمودار قیمت طلا,بازار طلا ایران' }}">
    <link rel="canonical" href="{{ $seo['canonical'] ?? url('/price/') }}">
    <link rel="alternate" hreflang="fa-IR" href="{{ $seo['canonical'] ?? url('/price/') }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] ?? url('/price/') }}">
    <link rel="alternate" type="application/rss+xml" title="بلاگ طلا و سکه ارنوکسین"
          href="{{ url(config('learn.base_path', '/blog') . '/feed.xml') }}">
    <link rel="preload" href="/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/Vazirmatn-Bold.woff2" as="font" type="font/woff2" crossorigin>
    @php($manifestPath = public_path('build/manifest.json'))
    @php($manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null)
    @if($manifest)
    @php($appCss = $manifest['resources/js/App.jsx']['css'][0] ?? null)
    @if($appCss)
    <link rel="preload" href="{{ asset('build/'.$appCss) }}" as="style">
    @endif
    @endif
    <script>document.documentElement.classList.add('js')</script>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7f8;
            --text: #182027;
            --muted: #5c6873;
            --accent: #a87520
        }

        :root[data-theme=dark] {
            color-scheme: dark;
            --bg: #080b10;
            --text: #f8fafc;
            --muted: #9aa7b4;
            --accent: #d9a441
        }

        html {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text)
        }

        body {
            margin: 0
        }

        .seoAppFallback {
            width: min(1180px, 100%);
            margin: auto;
            padding: 22px
        }

        .seoAppFallback h1 {
            margin: 8px 0 12px;
            font-size: clamp(28px, 5vw, 42px);
            line-height: 1.35
        }

        .seoAppFallback p {
            color: var(--muted)
        }

        .seoAppFallback ul {
            padding-right: 18px;
            margin: 12px 0 0;
            color: var(--muted)
        }

        /* Hide duplicate fallback as soon as JS runs — before React paints. */
        html.js .seoAppFallback,
        body.appReady .seoAppFallback {
            display: none !important
        }

        .homeLearn {
            content-visibility: auto;
            contain-intrinsic-size: auto 320px
        }
    </style>
    @foreach(($seo['alternateRanges'] ?? []) as $range)
    <link rel="alternate" href="{{ $range['url'] }}" title="{{ $range['title'] }}">
    @endforeach
    <meta property="og:locale" content="fa_IR">
    <meta property="og:site_name" content="Ernoxin Gold">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}">
    <meta property="og:description"
          content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <meta property="og:url" content="{{ $seo['canonical'] ?? url('/price/') }}">
    <meta property="og:image"
          content="{{ $seo['ogImage'] ?? url(config('learn.price_og_image', config('learn.default_og_image'))) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="قیمت طلا امروز و قیمت لحظه‌ای سکه — Ernoxin Gold">
    @if(!empty($seo['updatedAt']))
    <meta property="article:modified_time" content="{{ $seo['updatedAt'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}">
    <meta name="twitter:description"
          content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <title>{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}</title>
    @include('components.theme-bootstrap')
    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">{
            !!
            json_encode($seo[
            'jsonLd'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!
        }</script>
    @endif
    @if($manifest)
    @foreach(($manifest['resources/js/App.jsx']['css'] ?? []) as $css)
    <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
    @endforeach
    @foreach(($manifest['resources/js/App.jsx']['imports'] ?? []) as $import)
    @if(!empty($manifest[$import]['file']))
    <link rel="modulepreload" href="{{ asset('build/'.$manifest[$import]['file']) }}">
    @endif
    @endforeach
    <script type="module" src="{{ asset('build/'.$manifest['resources/js/App.jsx']['file']) }}" defer></script>
    @endif
</head>
<body>
<noscript>
    <main class="seoFallback">
        <h1>قیمت طلا امروز و قیمت لحظه‌ای سکه</h1>
        <p>{{ $seo['description'] ?? 'آخرین قیمت‌های ثبت‌شده بازار طلا و سکه ایران.' }}</p>
        <table>
            <caption>آخرین قیمت طلا و سکه</caption>
            <thead>
            <tr>
                <th>نام بازار</th>
                <th>قیمت فعلی</th>
                <th>تغییر</th>
                <th>آخرین به‌روزرسانی</th>
            </tr>
            </thead>
            <tbody>
            @foreach(($seoItems ?? []) as $item)
            <tr id="item-{{ $item->id }}">
                <th>{{ $item->name }}</th>
                <td>@php($price = $item->latestPrice?->current_value)@if($price !== null && (float)$price > 0){{
                    number_format((float)$price, 0, '.', ',') }}@else—@endif
                </td>
                <td>{{ $item->latestPrice?->change_percent !== null ? abs($item->latestPrice->change_percent) : '—'
                    }}٪
                </td>
                <td>{{ optional($item->latestPrice?->fetched_at)->toIso8601String() ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </main>
</noscript>
<main class="seoAppFallback" aria-label="خلاصه سریع بازار طلا و سکه">
    <h1>قیمت طلا امروز و قیمت لحظه‌ای سکه</h1>
    <p>{{ $seo['description'] ?? 'آخرین قیمت‌های بازار طلا و سکه ایران همراه با نمودار تعاملی و تاریخچه تغییرات.' }}</p>
    @if(!empty($seoItems) && count($seoItems) > 0)
    <ul>
        @foreach(collect($seoItems)->take(6) as $item)
        @php($price = $item->latestPrice?->current_value)
        <li>{{ $item->name }}:
            @if($price !== null && (float)$price > 0){{ number_format((float)$price, $item->isUsd() ? 2 : 0, '.', ',')
            }} {{ $item->isUsd() ? 'دلار' : 'تومان' }}@else—@endif
        </li>
        @endforeach
    </ul>
    @endif
</main>
@if(!empty($marketSummary))
<script type="application/json" id="market-summary">{
        !!
        json_encode($marketSummary,
        JSON_UNESCAPED_UNICODE
        |
        JSON_UNESCAPED_SLASHES)
        !!
    }</script>
@endif
<div id="root"></div>
<?php
$blogPath = config('learn.base_path', '/blog');
$homeCards = config('learn_home_cards', []);
$featuredLearn = collect($homeCards['featured'] ?? [])
        ->map(fn($page, $slug) => array_merge($page, ['slug' => $slug]));
$latestLearn = collect($homeCards['latest'] ?? [])
        ->map(fn($page, $slug) => array_merge($page, ['slug' => $slug]));
?>
<section class="homeLearn shell" aria-label="مطالب آموزشی بازار طلا">
    <div class="homeLearnHeader">
        <div>
            <span class="eyebrow">بلاگ بازار طلا</span>
            <h2>راهنمای خرید و قیمت طلا</h2>
            <p>مقاله‌های کاربردی برای فهم قیمت طلا، سکه، عیار، اجرت، فاکتور و ریسک‌های خرید. قیمت‌های روز را در داشبورد
                زنده ببینید و پاسخ کامل‌تر را در بلاگ بخوانید.</p>
        </div>
        <a class="learnCta" href="{{ $blogPath }}">مشاهده همه مقاله‌ها</a>
    </div>

    <div class="articleGrid">
        @foreach($featuredLearn as $page)
        <article class="articleCard">
            <span>{{ $page['category'] ?? 'آموزش طلا' }}</span>
            <h3><a href="{{ $blogPath }}/{{ $page['slug'] }}">{{ $page['title'] }}</a></h3>
            <p>{{ $page['quick_summary'] ?? $page['meta_description'] }}</p>
            <div class="articleMeta">
                <small>{{ $page['reading_time'] ?? '۶ دقیقه' }}</small>
                <a href="{{ $blogPath }}/{{ $page['slug'] }}">بیشتر بخوانید</a>
            </div>
        </article>
        @endforeach
    </div>

    <section class="learnPanel latestGuides">
        <h2>آخرین راهنماهای آموزشی</h2>
        <div class="compactLinks">
            @foreach($latestLearn as $page)
            <a href="{{ $blogPath }}/{{ $page['slug'] }}">
                <strong>{{ $page['title'] }}</strong>
                <small>{{ $page['category'] ?? 'آموزش' }} · {{ $page['reading_time'] ?? '۶ دقیقه' }}</small>
            </a>
            @endforeach
        </div>
    </section>
</section>
</body>
</html>
