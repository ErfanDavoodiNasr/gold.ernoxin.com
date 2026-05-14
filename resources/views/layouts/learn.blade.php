<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    @if(!empty($seo['ogImage']))
    <meta property="og:image" content="{{ $seo['ogImage'] }}">
    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">
    @endif
    <meta name="twitter:card" content="summary">
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
        :root{color-scheme:light;--bg:#f5f7f8;--surface:#fff;--surface-soft:#eef3f5;--text:#182027;--muted:#5c6873;--line:#d7e0e5;--accent:#a87520;--accent-soft:#fff4dc;--blue:#2368a2;--green:#267d5a;--red:#a24646;--shadow:0 18px 50px rgba(24,32,39,.08)}
        :root[data-theme=dark]{color-scheme:dark;--bg:#080b10;--surface:#111821;--surface-soft:#17212d;--text:#f8fafc;--muted:#9aa7b4;--line:#263241;--accent:#d9a441;--accent-soft:#20190d;--blue:#62a8ff;--green:#33d69f;--red:#ff647c;--shadow:0 24px 70px rgba(0,0,0,.28)}
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;background:var(--bg);color:var(--text);font-family:Vazirmatn,Tahoma,sans-serif;line-height:1.95;letter-spacing:0}
        a{color:var(--blue);text-decoration:none}
        a:hover{text-decoration:underline}
        .learnShell{width:min(1180px,100%);margin:auto;padding:22px}
        .learnTop{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:26px}
        .brand{color:var(--text);font-weight:850;font-size:18px}
        .nav{display:flex;gap:10px;flex-wrap:wrap}
        .nav a{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:8px 12px;color:var(--text)}
        .hero{padding:34px 0 22px;border-bottom:1px solid var(--line)}
        .eyebrow{color:var(--accent);font-size:14px;font-weight:750}
        h1{margin:8px 0 12px;font-size:clamp(32px,5vw,50px);line-height:1.35;max-width:900px}
        h2{font-size:25px;line-height:1.55;margin:42px 0 14px}
        h3{font-size:18px;margin:0 0 8px}
        p{margin:0 0 14px}
        .lead{font-size:18px;color:var(--muted);max-width:860px}
        .meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;color:var(--muted);font-size:14px}
        .pill{border:1px solid var(--line);border-radius:999px;background:var(--surface);padding:6px 10px}
        .contentGrid{display:grid;grid-template-columns:minmax(0,760px) 300px;gap:44px;align-items:start;justify-content:center;margin-top:26px}
        article{min-width:0}
        .articleProse p{font-size:17px;line-height:2.15;color:var(--text);margin:0 0 18px}
        .articleProse .hero{padding-top:18px}
        .panel{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:20px}
        ul{padding-right:20px;margin:8px 0 0}
        li{margin:7px 0}
        .section{border-top:1px solid var(--line);padding-top:2px}
        .links{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 4px}
        .links a{border:1px solid var(--line);border-radius:8px;background:var(--surface-soft);padding:8px 11px}
        .answerBox{border-right:4px solid var(--accent);background:var(--surface-soft);border-radius:8px;padding:18px 20px;margin:26px 0}
        .answerBox strong{display:block;margin-bottom:6px}
        .noteBox,.readerQuestions,.checklistPanel{border:1px solid var(--accent);background:var(--accent-soft);border-radius:8px;padding:20px;margin:30px 0}
        .noteBox h2{margin-top:0}
        .dataTable{width:100%;border-collapse:collapse;background:var(--surface);border:1px solid var(--line);border-radius:8px;overflow:hidden;margin:14px 0;display:table}
        .dataTable th,.dataTable td{border:1px solid var(--line);padding:10px;text-align:right;vertical-align:top}
        .dataTable th{background:var(--surface-soft)}
        .glossary{display:grid;gap:10px;margin:12px 0}
        .term{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:12px}
        .term strong{display:block;color:var(--accent);margin-bottom:4px}
        .sourceList{font-size:14px;color:var(--muted)}
        .sourceList a{color:var(--blue)}
        .mistakeGrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 4px}
        .mistakeItem{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px}
        .faqItem{border-top:1px solid var(--line);padding:16px 0}
        .faqItem:first-child{border-top:0}
        .faqItem summary{cursor:pointer;font-weight:800;list-style:none}
        .faqItem summary::-webkit-details-marker{display:none}
        .faqItem summary:after{content:'+';float:left;color:var(--accent);font-size:20px;line-height:1}
        .faqItem[open] summary:after{content:'−'}
        .faqItem p{margin-top:10px}
        aside{position:sticky;top:16px}
        aside .panel{box-shadow:none}
        .sideList{display:grid;gap:10px}
        .sideList a{display:block;border:1px solid var(--line);background:var(--surface);border-radius:8px;padding:10px 12px;color:var(--text)}
        .tocPanel{margin-bottom:14px}
        .tocPanel .sideList a{font-size:14px;color:var(--muted)}
        .note{font-size:14px;color:var(--muted)}
        .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:22px}
        .card{border:1px solid var(--line);background:var(--surface);border-radius:8px;padding:18px}
        .breadcrumb{font-size:14px;color:var(--muted);margin-bottom:8px}
        .breadcrumb a{color:var(--muted)}
        footer{border-top:1px solid var(--line);margin-top:36px;padding-top:18px;color:var(--muted);font-size:14px}
        @media (max-width:860px){.learnShell{padding:16px}.learnTop{align-items:flex-start;flex-direction:column}.contentGrid,.summaryGrid,.cards,.mistakeGrid{grid-template-columns:1fr}aside{position:static}.hero{padding-top:12px}h1{font-size:31px}.lead{font-size:16px}.dataTable{display:block;overflow-x:auto}.tocPanel{display:none}}
    </style>
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
    </footer>
</div>
</body>
</html>
