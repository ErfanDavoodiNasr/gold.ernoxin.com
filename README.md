<div dir="rtl" align="right">

# سامانه قیمت طلا و سکه

یک اپلیکیشن فول‌استک برای پایش قیمت طلا و سکه با Laravel 8، React، MySQL و رابط کاملا فارسی و راست‌به‌چپ. منبع اصلی داده‌ها `https://www.estjt.ir/price/` است و منطق استخراج داده از پروژه محلی `/Volumes/SanDisk/estjt-gold-api` به PHP منتقل شده است.

## قابلیت‌ها

- دریافت دوره‌ای قیمت‌ها از estjt.ir با دستور `gold:fetch-prices`
- ذخیره تاریخچه در MySQL برای طلا، سکه و ردیف‌های اضافه منبع
- API داخلی برای خلاصه بازار و تاریخچه هر آیتم
- داشبورد React موبایل‌محور با حالت روشن/تاریک، نمودار، جستجو و فیلتر بازه
- فونت Vazirmatn به صورت self-hosted در `public/fonts`
- بدون وابستگی runtime به CDN، Google Fonts یا سرویس خارجی frontend
- خروجی build شده در `public/build` برای استقرار مستقیم روی cPanel

## تنظیمات مهم

نمونه کامل در `.env.example` قرار دارد:

```env
ESTJT_SOURCE_URL=https://www.estjt.ir/price/
ESTJT_SOURCE_NAME=estjt
ESTJT_FETCH_INTERVAL_MINUTES=5
ESTJT_TIMEOUT_CONNECT=3
ESTJT_TIMEOUT_READ=8
ESTJT_RETRY_COUNT=2
ESTJT_RETRY_BACKOFF_MS=300
ESTJT_CACHE_SECONDS=60
FEATURE_AUTO_FETCH=true
FEATURE_DARK_MODE=true
FEATURE_MANUAL_FETCH_API=false
CHART_DEFAULT_RANGE_DAYS=7
CHART_AVAILABLE_RANGES=1,7,30,90,180,365
CHART_MAX_POINTS=600
HISTORY_MAX_DAYS=365
THEME_DEFAULT=dark
THEME_ACCENT=#d9a441
```

## API

- `GET /api/market/summary`
- `GET /api/market/items/{id}/history?days=7`
- `POST /api/market/fetch` فقط وقتی `FEATURE_MANUAL_FETCH_API=true` باشد.

## نصب محلی توسعه

در محیطی که PHP 8.2 و Composer دارد:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate
npm install
npm run build
```

پوشه `vendor/`، فایل `composer.lock` و فایل‌های build شده باید در Git باقی بمانند. `.gitignore` عمدا `vendor/` و `public/build/` را ignore نمی‌کند.

## استقرار روی cPanel

1. فایل `.env` را از روی `.env.example` بسازید و اطلاعات دیتابیس cPanel را وارد کنید.
2. روی یک ماشین محلی دارای PHP/Composer دستورهای نصب و build را اجرا کنید.
3. کل پروژه، شامل `vendor/`، `public/build/`، `public/fonts/` و `composer.lock` را commit و روی سرور upload کنید.
4. Document Root دامنه را روی پوشه `public` قرار دهید. اگر امکان تغییر Document Root ندارید، محتوای `public` را در `public_html` قرار دهید و مسیرهای `../vendor` و `../bootstrap` در `index.php` را مطابق ساختار هاست تنظیم کنید.
5. یک بار migration را اجرا کنید. اگر SSH ندارید، migration را روی دیتابیس import کنید یا از Terminal cPanel استفاده کنید:

```bash
php artisan migrate --force
php artisan optimize
```

## Cron در cPanel

برای زمان‌بندی استاندارد Laravel هر دقیقه:

```bash
* * * * * /usr/local/bin/php /home/USER/path-to-project/artisan schedule:run >> /dev/null 2>&1
```

فاصله واقعی دریافت داده با `ESTJT_FETCH_INTERVAL_MINUTES` کنترل می‌شود. اگر scheduler روی هاست محدود بود، مستقیم این دستور را با فاصله دلخواه اجرا کنید:

```bash
*/5 * * * * /usr/local/bin/php /home/USER/path-to-project/artisan gold:fetch-prices >> /dev/null 2>&1
```

## معماری استخراج

سرویس `App\Services\EstjtScraper` همان استراتژی پروژه Python را پیاده‌سازی می‌کند:

- شناسایی جدول‌ها با header های `نوع طلا` و `نوع سکه`
- fallback بر اساس تطبیق نام ردیف‌های شناخته‌شده
- نرمال‌سازی اعداد فارسی/عربی
- استخراج واحد پول از مقدارهای طلا
- تشخیص جهت تغییر از کلاس‌های `asc` و `desc`
- ثبت خطاها در `fetch_logs` و `storage/logs/laravel.log`

</div>
