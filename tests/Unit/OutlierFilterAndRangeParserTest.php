<?php

namespace Tests\Unit;

use App\Services\OutlierFilter;
use App\Services\RangeParser;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OutlierFilterAndRangeParserTest extends TestCase
{
    public function test_outlier_keeps_legitimate_moves_under_threshold(): void
    {
        config([
            'gold.outlier.chart_neighbor_radius' => 2,
            'gold.outlier.chart_max_relative_deviation' => 0.15,
        ]);

        $filter = new OutlierFilter();
        $points = collect([
            (object)['current_value' => 100],
            (object)['current_value' => 105], // +5%
            (object)['current_value' => 110], // +10% vs neighbors still within sustained move
            (object)['current_value' => 112],
            (object)['current_value' => 111],
        ]);

        $filtered = $filter->filter($points, fn($p) => $p->current_value);
        $this->assertCount(5, $filtered);
    }

    public function test_outlier_drops_single_tenfold_spike(): void
    {
        config([
            'gold.outlier.chart_neighbor_radius' => 2,
            'gold.outlier.chart_max_relative_deviation' => 0.15,
        ]);

        $filter = new OutlierFilter();
        $points = collect([
            (object)['current_value' => 7_500_000],
            (object)['current_value' => 7_520_000],
            (object)['current_value' => 75_000_000], // 10× unit corruption
            (object)['current_value' => 7_510_000],
            (object)['current_value' => 7_505_000],
        ]);

        $filtered = $filter->filter($points, fn($p) => $p->current_value);
        $this->assertCount(4, $filtered);
        $this->assertFalse($filtered->contains(fn($p) => (float)$p->current_value === 75_000_000.0));
    }

    public function test_range_parser_allowlist_and_bounds(): void
    {
        config([
            'gold.chart_available_ranges' => ['1h', '1d', '7d', '365d'],
            'gold.history_max_days' => 365,
        ]);
        $parser = new RangeParser();

        $this->assertSame(60, $parser->tryParse('1h')['minutes']);
        $this->assertSame(1440, $parser->tryParse('1d')['minutes']);
        $this->assertSame(365 * 1440, $parser->tryParse('365d')['minutes']);
        $this->assertNull($parser->tryParse('2h'));
        $this->assertNull($parser->tryParse('9999d'));
        $this->assertNull($parser->tryParse('nope'));
        $this->assertNull($parser->tryParse(''));
        $this->assertNull($parser->tryParse('-1d'));
    }

    public function test_range_parser_caps_to_history_max_days(): void
    {
        config([
            'gold.chart_available_ranges' => ['400d'],
            'gold.history_max_days' => 365,
        ]);
        $parser = new RangeParser();
        $parsed = $parser->tryParse('400d');
        $this->assertNotNull($parsed);
        $this->assertSame(365 * 1440, $parsed['minutes']);
    }
}
