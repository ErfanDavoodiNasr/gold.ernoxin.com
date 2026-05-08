<div dir="rtl" align="right">

# چک‌لیست تولید

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` مقداردهی شده باشد.
- `vendor/` در مخزن وجود داشته باشد.
- `public/build/manifest.json` و فایل‌های داخل `public/build/assets` در مخزن وجود داشته باشند.
- `public/fonts/Vazirmatn-Regular.woff2` روی سرور وجود داشته باشد.
- دسترسی نوشتن برای `storage/` و `bootstrap/cache/` فعال باشد.
- Cron برای `schedule:run` یا `gold:fetch-prices` تنظیم شده باشد.
- دیتابیس MySQL با charset `utf8mb4` ساخته شده باشد.

## دستور build نهایی frontend

```bash
npm ci
npm run build
```

## دستور آماده‌سازی backend

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

</div>
