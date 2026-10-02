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
4. اطلاعات دیتابیس را در `.env.example` وارد کنید.
5. دامنه را باز کنید؛ برنامه به صورت خودکار:
   - فایل `.env` را می‌سازد و `APP_KEY` را مقداردهی می‌کند.
   - **مهاجرت هوشمند دیتابیس** را به صورت خودکار اجرا می‌کند: چه دیتابیس نو باشد و چه از نسخه‌های قدیمی حاوی داده باشد، ساختار دیتابیس تشخیص داده شده و جداول، انتقال داده‌ها، ستون‌های جدید و ایندکس‌های پرفورمنس بدون خطر قطعی و بدون از دست رفتن داده‌ها همگام می‌شوند.
6. Cron دریافت قیمت را فعال کنید.

در صورت نیاز به بررسی دستی یا شبیه‌سازی وضعیت دیتابیس از طریق خط فرمان:
```bash
php artisan gold:migrate --status    # گزارش وضعیت و سلامت ساختار دیتابیس
php artisan gold:migrate --dry-run   # شبیه‌سازی مراحل ارتقا بدون تغییر
php artisan gold:migrate             # اجرای مستقیم مهاجرت
```

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

اگر `.env` وجود نداشته باشد، برنامه در اولین اجرا از `.env.example` یک `.env` می‌سازد. اگر `APP_KEY` خالی باشد،
در همان اجرا
تولید و ذخیره می‌شود. بعد از نصب، مقدار `APP_KEY` را تغییر ندهید.

## دریافت خودکار قیمت‌ها

منبع پیش‌فرض:

```text
https://www.estjt.ir/price/
```

تنظیمات مهم:

```env
ESTJT_SOURCE_URL=https://www.estjt.ir/price/
ESTJT_FETCH_INTERVAL_MINUTES=5
ESTJT_TIMEOUT_CONNECT=3
ESTJT_TIMEOUT_READ=5
ESTJT_RETRY_COUNT=1
ESTJT_RETRY_BACKOFF_MS=150
MARKET_SUMMARY_CACHE_SECONDS=10
FRONTEND_REFRESH_SECONDS=60
HISTORY_RETENTION_DAYS=400
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

فاصله دریافت با `ESTJT_FETCH_INTERVAL_MINUTES` نسبت به آخرین دریافت موفق اندازه‌گیری می‌شود
(مثلاً با مقدار ۵، حداقل پنج دقیقه بعد از آخرین موفقیت). Cron باید هر دقیقه اجرا شود؛ برنامه خودش اجراهای زودتر را
رد می‌کند و با قفل فایل از همپوشانی جلوگیری می‌کند. نقاط قدیمی‌تر از `HISTORY_RETENTION_DAYS` روزی یک‌بار پاک می‌شوند.

اگر هاست اجرای هر دقیقه را محدود کرده، Cron را هر پنج دقیقه اجرا کنید. در این حالت حتی اگر
`ESTJT_FETCH_INTERVAL_MINUTES=1` باشد، دریافت واقعی حداکثر هر پنج دقیقه انجام می‌شود.

کش برای هاست cPanel بدون Redis: اگر افزونهٔ PHP‏ `APCu` فعال باشد به‌صورت خودکار از حافظهٔ مشترک استفاده
می‌شود (`CACHE_DRIVER=apc`)؛ وگرنه `file`. قفل ضد-stampede همیشه روی فایل است. در cPanel از
Select PHP Version / Extensions می‌توانید `apcu` را روشن کنید.

## تست‌ها و راستی‌آزمایی پیش از دیپلوی (Verification & Tests)

برای اطمینان ۱۰۰٪ از صحت عملکرد بکند و فرانت‌اند قبل از آپلود یا کامیت:

```bash
npm run test:frontend   # تست‌های مستقل فرانت‌اند (Node.js test runner)
npm run test:backend    # تست‌های PHPUnit بکند (۸۳ تست با ۲۳۷ assertion)
npm run test:all        # اجرای هر دو مجموعه تست بکند و فرانت
npm run verify          # بررسی کامل ۶ مرحله‌ای پیش از دیپلوی (Syntax, Frontend Tests, Vite Build, Backend Tests, Smart Migrator)
```

تست‌ها روی SQLite حافظه‌ای اجرا می‌شوند و به منبع زنده estjt وابسته نیستند. Fixtureهای HTML در `tests/Fixtures/estjt/` هستند.

## امنیت DocumentRoot روی cPanel

اگر کل پروژه داخل `public_html` باشد، ریشهٔ `.htaccess` مسیرهای حساس (`app`, `vendor`, `.env`, `.git`, …) را مسدود
می‌کند. بدون `mod_rewrite` هم دسترسی به فایل‌های غیر از `index.php` رد می‌شود.

## توسعه محلی

1. فایل `database/schema/mysql.sql` را در MySQL import کنید (یا پچ
   `database/schema/patches/2026-08-perf-raw-and-hourly.sql` روی دیتابیس موجود).
2. سپس:

```bash
composer install
npm install
npm run build
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
