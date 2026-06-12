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
    <link rel="alternate" type="application/rss+xml" title="بلاگ طلا و سکه ارنوکسین"
          href="{{ url(config('learn.base_path', '/blog')) }}">
    <link rel="preload" href="/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
    @foreach(($seo['alternateRanges'] ?? []) as $range)
    <link rel="alternate" href="{{ $range['url'] }}" title="{{ $range['title'] }}">
    @endforeach
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}">
    <meta property="og:description"
          content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <meta property="og:url" content="{{ $seo['canonical'] ?? url('/price/') }}">
    <meta property="og:image" content="{{ url(config('learn.default_og_image')) }}">
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
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">{
            !!
            json_encode($seo[
            'jsonLd'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!
        }</script>
    @endif
    @php($manifestPath = public_path('build/manifest.json'))
    @if(file_exists($manifestPath))
    @php($manifest = json_decode(file_get_contents($manifestPath), true))
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
                <td>{{ number_format((float)($item->latestPrice?->current_value ?? 0), 0, '.', ',') }}</td>
                <td>{{ $item->latestPrice?->change_percent ?? '—' }}٪</td>
                <td>{{ optional($item->latestPrice?->fetched_at)->toIso8601String() ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </main>
</noscript>
<main class="seoAppFallback shell" aria-label="خلاصه سریع بازار طلا و سکه">
    <header class="topbar">
        <div class="brand">
            <span class="logo">Au</span>
            <div>
                <strong class="brandTitle">سکه و طلای ارنوکسین</strong>
                <p>قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران</p>
            </div>
        </div>
    </header>
    <section class="hero">
        <div>
            <span class="eyebrow">داشبورد زنده بازار</span>
            <h1>قیمت طلا امروز و قیمت لحظه‌ای سکه</h1>
            <p>{{ $seo['description'] ?? 'آخرین قیمت‌های بازار طلا و سکه ایران همراه با نمودار تعاملی و تاریخچه
                تغییرات.' }}</p>
        </div>
        <div class="stats">
            <div class="metric"><strong>{{ count($seoItems ?? []) }}</strong><span>نماد فعال</span></div>
        </div>
    </section>
    @if(!empty($seoItems) && count($seoItems) > 0)
    <section class="marketPanel" style="width:auto;flex:auto;margin-top:16px">
        <div class="panelTitle">
            <h2>آخرین قیمت‌های ثبت‌شده</h2>
        </div>
        <div class="itemList">
            @foreach(collect($seoItems)->take(6) as $item)
            <div class="marketItem">
                <span class="itemIcon">{{ $item->category === 'coin' ? 'س' : 'ط' }}</span>
                <span class="itemMain">
                            <b>{{ $item->name }}</b>
                            <small>{{ number_format((float)($item->latestPrice?->current_value ?? 0), 0, '.', ',') }} {{ $item->currency === 'USD' ? 'دلار' : 'تومان' }}</small>
                        </span>
                <span class="badge {{ $item->latestPrice?->direction === 'desc' ? 'down' : 'up' }}">{{ $item->latestPrice?->change_percent ?? '—' }}٪</span>
            </div>
            @endforeach
        </div>
    </section>
    @endif
</main>
<div id="root"></div>
<?php
$learnExtras = config('learn_extras', []);
$learnDefaults = $learnExtras['defaults'] ?? [];
unset($learnExtras['defaults']);
$learnPages = collect(config('learn.pages', []))
        ->map(fn($page, $slug) => array_merge($learnDefaults, $page, $learnExtras[$slug] ?? [], ['slug' => $slug]));
$blogPath = config('learn.base_path', '/blog');
$featuredLearn = $learnPages->only([
        'gold-price-guide',
        'how-gold-price-is-set',
        '18k-gold',
        'gold-coin-guide',
        'gold-bubble',
        'buying-gold-safely',
]);
$latestLearn = $learnPages->only([
        'gold-price-calculation',
        'gold-making-charge',
        'gold-vat',
        'online-gold-buying-risks',
]);
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

    <div class="learnSplit">
        <section class="learnPanel">
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

        <section class="learnPanel">
            <h2>سوالات پرتکرار</h2>
            <div class="faqPreview">
                <a href="{{ $blogPath }}/gold-bubble#faq">حباب سکه چیست و چرا تغییر می‌کند؟</a>
                <a href="{{ $blogPath }}/18k-gold#faq">طلای ۱۸ عیار یعنی طلای خالص؟</a>
                <a href="{{ $blogPath }}/gold-price-calculation#faq">قیمت طلا چگونه محاسبه می‌شود؟</a>
                <a href="{{ $blogPath }}/buying-gold-safely#faq">قبل از خرید طلا چه چیزهایی را بررسی کنیم؟</a>
            </div>
        </section>
    </div>
</section>
</body>
</html>
