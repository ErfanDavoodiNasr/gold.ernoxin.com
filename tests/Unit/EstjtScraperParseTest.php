<?php

namespace Tests\Unit;

use App\Services\EstjtScraper;
use App\Services\PersianNumber;
use RuntimeException;
use Tests\TestCase;

class EstjtScraperParseTest extends TestCase
{
    private EstjtScraper $scraper;

    public function test_parses_normal_fixture_with_stable_identities(): void
    {
        $payload = $this->scraper->parse($this->fixture('normal.html'), '2026-09-15T12:00:00+03:30');

        $this->assertCount(4, $payload['gold']);
        $this->assertCount(5, $payload['coin']);
        $this->assertSame('انس طلا', $payload['gold'][0]['type']);
        $this->assertSame('سکه طرح قدیم', $payload['coin'][0]['type']);
        $this->assertSame(7500000.0, $payload['gold'][2]['current']['value']);
        $this->assertSame('تومان', $payload['gold'][2]['current']['currency']);
        $this->assertSame(2450.5, $payload['gold'][0]['current']['value']);
    }

    public function test_parses_live_estjt_header_contract(): void
    {
        $html = <<<'HTML'
<table>
<thead><tr><th>نوع طلا</th><th>نرخ فعلی</th><th>بالاترین قیمت</th><th>کمترین قیمت</th><th>میانگین دیروز</th><th>تغییر از دیروز</th></tr></thead>
<tbody>
<tr><td>انس طلا</td><td>$ ۴۱۳۷</td><td>—</td><td>—</td><td>$ ۴۱۶۹٫۸۴</td><td><span class="desc">۳۲٫۸۴ (۰٫۷۹)</span></td></tr>
<tr><td>مظنه تهران</td><td>۱۱۲٫۶۷۰٫۰۰۰</td><td>—</td><td>—</td><td>۱۱۰٫۸۷۴٫۶۶۸</td><td><span class="asc">۱٫۷۹۵٫۳۳۲ (۱٫۶۲)</span></td></tr>
<tr><td>طلای ۱۸ عیار</td><td>۲۶٫۰۱۰٫۰۰۰</td><td>—</td><td>—</td><td>۲۵٫۵۹۵٫۵۷۲</td><td><span class="asc">۴۱۴٫۴۲۸ (۱٫۶۲)</span></td></tr>
<tr><td>طلای ۲۴ عیار</td><td>۳۴٫۶۷۶٫۰۰۰</td><td>—</td><td>—</td><td>۳۴٫۱۲۳٫۷۰۸</td><td><span class="asc">۵۵۲٫۲۹۲ (۱٫۶۲)</span></td></tr>
</tbody>
</table>
<table>
<thead><tr><th>نوع سکه</th><th>نرخ فعلی</th><th>بالاترین قیمت</th><th>کمترین قیمت</th><th>میانگین دیروز</th><th>تغییر از دیروز</th></tr></thead>
<tbody>
<tr><td>سکه طرح قدیم</td><td>۲۵۶٫۸۰۰٫۰۰۰</td><td>—</td><td>—</td><td>۲۵۱٫۴۸۳٫۸۲۰</td><td><span class="asc">۵٫۳۱۶٫۱۸۰ (۲٫۱۱)</span></td></tr>
<tr><td>سکه طرح جدید</td><td>۲۶۴٫۰۰۰٫۰۰۰</td><td>—</td><td>—</td><td>۲۵۷٫۵۲۲٫۰۱۶</td><td><span class="asc">۶٫۴۷۷٫۹۸۴ (۲٫۵۲)</span></td></tr>
<tr><td>نیم سکه</td><td>۱۳۷٫۵۰۰٫۰۰۰</td><td>—</td><td>—</td><td>۱۳۴٫۵۶۴٫۴۵۶</td><td><span class="asc">۲٫۹۳۵٫۵۴۴ (۲٫۱۸)</span></td></tr>
<tr><td>ربع سکه</td><td>۷۳٫۸۰۰٫۰۰۰</td><td>—</td><td>—</td><td>۷۲٫۶۰۷٫۶۹۲</td><td><span class="asc">۱٫۱۹۲٫۳۰۸ (۱٫۶۴)</span></td></tr>
<tr><td>سکه یک گرمی</td><td>۳۷٫۰۰۰٫۰۰۰</td><td>—</td><td>—</td><td>۳۷٫۰۰۰٫۰۰۰</td><td>—</td></tr>
</tbody>
</table>
HTML;
        $payload = $this->scraper->parse($html, now()->toIso8601String());
        $this->assertCount(4, $payload['gold']);
        $this->assertCount(5, $payload['coin']);
        $this->assertSame(4137.0, $payload['gold'][0]['current']['value']);
        $this->assertSame('$', $payload['gold'][0]['current']['currency']);
        $this->assertSame('desc', $payload['gold'][0]['change']['direction']);
        $this->assertSame(26010000.0, $payload['gold'][2]['current']['value']);
        $this->assertSame('asc', $payload['gold'][2]['change']['direction']);
        $this->assertSame(264000000.0, $payload['coin'][1]['current']['value']);
        $this->assertSame('none', $payload['coin'][4]['change']['direction']);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/estjt/{$name}"));
    }

