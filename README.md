<div dir="rtl" align="right">

# سامانه قیمت طلا و سکه

سامانه قیمت طلا و سکه یک داشبورد فارسی برای نمایش، ذخیره و بررسی قیمت‌های بازار طلا و سکه است. این برنامه قیمت‌ها را از
منبع مشخص و رسمی دریافت می‌کند، داده‌ها را در MySQL نگه می‌دارد و امکان مشاهده آخرین وضعیت بازار و تاریخچه قیمت‌ها را
فراهم می‌کند.

این پروژه با Laravel، React و MySQL ساخته شده و برای اجرا روی هاست‌های اشتراکی و cPanel آماده‌سازی شده است.

## امکانات

- نمایش آخرین قیمت طلا و سکه
- ذخیره تاریخچه قیمت‌ها در دیتابیس
- نمودار تغییرات قیمت در بازه‌های زمانی مختلف
- داشبورد فارسی، راست‌به‌چپ و مناسب موبایل
- پشتیبانی از حالت تاریک
- API داخلی برای دریافت خلاصه بازار و تاریخچه هر آیتم
- ساخت خودکار جدول‌های دیتابیس در اولین اجرا
- آماده‌سازی خودکار مسیرهای موردنیاز Laravel مثل `storage/` و `bootstrap/cache/`
- تولید خودکار `APP_KEY` در اولین اجرا، اگر در `.env` خالی باشد
- قابل اجرا روی cPanel بدون نیاز به SSH، Composer، npm یا اجرای دستور دستی
- دریافت خودکار قیمت‌ها از طریق Cron/Laravel Scheduler، مستقل از حضور کاربر در سایت

## تکنولوژی‌ها

- Laravel `8`
- React
- Vite
- MySQL
- PHP `8.2`

## نیازمندی‌ها

نسخه PHP باید روی `8.2` تنظیم شود. این پروژه برای نسخه‌های بالاتر از `8.2` در نظر گرفته نشده است.

افزونه‌های PHP موردنیاز:

```text
dom
pdo
pdo_mysql
mbstring
openssl
tokenizer
xml
ctype
json
fileinfo
curl
```

## نصب روی cPanel

### 1. دانلود نسخه آماده

### 2. ساخت دیتابیس

در cPanel یک دیتابیس MySQL و یک user بسازید و user را به دیتابیس متصل کنید.

سپس اطلاعات دیتابیس را در فایل `.env` قرار دهید:

```env
DB_DATABASE=نام_دیتابیس
DB_USERNAME=نام_کاربر
DB_PASSWORD=رمز_عبور
```

### 3. آپلود فایل‌ها

کل پروژه را بدون تغییر ساختار پوشه‌ها داخل `public_html` آپلود کنید.

فایل‌ها و پوشه‌های اصلی که باید روی هاست وجود داشته باشند:

```text
.env
.htaccess
index.php
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
vendor/
```

فایل `.htaccess` ریشه پروژه درخواست‌ها را به پوشه `public` هدایت می‌کند و جلوی دسترسی مستقیم به فایل‌های حساس مثل
`.env`، `vendor/` و `storage/` را می‌گیرد.

### 4. اجرای سایت

بعد از آپلود، دامنه را باز کنید. برنامه در اولین اجرا این موارد را خودکار انجام می‌دهد:

- ساخت `APP_KEY` در صورت خالی بودن
- ساخت جدول‌های دیتابیس
- آماده‌سازی مسیرهای قابل‌نوشتن
- بارگذاری داشبورد

## تنظیمات اصلی

تنظیمات پروژه در فایل `.env` قرار دارد. مهم‌ترین گزینه‌ها:

```env
APP_NAME="سامانه قیمت طلا"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gold_prices
DB_USERNAME=gold_user
DB_PASSWORD=
```

اگر `APP_KEY` خالی باشد، برنامه خودش آن را تولید و در `.env` ذخیره می‌کند. بعد از نصب، مقدار `APP_KEY` را تغییر ندهید.

## تنظیمات منبع قیمت

منبع پیش‌فرض قیمت‌ها:

```text
https://www.estjt.ir/price/
```

گزینه‌های مرتبط در `.env`:

