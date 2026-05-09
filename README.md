<div dir="rtl" align="right">

# سامانه قیمت طلا و سکه

اپلیکیشن Laravel + React برای نمایش قیمت طلا و سکه با ذخیره تاریخچه در MySQL. منبع داده‌ها `https://www.estjt.ir/price/`
است.

## امکانات اصلی

- دریافت خودکار قیمت‌ها بدون نیاز به Cron روی هاست
- ساخت و به‌روزرسانی خودکار جدول‌های دیتابیس با migration
- مناسب برای cPanel بدون SSH/Terminal
- داشبورد فارسی، راست‌به‌چپ، موبایل‌محور
- خروجی آماده آپلود همراه با `vendor/` و `public/build/`

## تنظیمات مهم

فایل `.env` را از روی `.env.example` بسازید. توضیح کامل هر گزینه داخل `.env.example` نوشته شده است.

مقادیر مهم برای production:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://gold.ernoxin.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gold_prices
DB_USERNAME=gold_user
DB_PASSWORD=

FEATURE_AUTO_FETCH=true
FEATURE_MANUAL_FETCH_API=false
HOSTING_AUTO_MIGRATE=true
HOSTING_ENSURE_WRITABLE_PATHS=true
```

`APP_KEY` اجباری است. روی سیستم خودت بساز:

```bash
php artisan key:generate
```

بعد از production مقدار `APP_KEY` را تغییر نده.

## آموزش دیپلوی روی cPanel

### 1. آماده‌سازی روی سیستم خودت

روی هاست command اجرا نمی‌کنی. این دستورها فقط روی سیستم خودت اجرا می‌شوند:

```bash
npm ci
npm run build
composer install --no-dev --optimize-autoloader
```

این commandها را برای نسخه upload نهایی اجرا نکن:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

دلیل: مخصوصا `config:cache` ممکن است `.env` سیستم خودت را cache کند و روی هاست تنظیمات اشتباه استفاده شود.

### 2. ساخت دیتابیس در cPanel

در cPanel یک دیتابیس MySQL و یک user بساز. سپس اطلاعات آن را داخل `.env` وارد کن:

```env
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

اگر `HOSTING_AUTO_MIGRATE=true` باشد، لازم نیست روی هاست migration اجرا کنی. اولین بازدید سایت خودش جدول‌ها را می‌سازد.
در نسخه‌های بعد هم اگر migration جدید اضافه شود، خودش اعمال می‌شود.

اگر خواستی migration خودکار را خاموش کنی، فایل زیر را در phpMyAdmin import کن:

```text
database/schema/mysql.sql
```

### 3. آپلود فایل‌ها

کل پروژه را روی هاست upload کن. این موارد حتما باید همراه پروژه باشند:

- `vendor/`
- `composer.lock`
- `public/build/`
- `public/fonts/`
- `.env`

### 4. تنظیم Document Root

بهترین حالت: Document Root دامنه را روی پوشه `public` بگذار.

اگر cPanel اجازه تغییر Document Root نداد:

- محتوای پوشه `public` را داخل `public_html` بگذار.
- مسیرهای `../vendor` و `../bootstrap` در `index.php` را مطابق محل واقعی پروژه تنظیم کن.

### 5. اولین تست

بعد از آپلود، سایت را باز کن. برنامه باید این کارها را خودش انجام دهد:

- ساخت پوشه‌های runtime مثل `storage/framework` و `storage/logs`
- اتصال به دیتابیس
- ساخت جدول‌ها در دیتابیس اگر خالی باشد
- اعمال migrationهای جدید اگر وجود داشته باشند
- دریافت قیمت‌ها هنگام درخواست API، اگر زمان دریافت قبلی گذشته باشد

برنامه هنگام اولین درخواست، مسیرهای runtime را خودش می‌سازد و دسترسی نوشتن لازم را برای این مسیرها تنظیم می‌کند:

```text
storage/
bootstrap/cache/
```

اگر مالکیت فایل‌ها در هاست اجازه تغییر permission از داخل PHP را ندهد، باید مالکیت فایل‌ها در همان هاست اصلاح شود؛ در
حالت عادی نیازی به تنظیم دستی File Manager نیست.

## دریافت خودکار قیمت

Cron لازم نیست. وقتی frontend این API را صدا می‌زند:

```text
GET /api/market/summary
```

برنامه بررسی می‌کند آخرین دریافت موفق قدیمی‌تر از `ESTJT_FETCH_INTERVAL_MINUTES` هست یا نه. اگر قدیمی باشد، همان request
قیمت‌های جدید را ذخیره می‌کند و بعد پاسخ API را برمی‌گرداند.

## API

```text
GET  /api/market/summary
GET  /api/market/items/{id}/history?days=7
POST /api/market/fetch
```

`POST /api/market/fetch` فقط وقتی فعال است که:

```env
FEATURE_MANUAL_FETCH_API=true
```

برای production مقدار پیشنهادی `false` است.

## نکات نگهداری

- برای تغییر جدول‌ها، migration جدید بساز و همراه پروژه upload کن.
- migration قدیمی را بعد از اجرا تغییر نده.
- اگر `HOSTING_AUTO_MIGRATE=true` باشد، migration جدید روی هاست خودکار اجرا می‌شود.
- فایل‌های build و `vendor/` باید در پروژه باقی بمانند چون هاست command ندارد.

</div>
