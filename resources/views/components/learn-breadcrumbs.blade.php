<nav class="breadcrumb" aria-label="مسیر صفحه">
    <a href="/">خانه</a>
    <span> / </span>
    <a href="{{ config('learn.base_path', '/blog') }}">بلاگ</a>
    @isset($current)
        <span> / </span>
        <span>{{ $current }}</span>
    @endisset
</nav>
