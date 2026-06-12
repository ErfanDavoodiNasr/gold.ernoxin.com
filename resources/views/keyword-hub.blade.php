<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <meta name="description" content="{{ $seo['description'] }}">
    @if(!empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <link rel="alternate" hreflang="fa-IR" href="{{ $seo['canonical'] }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] }}">
    <link rel="preload" href="/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <meta property="og:locale" content="fa_IR">
    <meta property="og:site_name" content="Ernoxin Gold">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['ogImage'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $hub['h1'] }} — Ernoxin Gold">
    @if(!empty($seo['updatedAt']))
    <meta property="article:modified_time" content="{{ $seo['updatedAt'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title>{{ $seo['title'] }}</title>
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
    @endif
    <style>
        @font-face{font-family:Vazirmatn;src:url('/fonts/Vazirmatn-Regular.woff2') format('woff2');font-weight:100 900;font-style:normal;font-display:swap}
        :root{color-scheme:light;--bg:#f5f7f8;--surface:#fff;--surface-soft:#eef3f5;--text:#182027;--muted:#5c6873;--line:#d7e0e5;--accent:#a87520;--blue:#2368a2}
        :root[data-theme=dark]{color-scheme:dark;--bg:#080b10;--surface:#111821;--surface-soft:#17212d;--text:#f8fafc;--muted:#9aa7b4;--line:#263241;--accent:#d9a441;--blue:#62a8ff}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Vazirmatn,Tahoma,sans-serif;line-height:1.9}
        a{color:var(--blue);text-decoration:none}.shell{width:min(1180px,100%);margin:auto;padding:22px}
        .top{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px}
        .nav{display:flex;gap:10px;flex-wrap:wrap}.nav a{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:8px 12px;color:var(--text)}
        .hero{padding:28px 0;border-bottom:1px solid var(--line)}.eyebrow{color:var(--accent);font-size:14px;font-weight:750}
        h1{margin:8px 0 12px;font-size:clamp(30px,5vw,46px);line-height:1.35}h2{font-size:24px;margin:34px 0 12px}
        .lead{font-size:18px;color:var(--muted);max-width:860px}.meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;color:var(--muted);font-size:14px}
        .pill{border:1px solid var(--line);border-radius:999px;background:var(--surface);padding:6px 10px}
        .priceGrid,.articleGrid,.hubNav{display:grid;gap:12px}.priceGrid{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-top:18px}
        .articleGrid{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}.hubNav{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-top:18px}
        .card,.priceCard{border:1px solid var(--line);background:var(--surface);border-radius:12px;padding:16px}
        .priceCard strong{display:block;font-size:22px;margin:8px 0}.priceCard small{color:var(--muted)}
        .faq details{border:1px solid var(--line);background:var(--surface);border-radius:12px;padding:14px;margin-top:10px}
        .faq summary{cursor:pointer;font-weight:800}.cta{display:inline-block;margin-top:18px;border:1px solid var(--accent);border-radius:10px;background:var(--accent);color:#fff;padding:10px 16px;font-weight:800}
        footer{border-top:1px solid var(--line);margin-top:36px;padding-top:16px;color:var(--muted);font-size:14px}
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <a href="/price/" style="color:var(--text);font-weight:850;font-size:18px;text-decoration:none">سکه و طلای ارنوکسین</a>
        <nav class="nav" aria-label="ناوبری">
            <a href="/price/">قیمت زنده</a>
            <a href="{{ $blogPath }}">بلاگ</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <span class="eyebrow">صفحه تخصصی SEO</span>
            <h1>{{ $hub['h1'] }}</h1>
            <p class="lead">{{ $hub['intro'] }}</p>
            <div class="meta">
                @if($primaryPrice !== 'نامشخص')
                <span class="pill">آخرین قیمت: {{ $primaryPrice }}</span>
                @endif
                @if(!empty($updatedAt))
                <span class="pill">به‌روزرسانی: {{ $updatedAt }}</span>
                @endif
            </div>
            <a class="cta" href="/price/">مشاهده داشبورد کامل قیمت</a>
        </section>

        @if($featuredItems->isNotEmpty())
        <section>
            <h2>قیمت‌های مرتبط</h2>
            <p class="lead" style="font-size:15px;margin-bottom:12px">داده‌های زنده از اتحادیه طلا تهران — {{ $featuredItems->count() }} نماد مرتبط با این صفحه.</p>
            <div class="priceGrid">
                @foreach($featuredItems as $item)
                <article class="priceCard" id="item-{{ $item->id }}">
                    <small>{{ $item->category === 'coin' ? 'سکه' : 'طلا' }}</small>
                    <strong>{{ $item->name }}</strong>
                    <strong>{{ number_format((float)($item->latestPrice?->current_value ?? 0), $item->currency === 'USD' ? 2 : 0, '.', ',') }} {{ $item->currency === 'USD' ? 'دلار' : 'تومان' }}</strong>
                    <small>تغییر: {{ $item->latestPrice?->change_percent ?? '—' }}٪</small>
                </article>
                @endforeach
            </div>
        </section>
        @endif

        <section>
            <h2>صفحات کلیدی دیگر</h2>
            <div class="hubNav">
                <a class="card" href="/price/mozaneh">قیمت مظنه امروز</a>
                <a class="card" href="/price/coin-bubble">حباب سکه امروز</a>
                <a class="card" href="/price/ounce">قیمت انس جهانی</a>
                <a class="card" href="/price/trends/30">روند ۳۰ روزه</a>
            </div>
        </section>

        @if(!empty($hub['articles']))
        <section>
            <h2>مقالات پیشنهادی</h2>
            <div class="articleGrid">
                @foreach($hub['articles'] as $slug => $title)
                <a class="card" href="{{ $blogPath }}/{{ $slug }}">
                    <strong>{{ $title }}</strong>
                    <small>مطالعه راهنمای کامل</small>
                </a>
                @endforeach
            </div>
        </section>
        @endif

        @if(!empty($hub['faqs']))
        <section class="faq" id="faq">
            <h2>سوالات پرتکرار</h2>
            @foreach($hub['faqs'] as $faq)
            <details @if($loop->first) open @endif>
                <summary>{{ $faq['question'] }}</summary>
                <p>{{ str_replace('{price}', $primaryPrice, $faq['answer']) }}</p>
            </details>
            @endforeach
        </section>
        @endif
    </main>

    <footer>
        <p>داده‌ها از منبع رسمی اتحادیه طلا تهران دریافت می‌شوند. این صفحات آموزشی و اطلاع‌رسانی هستند، نه مشاوره سرمایه‌گذاری.</p>
    </footer>
</div>
</body>
</html>
