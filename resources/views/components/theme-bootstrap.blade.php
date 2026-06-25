<script>
    (function () {
        var saved = localStorage.getItem('theme');
        var serverDefault = @json(config('gold.theme_default', 'system'));
        var theme = saved;
        if (!theme) {
            theme = serverDefault === 'dark' || serverDefault === 'light'
                ? serverDefault
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        }
        document.documentElement.dataset.theme = theme;
    })();
</script>