```env
ESTJT_SOURCE_URL=https://www.estjt.ir/price/
ESTJT_SOURCE_KEY=estjt
ESTJT_SOURCE_NAME="اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران"
ESTJT_FETCH_INTERVAL_MINUTES=1
ESTJT_TIMEOUT_CONNECT=3
ESTJT_TIMEOUT_READ=8
ESTJT_RETRY_COUNT=2
ESTJT_RETRY_BACKOFF_MS=300
ESTJT_AUTO_FETCH=true
ESTJT_FETCH_LOCK_SECONDS=120
MARKET_SUMMARY_CACHE_SECONDS=20
```

## اجرای دریافت خودکار در پس‌زمینه

برای اینکه قیمت‌ها حتی وقتی هیچ کاربری داخل سایت نیست به‌روزرسانی شوند، باید Cron هاست command دریافت قیمت را اجرا کند.
در cPanel زمان‌بندی را داخل فیلدهای جداگانه وارد کنید و داخل فیلد `Command` فقط دستور را بنویسید.

پیشنهاد اصلی:

```text
Minute:  *
Hour:    *
Day:     *
Month:   *
Weekday: *
Command: /usr/local/bin/php /home/USER/gold/artisan gold:fetch-prices
```

اگر هاست اجازه اجرای هر دقیقه را نمی‌دهد، اجرای هر پنج دقیقه هم قابل استفاده است:

```text
Minute:  */5
Hour:    *
Day:     *
Month:   *
Weekday: *
Command: /usr/local/bin/php /home/USER/gold/artisan gold:fetch-prices
```

در این نوع cPanel از `cd`، `&&`، `;` و `>/dev/null 2>&1` داخل Command استفاده نکنید، چون بعضی هاست‌ها آن‌ها را به عنوان
command chaining رد می‌کنند.
همچنین اگر هاست `proc_open` را غیرفعال کرده باشد، از `artisan schedule:run` استفاده نکنید و همین command مستقیم
`gold:fetch-prices` را در Cron قرار دهید.

مسیر `/home/USER/gold/artisan` را با مسیر واقعی پروژه روی هاست جایگزین کنید. برای مثال:

```text
/usr/local/bin/php /home/iouanode/gold/artisan gold:fetch-prices
```

در بعضی cPanelها مسیر PHP متفاوت است. اگر دستور بالا کار نکرد، مسیر PHP هاست را جایگزین `/usr/local/bin/php` کنید.

نحوه کار زمان‌بندی:

- Cron command دریافت قیمت را بیدار می‌کند.
- command `gold:fetch-prices` هر بار بررسی می‌کند آیا زمان دریافت قیمت رسیده یا نه.
- مقدار `ESTJT_FETCH_INTERVAL_MINUTES` در `.env` تعیین می‌کند خود برنامه چند دقیقه یک‌بار واقعا از منبع قیمت بگیرد.
- اگر Cron هر دقیقه باشد و `ESTJT_FETCH_INTERVAL_MINUTES=5` باشد، برنامه هر دقیقه بررسی می‌کند ولی فقط هر پنج دقیقه fetch واقعی انجام می‌دهد.
- اگر Cron هر پنج دقیقه باشد و `ESTJT_FETCH_INTERVAL_MINUTES=1` باشد، fetch واقعی حداکثر هر پنج دقیقه انجام می‌شود، چون Cron زودتر برنامه را بیدار نمی‌کند.

خود برنامه جلوی اجرای همزمان چند fetch را می‌گیرد و فاصله `ESTJT_FETCH_INTERVAL_MINUTES` را رعایت می‌کند.

## تنظیمات نمودار

```env
CHART_DEFAULT_RANGE_DAYS=7
CHART_AVAILABLE_RANGES=1,7,30,90,180,365
CHART_MAX_POINTS=600
HISTORY_MAX_DAYS=365
```

## API

```text
GET  /api/market/summary
GET  /api/market/items/{id}/history?days=7
POST /api/market/fetch
```

در حالت پیش‌فرض، API دریافت دستی قیمت غیرفعال است.

## مسیرهای مهم

```text
app/                 کدهای اصلی Laravel
config/              تنظیمات برنامه
database/migrations/ ساختار جدول‌های دیتابیس
public/              فایل ورودی وب و assetهای عمومی
public/build/        خروجی آماده frontend
resources/           فایل‌های React و Blade
routes/              مسیرهای web و API
storage/             فایل‌های runtime، cache و log
```

## خطایابی

اگر سایت خطای 500 نمایش داد، فایل log را بررسی کنید:

```text
storage/logs/laravel.log
```

اگر خطا مربوط به permission بود، دسترسی نوشتن این مسیرها را در File Manager هاست بررسی کنید:

```text
storage/
bootstrap/cache/
```

</div>
