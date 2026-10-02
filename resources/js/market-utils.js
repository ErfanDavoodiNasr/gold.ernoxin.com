/**
 * Pure market utility functions for gold.ernoxin.com.
 * Tested via node:test and bundled via Vite.
 */

export const defaultConfig = {
    chartDefaultRange: '1d',
    chartAvailableRanges: ['1h', '2h', '6h', '12h', '1d', '7d', '30d', '90d', '180d', '365d'],
    autoRefreshSeconds: 60,
    themeDefault: 'system',
    themeAccent: '#d9a441',
    sourceName: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    sourceUrl: 'https://www.estjt.ir/price/',
};

export const chartRangeStorageKey = 'chartRange:v2';

export const faNumber = new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 2});
export const faNumberInt = new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 0});
export const faTickTime = new Intl.DateTimeFormat('fa-IR', {hour: '2-digit', minute: '2-digit'});
export const faTickDate = new Intl.DateTimeFormat('fa-IR', {month: '2-digit', day: '2-digit'});
export const faDateTime = new Intl.DateTimeFormat('fa-IR', {dateStyle: 'medium', timeStyle: 'short'});
export const faTooltipDate = new Intl.DateTimeFormat('fa-IR', {year: 'numeric', month: 'long', day: 'numeric'});
export const faTooltipTime = new Intl.DateTimeFormat('fa-IR', {hour: '2-digit', minute: '2-digit', hour12: false});

/**
 * Normalizes an array or map of market items into a clean array.
 * @param {Array|Object|null} items
 * @returns {Array<Object>}
 */
export function normalizeItems(items) {
    const list = Array.isArray(items)
        ? items
        : (items && typeof items === 'object' ? Object.values(items) : []);

    return list.filter((item) => item && (item.id != null || item.key || item.name)).map((item) => {
        const current = item.current == null ? null : Number(item.current);
        return {
            ...item,
            current: Number.isFinite(current) && current > 0 ? current : null,
            stale: Boolean(item.stale),
            direction: ['asc', 'desc', 'none'].includes(item.direction) ? item.direction : 'none',
        };
    });
}

/**
 * Standardizes a range token to a valid string (e.g. '1d', '7d', '12h') or null.
 * @param {string|number|null} range
 * @returns {string|null}
 */
export function rangeKey(range) {
    if (typeof range === 'number' && Number.isFinite(range) && range > 0) {
        return `${Math.trunc(range)}d`;
    }
    const value = String(range || '').trim().toLowerCase();
    return /^\d+[hd]$/.test(value) ? value : null;
}

/**
 * Formats a range token into Persian words (e.g. '۷ روز', '۱۲ ساعت').
 * @param {string|number|null} range
 * @returns {string}
 */
export function rangeLabel(range) {
    const key = rangeKey(range);
    if (!key) return '—';
    const amount = parseInt(key, 10);
    return key.endsWith('h') ? `${formatNumber(amount)} ساعت` : `${formatNumber(amount)} روز`;
}

/**
 * Normalizes available ranges array, removing duplicates and invalid tokens.
 * @param {Array<string>|null} ranges
 * @returns {Array<string>}
 */
export function normalizeAvailableRanges(ranges) {
    const source = ranges?.length ? ranges : defaultConfig.chartAvailableRanges;
    return [...new Set(source.map(rangeKey).filter(Boolean))];
}

/**
 * Ensures range is inside available ranges or falls back gracefully.
 * @param {string|number|null} range
 * @param {Array<string>} availableRanges
 * @param {string} fallback
 * @returns {string}
 */
export function coerceChartRange(range, availableRanges, fallback = defaultConfig.chartDefaultRange) {
    const available = normalizeAvailableRanges(availableRanges);
    const key = rangeKey(range);
    if (key && available.includes(key)) {
        return key;
    }
    const safeFallback = rangeKey(fallback);
    if (safeFallback && available.includes(safeFallback)) {
        return safeFallback;
    }
    return available[0] || defaultConfig.chartDefaultRange;
}

/**
 * Builds merged configuration object from raw server summary payload.
 * @param {Object|null} data
 * @returns {Object}
 */
export function buildConfigFromSummary(data) {
    const nextConfig = {...defaultConfig, ...(data?.config || {})};
    nextConfig.chartDefaultRange = coerceChartRange(
        nextConfig.chartDefaultRange,
        nextConfig.chartAvailableRanges,
        defaultConfig.chartDefaultRange,
    );
    nextConfig.chartAvailableRanges = normalizeAvailableRanges(nextConfig.chartAvailableRanges);
    return nextConfig;
}

/**
 * Calculates client-side cache TTL for history range requests.
 * @param {string} range
 * @returns {number} TTL in milliseconds
 */
export function historyClientTtlMs(range) {
    const key = rangeKey(range);
    if (!key) return 45_000;
    const amount = parseInt(key, 10) || 1;
    if (key.endsWith('h')) return 45_000;
    if (amount >= 30) return 300_000;
    if (amount >= 7) return 120_000;
    return 45_000;
}

/**
 * Returns true if the range is a short intraday or 1-day window.
 * @param {string} range
 * @returns {boolean}
 */
export function isShortHistoryRange(range) {
    const key = rangeKey(range);
    return key ? ['1h', '2h', '6h', '12h', '1d'].includes(key) : false;
}

/**
 * Formats a number with Persian numerals.
 * @param {number|string|null} value
 * @param {Object} options
 * @returns {string}
 */