    public function test_row_reorder_does_not_swap_identities(): void
    {
        $payload = $this->scraper->parse($this->fixture('reordered.html'), now()->toIso8601String());

        $this->assertSame(
            ['انس طلا', 'مظنه تهران', 'طلای ۱۸ عیار', 'طلای ۲۴ عیار'],
            array_column($payload['gold'], 'type')
        );
        $this->assertSame(
            ['سکه طرح قدیم', 'سکه طرح جدید', 'نیم سکه', 'ربع سکه', 'سکه یک گرمی'],
            array_column($payload['coin'], 'type')
        );
        $this->assertSame(7500000.0, $payload['gold'][2]['current']['value']);
        $this->assertSame(45000000.0, $payload['coin'][2]['current']['value']);
    }

    public function test_column_reorder_still_maps_fields(): void
    {
        $payload = $this->scraper->parse($this->fixture('columns_reordered.html'), now()->toIso8601String());
        $this->assertSame(7500000.0, $payload['gold'][2]['current']['value']);
        $this->assertSame(7550000.0, $payload['gold'][2]['high']['value']);
        $this->assertSame(7450000.0, $payload['gold'][2]['low']['value']);
    }

    public function test_extra_unrelated_table_is_ignored(): void
    {
        $payload = $this->scraper->parse($this->fixture('extra_table.html'), now()->toIso8601String());
        $this->assertCount(4, $payload['gold']);
        $this->assertCount(5, $payload['coin']);
    }

    public function test_duplicate_label_keeps_last_known_without_unknown_overwrite(): void
    {
        $payload = $this->scraper->parse($this->fixture('duplicate_and_unknown.html'), now()->toIso8601String());
        $eighteen = collect($payload['gold'])->first(
            fn($row) => PersianNumber::label($row['type']) === PersianNumber::label('طلای ۱۸ عیار')
        );
        $this->assertSame(99999999.0, $eighteen['current']['value']);
        $keys = array_map(fn($row) => PersianNumber::label($row['type']), $payload['gold']);
        $this->assertNotContains(PersianNumber::label('شمش ناشناس'), $keys);
    }

    public function test_rial_fixture_keeps_raw_rial_values_for_normalizer(): void
    {
        $payload = $this->scraper->parse($this->fixture('rial_values.html'), now()->toIso8601String());
        $this->assertSame(75000000.0, $payload['gold'][2]['current']['value']);
        $this->assertSame('ریال', $payload['gold'][2]['current']['currency']);
    }

    public function test_missing_coin_table_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->scraper->parse($this->fixture('missing_coin.html'), now()->toIso8601String());
    }

    public function test_missing_gold_table_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->scraper->parse($this->fixture('missing_gold.html'), now()->toIso8601String());
    }

    public function test_unrelated_html_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->scraper->parse($this->fixture('unrelated.html'), now()->toIso8601String());
    }

    public function test_maintenance_page_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->scraper->parse($this->fixture('maintenance.html'), now()->toIso8601String());
    }

    public function test_looks_blocked_detects_cloudflare_captcha(): void
    {
        $method = new \ReflectionMethod(EstjtScraper::class, 'looksBlocked');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->scraper, $this->fixture('cloudflare.html')));
        $this->assertFalse($method->invoke($this->scraper, $this->fixture('normal.html')));
    }

    public function test_empty_current_still_emits_row_with_null_value(): void
    {
        $payload = $this->scraper->parse($this->fixture('empty_and_invalid.html'), now()->toIso8601String());
        $eighteen = collect($payload['gold'])->first(
            fn($row) => PersianNumber::label($row['type']) === PersianNumber::label('طلای ۱۸ عیار')
        );
        $twentyFour = collect($payload['gold'])->first(
            fn($row) => PersianNumber::label($row['type']) === PersianNumber::label('طلای ۲۴ عیار')
        );
        $this->assertNull($eighteen['current']['value']);
        $this->assertNull($twentyFour['current']['value']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->scraper = new EstjtScraper();
    }
}
