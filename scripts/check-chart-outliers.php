<?php

/**
 * Self-check: chart OutlierFilter (neighbor) + window anchors / analytics.
 * Run: php scripts/check-chart-outliers.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OutlierFilter;
use App\Services\PriceHistoryQuery;
use Illuminate\Support\Carbon;

function point(float $value, string $at): object
{
    return (object)[
        'current_value' => $value,
        'fetched_at' => Carbon::parse($at),
    ];
}

$filter = app(OutlierFilter::class);

// Poisoned first point (10×) must not wipe the rest (old 8–12× band did).
$poisoned = collect([
    point(10_000_000, '2026-01-01 00:00:00'),
    point(1_000_000, '2026-01-01 01:00:00'),
    point(1_010_000, '2026-01-01 02:00:00'),
    point(1_020_000, '2026-01-01 03:00:00'),
    point(990_000, '2026-01-01 04:00:00'),
]);
$kept = $filter->filter($poisoned, fn($p) => $p->current_value);
assert($kept->count() >= 4, 'neighbor filter must keep healthy points after a 10× spike');
assert(!$kept->contains(fn($p) => (float)$p->current_value === 10_000_000.0), '10× spike must be dropped');

// Mid-series spike
$mid = collect([
    point(1_000_000, '2026-01-01 00:00:00'),
    point(1_005_000, '2026-01-01 01:00:00'),
    point(12_000_000, '2026-01-01 02:00:00'),
    point(1_010_000, '2026-01-01 03:00:00'),
    point(1_015_000, '2026-01-01 04:00:00'),
]);
$midKept = $filter->filter($mid, fn($p) => $p->current_value);
assert($midKept->count() === 4, 'isolated mid spike dropped, rest kept');
assert(!$midKept->contains(fn($p) => (float)$p->current_value === 12_000_000.0));

$query = app(PriceHistoryQuery::class);
$windowStart = Carbon::parse('2026-01-01 00:00:00');
$series = collect([
    point(100, '2026-01-01 00:05:00'),
    point(110, '2026-01-15 00:00:00'),
    point(120, '2026-01-30 00:00:00'),
]);
[$open, $close] = $query->windowAnchors($series, $windowStart);
assert($open === 100.0, 'open nearest windowStart');
assert($close === 120.0, 'close is last');

$analytics = $query->fetchAnalytics($series, $open, $close);
assert($analytics['change'] === 20.0);
assert($analytics['changePercent'] === 20.0);
assert($analytics['min'] === 100.0);
assert($analytics['max'] === 120.0);

// Sample must not affect analytics when anchors are passed explicitly
$sampled = collect([point(110, '2026-01-15 00:00:00'), point(120, '2026-01-30 00:00:00')]);
$fromSample = $query->fetchAnalytics($sampled); // wrong if used for change
$withAnchors = $query->fetchAnalytics($sampled, $open, $close);
assert($fromSample['changePercent'] !== 20.0 || $fromSample['change'] !== 20.0, 'sampled first≠open');
assert($withAnchors['change'] === 20.0 && $withAnchors['changePercent'] === 20.0, 'anchors preserve window change');

echo "chart outlier + analytics checks passed\n";