export function formatNumber(value, options = {}) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    if (options.maximumFractionDigits === 0) return faNumberInt.format(Number(value));
    if (Object.keys(options).length === 0) return faNumber.format(Number(value));
    return new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 2, ...options}).format(Number(value));
}

/**
 * Formats an ISO date into Persian medium date and short time.
 * @param {string|null} value
 * @returns {string}
 */
export function formatDate(value) {
    if (!value) return '—';
    return faDateTime.format(new Date(value));
}

/**
 * Checks whether an item is priced in USD.
 * @param {Object|null} item
 * @returns {boolean}
 */
export function isUsdItem(item) {
    const currency = String(item?.currency || '').trim();
    return item?.name?.includes('انس')
        || currency.toUpperCase() === 'USD'
        || currency === '$'
        || currency.includes('$');
}

/**
 * Extracts numeric value or null.
 * @param {*} value
 * @returns {number|null}
 */
export function displayValue(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
    return Number(value);
}

/**
 * Determines price unit label (تومان، دلار، مظنه / مثقال).
 * @param {Object|null} item
 * @returns {string}
 */
export function priceUnitLabel(item) {
    if (item?.unitLabel) return item.unitLabel;
    if (item?.slug === 'mozaneh') return 'مظنه / مثقال';
    return isUsdItem(item) ? 'دلار' : 'تومان';
}

/**
 * Formats price with appropriate unit label.
 * @param {number|string|null} value
 * @param {Object|null} item
 * @param {Object} options
 * @returns {string}
 */
export function formatPrice(value, item, options = {}) {
    const nextValue = displayValue(value);
    if (nextValue === null) return '—';
    return `${formatNumber(nextValue, options)} ${priceUnitLabel(item)}`;
}

/**
 * Formats price for chart Y-axis labels.
 * @param {number|string|null} value
 * @param {Object|null} item
 * @returns {string}
 */
export function formatAxisPrice(value, item) {
    const nextValue = displayValue(value);
    if (nextValue === null) return '—';
    return formatNumber(nextValue, {maximumFractionDigits: isUsdItem(item) ? 2 : 0});
}

/**
 * Calculates optimal Y-axis width based on value lengths.
 * @param {Object|null} item
 * @param {Array<number>} values
 * @returns {number}
 */
export function chartYAxisWidth(item, values) {
    if (!values?.length) return 72;
    const labels = values.map((val) => formatAxisPrice(val, item));
    const longest = labels.reduce((max, label) => Math.max(max, label.length), 0);
    return Math.min(96, Math.max(58, longest * 7 + 14));
}

/**
 * Returns true if percent value is valid number.
 * @param {*} percent
 * @returns {boolean}
 */
export function hasPercentValue(percent) {
    return percent !== null && percent !== undefined && !Number.isNaN(Number(percent));
}

/**
 * Formats absolute percentage value.
 * @param {number|string|null} value
 * @returns {string}
 */
export function formatPercent(value) {
    if (!hasPercentValue(value)) return '—';
    return formatNumber(Math.abs(Number(value)));
}

/**
 * Resolves visual tone for price change: 'up', 'down', or 'flat'.
 * @param {string|null} direction
 * @param {number|string|null} percent
 * @returns {'up'|'down'|'flat'}
 */
export function changeTone(direction, percent = null) {
    if (direction === 'desc') return 'down';
    if (direction === 'asc') return 'up';
    if (direction === 'none') return 'flat';
    const value = Number(percent);
    if (Number.isFinite(value) && value < 0) return 'down';
    if (Number.isFinite(value) && value > 0) return 'up';
    return 'flat';
}

/**
 * Calculates domain range [min, max] with padding for chart Y-axis.
 * @param {[number, number]} bounds
 * @returns {[number, number]|['dataMin', 'dataMax']}
 */
export function chartDomain([dataMin, dataMax]) {
    if (!Number.isFinite(dataMin) || !Number.isFinite(dataMax)) return ['dataMin', 'dataMax'];
    const span = Math.max(0, dataMax - dataMin);
    const baseline = Math.max(Math.abs(dataMin), Math.abs(dataMax), 1);
    const padding = Math.max(span * 0.18, baseline * 0.00025);
    return [Math.max(0, dataMin - padding), dataMax + padding];
}

/**
 * Formats time or date tick for chart X-axis.
 * @param {string|Date|null} value
 * @param {string} range
 * @returns {string}
 */
export function formatChartTick(value, range) {
    if (!value) return '';
    const date = new Date(value);
    const key = rangeKey(range) || '1d';
    return (key.endsWith('h') || key === '1d' ? faTickTime : faTickDate).format(date);
}

/**
 * Formats tooltip date and time for hovered point.
 * @param {string|Date|null} value
 * @returns {{date: string, time: string}|null}
 */
export function formatChartTooltipDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const datePart = faTooltipDate.format(date);
    const timePart = faTooltipTime.format(date);
    return {
        date: datePart,
        time: timePart,
        datePart,
        timePart,
    };
}

export function resolveSystemTheme() {
    if (typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }
    return 'light';
}

/**
 * Resolves default theme string ('light' or 'dark').
 * @param {string|null} value
 * @returns {'light'|'dark'}
 */
export function resolveThemeDefault(value) {
    if (value === 'light' || value === 'dark') {
        return value;
    }
    return resolveSystemTheme();
}
