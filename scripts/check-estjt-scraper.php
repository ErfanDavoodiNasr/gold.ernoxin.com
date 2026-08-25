<?php

/**
 * Self-check for EstjtScraper header mapping + known-type guard.
 * Run: php scripts/check-estjt-scraper.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$html = <<<'HTML'
<html><body>
<table>
<tr><th>نوع طلا</th><th>لحظه‌ای</th><th>بیشترین</th><th>کمترین</th><th>دیروز</th><th>تغییر</th></tr>
<tr><td>انس طلا</td><td>$ 2500</td><td>$ 2510</td><td>$ 2490</td><td>$ 2480</td><td class="asc">+1%</td></tr>
<tr><td>مظنه تهران</td><td>12,000,000</td><td>12,100,000</td><td>11,900,000</td><td>11,800,000</td><td class="desc">-0.5%</td></tr>
<tr><td>طلای ۱۸ عیار</td><td>3,000,000</td><td>3,050,000</td><td>2,950,000</td><td>2,900,000</td><td class="asc">+1%</td></tr>
<tr><td>طلای ۲۴ عیار</td><td>4,000,000</td><td>4,050,000</td><td>3,950,000</td><td>3,900,000</td><td class="asc">+1%</td></tr>
</table>
<table>
<tr><th>نوع سکه</th><th>لحظه‌ای</th><th>بیشترین</th><th>کمترین</th><th>دیروز</th><th>تغییر</th></tr>
<tr><td>سکه طرح قدیم</td><td>30,000,000</td><td>31,000,000</td><td>29,000,000</td><td>28,000,000</td><td class="asc">+2%</td></tr>
<tr><td>سکه طرح جدید</td><td>31,000,000</td><td>32,000,000</td><td>30,000,000</td><td>29,000,000</td><td class="asc">+2%</td></tr>
<tr><td>نیم سکه</td><td>16,000,000</td><td>16,500,000</td><td>15,500,000</td><td>15,000,000</td><td class="desc">-1%</td></tr>
<tr><td>ربع سکه</td><td>9,000,000</td><td>9,200,000</td><td>8,800,000</td><td>8,700,000</td><td class="asc">+1%</td></tr>
<tr><td>سکه یک گرمی</td><td>5,000,000</td><td>5,100,000</td><td>4,900,000</td><td>4,800,000</td><td>0%</td></tr>
</table>
</body></html>
HTML;

$scraper = app(App\Services\EstjtScraper::class);
$parsed = $scraper->parse($html, now()->toIso8601String());
assert(count($parsed['gold']) === 4);
assert(count($parsed['coin']) === 5);

// Header-only swap: data stays put → following labels must read current from the new لحظه‌ای column.
$headerOnly = str_replace(
    '<th>لحظه‌ای</th><th>بیشترین</th>',
    '<th>بیشترین</th><th>لحظه‌ای</th>',
    $html
);
$headerOnlyParsed = $scraper->parse($headerOnly, now()->toIso8601String());
$gold18HeaderOnly = collect($headerOnlyParsed['gold'])->first(fn($row) => str_contains($row['type'], '۱۸'));
assert($gold18HeaderOnly !== null);
assert((float)$gold18HeaderOnly['current']['value'] === 3050000.0, 'header map must follow labels after header-only swap');
assert((float)$gold18HeaderOnly['high']['value'] === 3000000.0, 'high must follow بیشترین after header-only swap');

// Full column reorder (headers + cells move together) → current stays 3,000,000 under لحظه‌ای.
$reordered = str_replace(
    [
        '<th>لحظه‌ای</th><th>بیشترین</th>',
        '<td>3,000,000</td><td>3,050,000</td>',
        '<td>4,000,000</td><td>4,050,000</td>',
        '<td>12,000,000</td><td>12,100,000</td>',
        '<td>$ 2500</td><td>$ 2510</td>',
        '<td>30,000,000</td><td>31,000,000</td>',
        '<td>31,000,000</td><td>32,000,000</td>',
        '<td>16,000,000</td><td>16,500,000</td>',
        '<td>9,000,000</td><td>9,200,000</td>',
        '<td>5,000,000</td><td>5,100,000</td>',
    ],
    [
        '<th>بیشترین</th><th>لحظه‌ای</th>',
        '<td>3,050,000</td><td>3,000,000</td>',
        '<td>4,050,000</td><td>4,000,000</td>',
        '<td>12,100,000</td><td>12,000,000</td>',
        '<td>$ 2510</td><td>$ 2500</td>',
        '<td>31,000,000</td><td>30,000,000</td>',
        '<td>32,000,000</td><td>31,000,000</td>',
        '<td>16,500,000</td><td>16,000,000</td>',
        '<td>9,200,000</td><td>9,000,000</td>',
        '<td>5,100,000</td><td>5,000,000</td>',
    ],
    $html
);
$reorderedParsed = $scraper->parse($reordered, now()->toIso8601String());
$gold18Reordered = collect($reorderedParsed['gold'])->first(fn($row) => str_contains($row['type'], '۱۸'));
assert($gold18Reordered !== null);
assert((float)$gold18Reordered['current']['value'] === 3000000.0, 'header map must keep current after full column reorder');
assert((float)$gold18Reordered['high']['value'] === 3050000.0, 'high must stay with بیشترین after full column reorder');

$missing = str_replace('<th>لحظه‌ای</th>', '<th>قیمت</th>', $html);
try {
    $scraper->parse($missing, now()->toIso8601String());
    fwrite(STDERR, "FAIL: expected missing-header exception\n");
    exit(1);
} catch (RuntimeException $e) {
    // expected
}

echo "estjt scraper checks passed\n";
