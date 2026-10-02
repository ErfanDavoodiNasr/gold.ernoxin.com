import {describe, it} from 'node:test';
import assert from 'node:assert/strict';
import {
    defaultConfig,
    normalizeItems,
    rangeKey,
    rangeLabel,
    normalizeAvailableRanges,
    coerceChartRange,
    buildConfigFromSummary,
    historyClientTtlMs,
    isShortHistoryRange,
    formatNumber,
    formatPrice,
    formatAxisPrice,
    formatPercent,
    changeTone,
    isUsdItem,
    priceUnitLabel,
    chartDomain,
    formatChartTick,
    formatChartTooltipDate,
    chartYAxisWidth,
} from '../../resources/js/market-utils.js';

describe('Market Utilities - Frontend Unit Test Suite', () => {

    describe('normalizeItems', () => {
        it('handles null, undefined, and non-array inputs gracefully', () => {
            assert.deepEqual(normalizeItems(null), []);
            assert.deepEqual(normalizeItems(undefined), []);
            assert.deepEqual(normalizeItems('invalid'), []);
            assert.deepEqual(normalizeItems({}), []);
        });

        it('normalizes valid and edge-case items', () => {
            const raw = [
                {id: 1, name: 'انس طلا', current: 2500, direction: 'asc', stale: 0},
                {id: 2, name: 'سکه', current: '30000000', direction: 'invalid_dir', stale: 1},
                {id: 3, name: 'قیمت نامعتبر', current: -500, direction: 'desc'},
                {id: 4, name: 'قیمت صفر', current: 0},
                {id: null, name: null}, // Should be filtered out
            ];

            const normalized = normalizeItems(raw);
            assert.equal(normalized.length, 4);

            assert.equal(normalized[0].current, 2500);
            assert.equal(normalized[0].direction, 'asc');
            assert.equal(normalized[0].stale, false);

            assert.equal(normalized[1].current, 30_000_000);
            assert.equal(normalized[1].direction, 'none'); // fallback
            assert.equal(normalized[1].stale, true);

            assert.equal(normalized[2].current, null); // negative filtered
            assert.equal(normalized[2].direction, 'desc');

            assert.equal(normalized[3].current, null); // zero filtered
        });
    });

    describe('rangeKey & rangeLabel', () => {
        it('converts numbers to day strings', () => {
            assert.equal(rangeKey(1), '1d');
            assert.equal(rangeKey(7), '7d');
            assert.equal(rangeKey(30.8), '30d');
        });

        it('validates range strings', () => {
            assert.equal(rangeKey('1h'), '1h');
            assert.equal(rangeKey('12h'), '12h');
            assert.equal(rangeKey('7D'), '7d');
            assert.equal(rangeKey('365d'), '365d');
            assert.equal(rangeKey('invalid'), null);
            assert.equal(rangeKey(''), null);
            assert.equal(rangeKey(null), null);
        });

        it('formats Persian range labels', () => {
            const label7d = rangeLabel('7d');
            assert.match(label7d, /روز/);

            const label12h = rangeLabel('12h');
            assert.match(label12h, /ساعت/);

            assert.equal(rangeLabel('invalid'), '—');
        });
    });

    describe('normalizeAvailableRanges & coerceChartRange', () => {
        it('deduplicates and cleans available ranges', () => {
            const input = ['1d', '7d', '1d', 'invalid', '30d'];
            const res = normalizeAvailableRanges(input);
            assert.deepEqual(res, ['1d', '7d', '30d']);
        });

        it('falls back to defaultConfig when input is empty', () => {
            const res = normalizeAvailableRanges([]);
            assert.deepEqual(res, defaultConfig.chartAvailableRanges);
        });

        it('coerces chart ranges to valid available options', () => {
            const available = ['1d', '7d', '30d'];
            assert.equal(coerceChartRange('7d', available), '7d');
            assert.equal(coerceChartRange('99d', available, '30d'), '30d');
            assert.equal(coerceChartRange('invalid', available, 'unknown'), '1d');
        });
    });

    describe('buildConfigFromSummary', () => {
        it('merges server config with defaults', () => {
            const config = buildConfigFromSummary({
                config: {
                    chartDefaultRange: '7d',
                    autoRefreshSeconds: 30,
                    sourceName: 'منبع تستی',
                },
            });

            assert.equal(config.chartDefaultRange, '7d');
            assert.equal(config.autoRefreshSeconds, 30);
            assert.equal(config.sourceName, 'منبع تستی');
            assert.equal(config.themeAccent, defaultConfig.themeAccent);
        });

        it('handles null or missing config payload', () => {
            const config = buildConfigFromSummary(null);
            assert.equal(config.chartDefaultRange, defaultConfig.chartDefaultRange);
            assert.equal(config.autoRefreshSeconds, defaultConfig.autoRefreshSeconds);
        });
    });

    describe('historyClientTtlMs & isShortHistoryRange', () => {
        it('calculates correct TTL for ranges', () => {
            assert.equal(historyClientTtlMs('1h'), 45_000);
            assert.equal(historyClientTtlMs('1d'), 45_000);
            assert.equal(historyClientTtlMs('7d'), 120_000);
            assert.equal(historyClientTtlMs('30d'), 300_000);
            assert.equal(historyClientTtlMs('365d'), 300_000);
        });

        it('identifies short history ranges', () => {
            assert.equal(isShortHistoryRange('1h'), true);
            assert.equal(isShortHistoryRange('6h'), true);
            assert.equal(isShortHistoryRange('1d'), true);
            assert.equal(isShortHistoryRange('7d'), false);
            assert.equal(isShortHistoryRange('30d'), false);
        });
    });

    describe('formatNumber, formatPrice, formatPercent', () => {
        it('formats numbers with Persian digits', () => {
            const num = formatNumber(1250000, {maximumFractionDigits: 0});
            assert.notEqual(num, '—');
            assert.equal(formatNumber(null), '—');
            assert.equal(formatNumber(NaN), '—');
        });

        it('identifies USD items correctly', () => {
            assert.equal(isUsdItem({name: 'انس طلا'}), true);
            assert.equal(isUsdItem({currency: '$'}), true);
            assert.equal(isUsdItem({currency: 'USD'}), true);
            assert.equal(isUsdItem({name: 'طلای ۱۸ عیار', currency: null}), false);
        });

        it('formats price with appropriate unit label', () => {
            const gold18k = {name: 'طلای ۱۸ عیار', currency: null};
            const goldOunce = {name: 'انس طلا', currency: '$'};
            const mozaneh = {slug: 'mozaneh', name: 'مظنه تهران'};

            assert.equal(priceUnitLabel(gold18k), 'تومان');
            assert.equal(priceUnitLabel(goldOunce), 'دلار');
            assert.equal(priceUnitLabel(mozaneh), 'مظنه / مثقال');

            assert.match(formatPrice(25000000, gold18k), /تومان/);
            assert.match(formatPrice(2700.5, goldOunce), /دلار/);
            assert.equal(formatPrice(null, gold18k), '—');
        });

        it('formats percentages correctly', () => {
            assert.notEqual(formatPercent(2.45), '—');
            assert.notEqual(formatPercent(-1.2), '—');
            assert.equal(formatPercent(null), '—');
        });
    });

    describe('changeTone', () => {
        it('resolves visual direction tones', () => {
            assert.equal(changeTone('asc'), 'up');
            assert.equal(changeTone('desc'), 'down');
            assert.equal(changeTone('none'), 'flat');
            assert.equal(changeTone(null, 1.5), 'up');
            assert.equal(changeTone(null, -2.1), 'down');
            assert.equal(changeTone(null, 0), 'flat');
        });
    });

    describe('chartDomain & formatChartTooltipDate', () => {
        it('pads domain bounds correctly', () => {
            const [min, max] = chartDomain([1000, 2000]);
            assert.ok(min < 1000);
            assert.ok(max > 2000);
            assert.ok(min >= 0);
        });

        it('guards non-finite bounds', () => {
            const domain = chartDomain([NaN, Infinity]);
            assert.deepEqual(domain, ['dataMin', 'dataMax']);
        });

        it('formats tooltip date and time', () => {
            const tooltip = formatChartTooltipDate('2026-10-02T10:30:00Z');
            assert.ok(tooltip !== null);
            assert.ok(typeof tooltip.date === 'string');
            assert.ok(typeof tooltip.time === 'string');
            assert.equal(formatChartTooltipDate('invalid-date'), null);
            assert.equal(formatChartTooltipDate(null), null);
        });

        it('calculates dynamic chart Y-axis width within reasonable limits', () => {
            const width = chartYAxisWidth({currency: 'تومان'}, [25000000, 30000000]);
            assert.ok(width >= 58 && width <= 96);
        });
    });
});
