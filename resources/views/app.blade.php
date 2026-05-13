<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    <meta name="description" content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <link rel="canonical" href="{{ $seo['canonical'] ?? url('/price/') }}">
    @foreach(($seo['alternateRanges'] ?? []) as $range)
    <link rel="alternate" href="{{ $range['url'] }}" title="{{ $range['title'] }}">
    @endforeach
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}">
    <meta property="og:description" content="{{ $seo['description'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران همراه با نمودار و تاریخچه تغییرات.' }}">
    <meta property="og:url" content="{{ $seo['canonical'] ?? url('/price/') }}">
    <meta name="twitter:card" content="summary">
    <title>{{ $seo['title'] ?? 'قیمت طلا امروز و قیمت لحظه‌ای سکه' }}</title>
    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
    @endif
    @php($manifestPath = public_path('build/manifest.json'))
    @if(file_exists($manifestPath))
    @php($manifest = json_decode(file_get_contents($manifestPath), true))
    @foreach(($manifest['resources/js/App.jsx']['css'] ?? []) as $css)
    <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
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
                <th>قیمت فعلی (ریال)</th>
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
<div id="root"></div>
<?php
    $learnExtras = config('learn_extras', []);
    $learnDefaults = $learnExtras['defaults'] ?? [];
    unset($learnExtras['defaults']);
    $learnPages = collect(config('learn.pages', []))
        ->map(fn($page, $slug) => array_merge($learnDefaults, $page, $learnExtras[$slug] ?? [], ['slug' => $slug]));
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
            <span class="eyebrow">مطالب آموزشی بازار طلا</span>
            <h2>راهنمای خرید و قیمت طلا</h2>
            <p>مقاله‌های آموزشی برای فهم قیمت طلا، سکه، عیار، اجرت و ریسک‌های خرید. قیمت‌های روز را در داشبورد زنده ببینید و مفاهیم را اینجا دقیق‌تر بخوانید.</p>
        </div>
        <a class="learnCta" href="/learn">مشاهده همه آموزش‌ها</a>
    </div>

    <div class="articleGrid">
        @foreach($featuredLearn as $page)
            <article class="articleCard">
                <span>{{ $page['category'] ?? 'آموزش طلا' }}</span>
                <h3><a href="/learn/{{ $page['slug'] }}">{{ $page['title'] }}</a></h3>
                <p>{{ $page['quick_summary'] ?? $page['meta_description'] }}</p>
                <div class="articleMeta">
                    <small>{{ $page['reading_time'] ?? '۶ دقیقه' }}</small>
                    <a href="/learn/{{ $page['slug'] }}">بیشتر بخوانید</a>
                </div>
            </article>
        @endforeach
    </div>

    <div class="learnSplit">
        <section class="learnPanel">
            <h2>آخرین راهنماهای آموزشی</h2>
            <div class="compactLinks">
                @foreach($latestLearn as $page)
                    <a href="/learn/{{ $page['slug'] }}">
                        <strong>{{ $page['title'] }}</strong>
                        <small>{{ $page['category'] ?? 'آموزش' }} · {{ $page['reading_time'] ?? '۶ دقیقه' }}</small>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="learnPanel">
            <h2>سوالات پرتکرار</h2>
            <div class="faqPreview">
                <a href="/learn/gold-bubble#faq">حباب سکه چیست و چرا تغییر می‌کند؟</a>
                <a href="/learn/18k-gold#faq">طلای ۱۸ عیار یعنی طلای خالص؟</a>
                <a href="/learn/gold-price-calculation#faq">قیمت طلا چگونه محاسبه می‌شود؟</a>
                <a href="/learn/buying-gold-safely#faq">قبل از خرید طلا چه چیزهایی را بررسی کنیم؟</a>
            </div>
        </section>
    </div>
</section>
</body>
</html>
