<?php

namespace Tests\Feature;

use App\Services\EstjtScraper;
use App\Services\PersianNumber;
use App\Services\PriceIngestor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IngestPipelineTest extends TestCase
{
    public function test_fixture_parse_normalize_store_rejects_empty_prices(): void
    {
        $scraper = new EstjtScraper();
        $payload = $scraper->parse(
            file_get_contents(base_path('tests/Fixtures/estjt/empty_and_invalid.html')),
            now()->toIso8601String()
        );

        $ingestor = app(PriceIngestor::class);
        $count = $ingestor->store($payload);

        // 2 gold rows invalid + 2 gold valid + 5 coins = 7
        $this->assertSame(7, $count);
        $this->assertSame(0, DB::table('price_points')->where('item_key', PersianNumber::label('طلای ۱۸ عیار'))->count());
        $this->assertSame(1, DB::table('price_points')->where('item_key', PersianNumber::label('انس طلا'))->count());
    }

    public function test_rial_fixture_stores_toman_scaled_values(): void
    {
        $scraper = new EstjtScraper();
        $payload = $scraper->parse(
            file_get_contents(base_path('tests/Fixtures/estjt/rial_values.html')),
            now()->toIso8601String()
        );

        $count = app(PriceIngestor::class)->store($payload);
        $this->assertSame(9, $count);

        $eighteen = DB::table('price_points')
            ->where('item_key', PersianNumber::label('طلای ۱۸ عیار'))
            ->value('current_value');
        $this->assertEqualsWithDelta(7_500_000.0, (float)$eighteen, 0.01);
    }

    public function test_tenfold_unknown_currency_rejected_against_reference(): void
    {
        // Seed reference at 7.5M toman
        DB::table('price_points')->insert([
            'item_key' => PersianNumber::label('طلای ۱۸ عیار'),
            'current_value' => 7_500_000,
            'high_value' => 7_500_000,
            'low_value' => 7_500_000,
            'direction' => 'none',
            'fetched_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = [
            'type' => 'طلای ۱۸ عیار',
            'category' => 'gold',
            'current' => ['value' => 75_000_000.0, 'currency' => null, 'raw' => '75000000'],
            'high' => ['value' => 75_000_000.0, 'currency' => null],
            'low' => ['value' => 75_000_000.0, 'currency' => null],
            'yesterdayAvg' => ['value' => 75_000_000.0, 'currency' => null],
            'change' => ['value' => 0, 'percent' => 0, 'direction' => 'none'],
        ];

        $payload = [
            'source' => ['fetchedAt' => now()->toIso8601String()],
            'gold' => [$row],
            'coin' => [],
        ];

        // Only one item → below 50% of 9 expected if going through fetchAndStore;
        // store() alone returns 0 when rejected.
        $count = app(PriceIngestor::class)->store($payload);
        $this->assertSame(0, $count);
        $this->assertSame(1, DB::table('price_points')->count());
    }

    public function test_upsert_same_fetched_at_does_not_duplicate(): void
    {
        $scraper = new EstjtScraper();
        $fetchedAt = '2026-09-15T12:00:00+03:30';
        $payload = $scraper->parse(file_get_contents(base_path('tests/Fixtures/estjt/normal.html')), $fetchedAt);

        $ingestor = app(PriceIngestor::class);
        $this->assertSame(9, $ingestor->store($payload));
        $this->assertSame(9, $ingestor->store($payload));
        $this->assertSame(9, DB::table('price_points')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('price_points', function ($table) {
            $table->id();
            $table->string('item_key');
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('high_value', 18, 4)->nullable();
            $table->decimal('low_value', 18, 4)->nullable();
            $table->decimal('yesterday_avg_value', 18, 4)->nullable();
            $table->decimal('change_value', 18, 4)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->string('direction')->default('none');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['item_key', 'fetched_at']);
        });
    }
}
