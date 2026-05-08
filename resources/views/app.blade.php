<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0f14">
    <meta name="description" content="داشبورد تحلیلی قیمت طلا و سکه با نمودارهای تاریخی و داده‌های estjt.ir">
    <title>سامانه قیمت طلا و سکه</title>
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
    <div id="root"></div>
</body>
</html>
