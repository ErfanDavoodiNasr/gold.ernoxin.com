<?php

namespace Tests\Unit;

use App\Services\PersianNumber;
use PHPUnit\Framework\TestCase;

class PersianNumberTest extends TestCase
{
    /** @dataProvider numericEquivalents */
    public function test_parses_equivalent_numeric_forms(string $input, float $expected): void
    {
        $this->assertSame($expected, PersianNumber::numeric($input));
    }

    public function numericEquivalents(): array
    {
        return [
            'latin commas' => ['7,530,000', 7530000.0],
            'persian digits' => ['۷۵۳۰۰۰۰', 7530000.0],
            'persian thousands' => ['۷٬۵۳۰٬۰۰۰', 7530000.0],
            'arabic digits' => ['٧٥٣٠٠٠٠', 7530000.0],
            'spaces' => ['7 530 000', 7530000.0],
            'nbsp' => ["7\u{00A0}530\u{00A0}000", 7530000.0],
            'plain' => ['7530000', 7530000.0],
            'decimal latin' => ['12.5', 12.5],
            'decimal persian' => ['۱۲٫۵', 12.5],
            'negative' => ['-1,200', -1200.0],
            'plus' => ['+1500', 1500.0],
        ];
    }

    public function test_malformed_numeric_fails_safely(): void
    {
        $this->assertNull(PersianNumber::numeric(''));
        $this->assertNull(PersianNumber::numeric('—'));
        $this->assertNull(PersianNumber::numeric('abc'));
        $this->assertNull(PersianNumber::numeric('%'));
    }

    public function test_currency_and_value_extracts_rial_and_toman(): void
    {
        [$value, $currency] = PersianNumber::currencyAndValue('۷٬۵۳۰٬۰۰۰ تومان');
        $this->assertSame(7530000.0, $value);
        $this->assertSame('تومان', $currency);

        [$rialValue, $rialCurrency] = PersianNumber::currencyAndValue('75300000 ریال');
        $this->assertSame(75300000.0, $rialValue);
        $this->assertSame('ریال', $rialCurrency);

        [$usdValue, $usdCurrency] = PersianNumber::currencyAndValue('$2,450.50');
        $this->assertSame(2450.5, $usdValue);
        $this->assertSame('$', $usdCurrency);
    }

    public function test_label_normalizes_arabic_yeh_kaf_and_zwnj(): void
    {
        $this->assertSame(
            PersianNumber::label('سكه امامي'),
            PersianNumber::label("سکه\u{200c}امامی")
        );
        $this->assertSame('طلای 18 عیار', PersianNumber::label('طلاي ١٨ عيار'));
        $this->assertSame(
            PersianNumber::label('طلای ۱۸ عیار'),
            PersianNumber::label('طلاي ١٨ عيار')
        );
    }

    public function test_change_direction_wins_over_raw_sign(): void
    {
        $asc = PersianNumber::change('+120 (1.2%)', 'desc');
        $this->assertSame('desc', $asc['direction']);
        $this->assertSame(-120.0, $asc['value']);
        $this->assertSame(-1.2, $asc['percent']);

        $desc = PersianNumber::change('-120 (1.2%)', 'asc');
        $this->assertSame('asc', $desc['direction']);
        $this->assertSame(120.0, $desc['value']);
        $this->assertSame(1.2, $desc['percent']);
    }

    public function test_signed_by_direction(): void
    {
        $this->assertSame(-5.0, PersianNumber::signedByDirection(5, 'desc'));
        $this->assertSame(5.0, PersianNumber::signedByDirection(-5, 'asc'));
        $this->assertSame(5.0, PersianNumber::signedByDirection(5, 'none'));
        $this->assertNull(PersianNumber::signedByDirection(null, 'desc'));
    }
}
