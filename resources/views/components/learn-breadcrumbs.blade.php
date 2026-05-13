<nav class="breadcrumb" aria-label="مسیر صفحه">
    <a href="/">خانه</a>
    <span> / </span>
    <a href="/learn">آموزش</a>
    @isset($current)
        <span> / </span>
        <span>{{ $current }}</span>
    @endisset
</nav>
