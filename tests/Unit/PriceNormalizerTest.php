<?php

namespace Tests\Unit;

use App\Services\PriceNormalizer;
use Tests\TestCase;

class PriceNormalizerTest extends TestCase
{
    private PriceNormalizer $normalizer;

    public function test_explicit_rial_divides_by_ten_once(): void
    {
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(75300000.0, 'ریال', null));
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(75300000.0, 'ریال ایران', null));
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(75300000.0, 'IRR', null));
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(75300000.0, 'Rial', null));
    }

    public function test_explicit_toman_never_divides(): void
    {
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(7530000.0, 'تومان', null));
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(7530000.0, ' تومان ', null));
        $this->assertSame(7530000.0, $this->normalizer->normalizeValue(7530000.0, 'تومن', null));
    }

    public function test_usd_never_applies_rial_toman_scaling(): void
    {
        $this->assertSame(2450.5, $this->normalizer->normalizeValue(2450.5, '$', 7500000.0, true));
        $this->assertSame(2450.5, $this->normalizer->normalizeValue(2450.5, 'دلار آمریکا', 7500000.0, true));
        $this->assertSame(2450.5, $this->normalizer->normalizeValue(2450.5, 'USD', 7500000.0, true));
        // Even a 10× USD move must not be treated as rial/toman conversion when flagged USD.
        $this->assertSame(24505.0, $this->normalizer->normalizeValue(24505.0, 'USD', 2450.5, true));
    }

    public function test_unknown_currency_rejects_rial_spike_band(): void
    {
        $reference = 7_500_000.0;
        $this->assertNull($this->normalizer->normalizeValue(75_000_000.0, null, $reference));
        $this->assertNull($this->normalizer->normalizeValue(75_000_000.0, '???', $reference));
        $this->assertSame(7_500_000.0, $this->normalizer->normalizeValue(7_500_000.0, null, $reference));
    }

    /** @dataProvider spikeRatios */
    public function test_spike_band_thresholds(float $ratio, bool $isSpike): void
    {
        $reference = 7_500_000.0;
        $value = $reference * $ratio;
        $this->assertSame(
            $isSpike,
            $this->normalizer->looksLikeRialSpike($value, $reference),
            "ratio {$ratio}"
        );
    }

    public function spikeRatios(): array
    {
        return [
            '7.9x' => [7.9, false],
            '8x' => [8.0, true],
            '9x' => [9.0, true],
            '10x' => [10.0, true],
            '11x' => [11.0, true],
            '12x' => [12.0, true],
            '12.1x' => [12.1, false],
        ];
    }

    /** @dataProvider dipRatios */
    public function test_toman_dip_band(float $ratio, bool $isDip): void
    {
        $reference = 7_500_000.0;
        $value = $reference * $ratio;
        $this->assertSame($isDip, $this->normalizer->looksLikeTomanDip($value, $reference), "ratio {$ratio}");
    }

    public function dipRatios(): array
    {
        return [
            '1/7.9' => [1 / 7.9, false],
            '1/8' => [1 / 8.0, true],
            '1/10' => [0.1, true],
            '1/12' => [1 / 12.0, true],
            '1/12.1' => [1 / 12.1, false],
        ];
    }

    public function test_unknown_currency_rejects_dip_band(): void
    {
        $reference = 7_500_000.0;
        $this->assertNull($this->normalizer->normalizeValue(750_000.0, null, $reference));
    }

    public function test_zero_and_negative_rejected(): void
    {
        $this->assertNull($this->normalizer->normalizeValue(0.0, 'تومان', null));
        $this->assertNull($this->normalizer->normalizeValue(-100.0, 'تومان', null));
        $this->assertNull($this->normalizer->normalizeValue(null, 'تومان', null));
    }

    public function test_normalize_row_applies_direction_to_change(): void
    {
        $row = [
            'current' => ['value' => 7_500_000.0, 'currency' => 'تومان'],
            'high' => ['value' => 7_600_000.0, 'currency' => 'تومان'],
            'low' => ['value' => 7_400_000.0, 'currency' => 'تومان'],
            'yesterdayAvg' => ['value' => 7_450_000.0, 'currency' => 'تومان'],
            'change' => ['value' => 50_000.0, 'percent' => 0.67, 'direction' => 'desc'],
        ];

        $normalized = $this->normalizer->normalizeRow($row, 7_500_000.0, false);
        $this->assertNotNull($normalized);
        $this->assertSame(-50000.0, $normalized['change']['value']);
        $this->assertSame(-0.67, $normalized['change']['percent']);
    }

    public function test_repair_against_reference_only_in_spike_band(): void
    {
        $this->assertSame(7_500_000.0, $this->normalizer->repairAgainstReference(75_000_000.0, 7_500_000.0));
        $this->assertSame(7_500_000.0, $this->normalizer->repairAgainstReference(750_000.0, 7_500_000.0));
        $this->assertSame(8_000_000.0, $this->normalizer->repairAgainstReference(8_000_000.0, 7_500_000.0));
    }

    public function test_currency_detectors_are_conservative(): void
    {
        $this->assertTrue($this->normalizer->isTomanCurrency('تومان'));
        $this->assertTrue($this->normalizer->isTomanCurrency('تومن'));
        $this->assertFalse($this->normalizer->isTomanCurrency(null));
        $this->assertFalse($this->normalizer->isTomanCurrency(''));
        $this->assertFalse($this->normalizer->isRialCurrency('تومان'));
        $this->assertTrue($this->normalizer->isRialCurrency('ریال'));
        $this->assertFalse($this->normalizer->isUsdItem(null));
        $this->assertTrue($this->normalizer->isUsdItem('USD'));
        $this->assertTrue($this->normalizer->isUsdItem('دلار'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'gold.outlier.spike_min' => 8.0,
            'gold.outlier.spike_max' => 12.0,
        ]);
        $this->normalizer = new PriceNormalizer();
    }
}
