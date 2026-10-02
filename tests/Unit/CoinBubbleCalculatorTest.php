<?php

namespace Tests\Unit;

use App\Models\PricePoint;
use App\Services\CoinBubbleCalculator;
use App\Support\MarketItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Known-answer bubble tests.
 *
 * Independent derivation (not copied from production code):
 * intrinsic = weight_g * (purity / reference_purity) * reference_18k_price_toman
 * For full Bahar/Emami: weight=8.133, purity=0.900, ref_purity=0.750
 * factor = 8.133 * (0.900/0.750) = 8.133 * 1.2 = 9.7596
 */
class CoinBubbleCalculatorTest extends TestCase
{
    public function test_known_answer_emami_bubble(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00', 'Asia/Tehran');
        $items = new Collection([
            new MarketItem(
                id: 3,
                key: 'طلای ۱۸ عیار',
                name: 'طلای ۱۸ عیار',
                category: 'gold',
                currency: 'تومان',
                latestPrice: new PricePoint([
                    'current_value' => 7_500_000,
                    'fetched_at' => $now,
                    'direction' => 'asc',
                ]),
                slug: '18k',
            ),
            new MarketItem(
                id: 6,
                key: 'سکه طرح جدید',
                name: 'سکه طرح جدید',
                category: 'coin',
                currency: 'تومان',
                latestPrice: new PricePoint([
                    'current_value' => 82_000_000,
                    'fetched_at' => $now,
                    'direction' => 'asc',
                ]),
                slug: 'emami',
            ),
        ]);

        $result = (new CoinBubbleCalculator())->forItems($items);
        $bubble = $result['سکه طرح جدید'];

        // 9.7596 * 7_500_000 = 73_197_000
        $this->assertSame(82000000.0, $bubble['market']);
        $this->assertSame(73197000.0, $bubble['intrinsic']);
        $this->assertSame(8803000.0, $bubble['bubble']);
        $this->assertEqualsWithDelta(12.03, $bubble['bubble_percent'], 0.01);
    }

    public function test_unsynchronized_timestamps_return_unavailable(): void
    {
        $coinAt = Carbon::parse('2026-09-15 12:00:00', 'Asia/Tehran');
        $goldAt = $coinAt->copy()->subSeconds(301);
        $items = new Collection([
            new MarketItem(3, 'طلای ۱۸ عیار', 'طلای ۱۸ عیار', 'gold', 'تومان', new PricePoint([
                'current_value' => 7_500_000,
                'fetched_at' => $goldAt,
                'direction' => 'none',
            ]), '18k'),
            new MarketItem(6, 'سکه طرح جدید', 'سکه طرح جدید', 'coin', 'تومان', new PricePoint([
                'current_value' => 82_000_000,
                'fetched_at' => $coinAt,
                'direction' => 'none',
            ]), 'emami'),
        ]);

        $bubble = (new CoinBubbleCalculator())->forItems($items)['سکه طرح جدید'];
        $this->assertNull($bubble['intrinsic']);
        $this->assertNull($bubble['bubble']);
        $this->assertNotEmpty($bubble['unavailable_reason']);
    }

    public function test_within_sync_threshold_is_available(): void
    {
        $coinAt = Carbon::parse('2026-09-15 12:00:00', 'Asia/Tehran');
        $goldAt = $coinAt->copy()->subSeconds(30);
        $items = new Collection([
            new MarketItem(3, 'طلای ۱۸ عیار', 'طلای ۱۸ عیار', 'gold', 'تومان', new PricePoint([
                'current_value' => 7_500_000,
                'fetched_at' => $goldAt,
            ]), '18k'),
            new MarketItem(6, 'سکه طرح جدید', 'سکه طرح جدید', 'coin', 'تومان', new PricePoint([
                'current_value' => 82_000_000,
                'fetched_at' => $coinAt,
            ]), 'emami'),
        ]);

        $bubble = (new CoinBubbleCalculator())->forItems($items)['سکه طرح جدید'];
        $this->assertNotNull($bubble['bubble']);
    }

    public function test_zero_reference_returns_empty(): void
    {
        $now = now();
        $items = new Collection([
            new MarketItem(3, 'طلای ۱۸ عیار', 'طلای ۱۸ عیار', 'gold', 'تومان', new PricePoint([
                'current_value' => 0,
                'fetched_at' => $now,
            ]), '18k'),
            new MarketItem(6, 'سکه طرح جدید', 'سکه طرح جدید', 'coin', 'تومان', new PricePoint([
                'current_value' => 82_000_000,
                'fetched_at' => $now,
            ]), 'emami'),
        ]);

        $this->assertSame([], (new CoinBubbleCalculator())->forItems($items));
    }

    public function test_gram_coin_known_answer(): void
    {
        // weight=1.0, purity=0.900, ref=0.750 → factor=1.2
        // intrinsic = 1.2 * 7_500_000 = 9_000_000
        $now = now();
        $items = new Collection([
            new MarketItem(3, 'طلای ۱۸ عیار', 'طلای ۱۸ عیار', 'gold', 'تومان', new PricePoint([
                'current_value' => 7_500_000,
                'fetched_at' => $now,
            ]), '18k'),
            new MarketItem(9, 'سکه یک گرمی', 'سکه یک گرمی', 'coin', 'تومان', new PricePoint([
                'current_value' => 12_000_000,
                'fetched_at' => $now,
            ]), 'gram'),
        ]);

        $bubble = (new CoinBubbleCalculator())->forItems($items)['سکه یک گرمی'];
        $this->assertSame(9000000.0, $bubble['intrinsic']);
        $this->assertSame(3000000.0, $bubble['bubble']);
        $this->assertSame(33.33, $bubble['bubble_percent']);
    }
}
