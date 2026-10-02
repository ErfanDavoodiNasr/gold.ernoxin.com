<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="robots"
          content="{{ $seo['robots'] ?? 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1' }}">
    <meta name="description" content="{{ $seo['description'] }}">
    @if(!empty($seo['keywords']))
    <meta name="keywords"
          content="{{ is_array($seo['keywords']) ? implode(',', $seo['keywords']) : $seo['keywords'] }}">
    @endif
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <link rel="alternate" hreflang="fa-IR" href="{{ $seo['canonical'] }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] }}">
    <link rel="alternate" type="application/rss+xml" title="بلاگ طلا و سکه ارنوکسین"
          href="{{ url(config('learn.base_path', '/blog') . '/feed.xml') }}">
    <link rel="preload" href="/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/Vazirmatn-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <meta name="author" content="Ernoxin Gold">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="{{ $seo['type'] ?? 'article' }}">
    <meta property="og:site_name" content="Ernoxin Gold">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    @isset($page)
    <meta property="article:section" content="{{ $page['category'] ?? 'آموزش طلا و سکه' }}">
    @endisset
    @if(!empty($seo['publishedTime']))
    <meta property="article:published_time" content="{{ $seo['publishedTime'] }}">
    @endif
    @if(!empty($seo['modifiedTime']))
    <meta property="article:modified_time" content="{{ $seo['modifiedTime'] }}">
    @endif
    @if(!empty($seo['ogImage']))
    <meta property="og:image" content="{{ $seo['ogImage'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <title>{{ $seo['title'] }}</title>
    @include('components.theme-bootstrap')
    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">{
            !!
            json_encode($seo[
            'jsonLd'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!
        }</script>
    @endif
    @php($manifestPath = public_path('build/manifest.json'))
    @php($manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null)
    @php($learnCss = $manifest['resources/css/learn.css']['file'] ?? null)
    @if($learnCss)
    <link rel="stylesheet" href="{{ asset('build/'.$learnCss) }}">
    @endif
</head>
<body id="top">
<div class="learnShell">
    <header class="learnTop">
        <a class="brand" href="/price/">سکه و طلای ارنوکسین</a>
        <nav class="nav" aria-label="ناوبری اصلی">
            <a href="/price/">قیمت زنده</a>
            <a href="{{ config('learn.base_path', '/blog') }}">بلاگ</a>
        </nav>
    </header>
    @yield('content')
    <footer>
        <p>ارنوکسین گلد؛ راهنمای ساده و قابل پیگیری برای خواندن بازار طلا و سکه.</p>
        <p><a href="/price/">قیمت زنده</a> · <a href="/price/mozaneh">مظنه</a> · <a href="/price/coin-bubble">حباب
                سکه</a> · <a href="/price/ounce">انس</a> · <a href="{{ config('learn.base_path', '/blog') }}/feed.xml">RSS</a>
            · <a href="/llms.txt">راهنمای AI</a></p>
    </footer>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" crossorigin="anonymous"
        onload="renderMathInElement(document.body,{delimiters:[{left:'$$',right:'$$',display:true},{left:'\\(',right:'\\)',display:false}],throwOnError:false});"></script>
</body>
</html>
