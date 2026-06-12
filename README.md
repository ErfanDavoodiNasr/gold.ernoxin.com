<div dir="rtl" align="right">

<p align="center">
  <img src="public/favicon.svg" alt="آیکون سکه و طلای ارنوکسین" width="96" height="96">
</p>

# سامانه قیمت طلا و سکه ارنوکسین

داشبورد فارسی قیمت طلا و سکه برای بازار ایران. پروژه قیمت‌ها را از منبع مشخص دریافت می‌کند، در MySQL ذخیره می‌کند و در
کنار نمودارهای تاریخی، یک بلاگ آموزشی برای توضیح مفاهیم بازار طلا دارد.

این پروژه با Laravel، React، Vite و MySQL ساخته شده و مناسب هاست اشتراکی/cPanel است.

## امکانات اصلی

- نمایش قیمت لحظه‌ای طلا و سکه
- ذخیره تاریخچه قیمت‌ها و نمایش نمودار بازه‌ای
- بلاگ آموزشی ایران‌محور با جستجوی مقاله‌ها

## نیازمندی‌ها

- PHP `8.2`
- MySQL
- افزونه‌های PHP: `dom`, `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`,
  `curl`

## نصب سریع روی cPanel

1. از صفحه **Releases** آخرین نسخه را باز کنید.
2. فایل `ernoxin-gold.zip` را از بخش Assets دانلود و روی هاست extract کنید.
3. یک دیتابیس MySQL و user بسازید.
4. اطلاعات دیتابیس و دامنه را در `.env` تنظیم کنید.
5. دامنه را باز کنید تا برنامه `APP_KEY`، جدول‌ها و مسیرهای لازم را آماده کند.
6. Cron دریافت قیمت را فعال کنید.

هر tag با فرمت `v*` مثل `v1.0.0` در GitHub Actions بیلد می‌شود و فایل آماده‌ی نصب به همان Release اضافه می‌شود.

نمونه تنظیمات ضروری `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gold.ernoxin.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gold_prices
DB_USERNAME=gold_user
DB_PASSWORD=
```

اگر `APP_KEY` خالی باشد، برنامه در اولین اجرا آن را تولید می‌کند. بعد از نصب، مقدار آن را تغییر ندهید.

## دریافت خودکار قیمت‌ها

منبع پیش‌فرض:

```text
https://www.estjt.ir/price/
```

تنظیمات مهم:

```env
ESTJT_SOURCE_URL=https://www.estjt.ir/price/
ESTJT_FETCH_INTERVAL_MINUTES=1
ESTJT_TIMEOUT_CONNECT=3
ESTJT_TIMEOUT_READ=5
ESTJT_RETRY_COUNT=1
ESTJT_RETRY_BACKOFF_MS=150
MARKET_SUMMARY_CACHE_SECONDS=10
FRONTEND_REFRESH_SECONDS=60
```

Cron پیشنهادی در cPanel:

```text
Minute:  *
Hour:    *
Day:     *
Month:   *
Weekday: *
Command: /usr/local/bin/php /home/USER/gold/artisan gold:fetch-prices
```

مسیر `/home/USER/gold/artisan` را با مسیر واقعی پروژه روی هاست جایگزین کنید. اگر مسیر PHP روی هاست متفاوت است، به جای
`/usr/local/bin/php` همان مسیر را بگذارید.

اگر هاست اجرای هر دقیقه را محدود کرده، Cron را هر پنج دقیقه اجرا کنید. در این حالت حتی اگر
`ESTJT_FETCH_INTERVAL_MINUTES=1` باشد، دریافت واقعی حداکثر هر پنج دقیقه انجام می‌شود.

## API

```text
GET  /api/market/summary
GET  /api/market/items/{id}/history?range=1d
```

## توسعه محلی

```bash
composer install
npm install
npm run build
php artisan migrate
php artisan serve
```

برای اجرای دریافت قیمت:

```bash
php artisan gold:fetch-prices --force
```

## خطایابی

اگر سایت خطای 500 داد، ابتدا این فایل را بررسی کنید:

```text
storage/logs/laravel.log
```

اگر خطا مربوط به permission بود، مسیرهای زیر باید قابل نوشتن باشند:

```text
storage/
bootstrap/cache/
```

</div>
