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
