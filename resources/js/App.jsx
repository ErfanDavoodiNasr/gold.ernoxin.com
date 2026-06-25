import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    Coins,
    Minus,
    Moon,
    RefreshCw,
    Search,
    Sun,
    TrendingUp,
    WalletCards
} from 'lucide-react';
import '../css/app.css';

const defaultConfig = {
    chartDefaultRange: '1d',
    chartAvailableRanges: ['1h', '2h', '6h', '12h', '1d', '7d', '30d', '90d', '180d', '365d'],
    autoRefreshSeconds: 60,
    themeDefault: 'system',
    themeAccent: '#d9a441',
    sourceName: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    sourceUrl: 'https://www.estjt.ir/price/',
};

const etagStore = new Map();

function normalizeItems(items) {
    if (Array.isArray(items)) return items;
    if (items && typeof items === 'object') return Object.values(items);
    return [];
}

function readEmbeddedSummary() {
    const node = document.getElementById('market-summary');
    if (!node?.textContent) return null;
    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
}

const embeddedSummary = readEmbeddedSummary();

function buildConfigFromSummary(data) {
    const nextConfig = {...defaultConfig, ...(data?.config || {})};
    nextConfig.chartDefaultRange = rangeKey(nextConfig.chartDefaultRange);
    nextConfig.chartAvailableRanges = (nextConfig.chartAvailableRanges || defaultConfig.chartAvailableRanges).map(rangeKey);
    return nextConfig;
}

async function fetchJsonWithEtag(url, {signal, etagKey} = {}) {
    const headers = {Accept: 'application/json'};
    if (etagKey) {
        const etag = etagStore.get(etagKey);
        if (etag) headers['If-None-Match'] = etag;
    }

    const res = await fetch(url, {headers, signal});
    if (res.status === 304) return {notModified: true};
    if (!res.ok) throw new Error('fetch_failed');

    const etag = res.headers.get('ETag');
    if (etagKey && etag) etagStore.set(etagKey, etag);

    return {notModified: false, data: await res.json()};
}

function historyClientTtlMs(range) {
    const key = rangeKey(range);
    const amount = parseInt(key, 10) || 1;
    if (key.endsWith('h')) return 45_000;
    if (amount >= 30) return 300_000;
    if (amount >= 7) return 120_000;
    return 45_000;
}

function chartYAxisWidth(item, values) {
    if (!values.length) return 72;
    const labels = values.map((value) => formatAxisPrice(value, item));
    const longest = labels.reduce((max, label) => Math.max(max, label.length), 0);
    return Math.min(96, Math.max(58, longest * 7 + 14));
}

function ChartYAxisTick({x, y, payload, fill, panelFill, item}) {
    const label = formatAxisPrice(payload.value, item);
    if (label === '—') return null;
    const width = Math.max(48, label.length * 6.8 + 8);
    return (
        <g className="chartYAxisTick" transform={`translate(${x},${y})`}>
            <rect x={2} y={-10} width={width} height={20} fill={panelFill} opacity={0.94} rx={4}/>
            <text x={6} y={4} textAnchor="start" fill={fill} fontSize={11} fontFamily="Vazirmatn, Tahoma, sans-serif">
                {label}
            </text>
        </g>
    );
}

const ChartRenderer = React.lazy(() => import('recharts').then((module) => ({
    default: function ChartRenderer({width, height, data, selected, activeRange, colors}) {
        const {Area, AreaChart, CartesianGrid, Tooltip, XAxis, YAxis} = module;
        const chartData = data
            .map((point) => ({...point, timeValue: new Date(point.time).getTime()}))
            .filter((point) => Number.isFinite(point.timeValue));
        const yAxisWidth = chartYAxisWidth(selected, chartData.map((point) => point.current));

        return (
            <AreaChart width={width} height={height} data={chartData} margin={{top: 12, right: 2, left: 2, bottom: 8}}>
                <defs>
                    <linearGradient id="goldGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={colors.accent} stopOpacity="0.32"/>
                        <stop offset="100%" stopColor={colors.accent} stopOpacity="0.02"/>
                    </linearGradient>
                </defs>
                <CartesianGrid stroke={colors.line} strokeOpacity={0.82} strokeDasharray="0" vertical={false}/>
                <XAxis dataKey="timeValue" type="number" scale="time" domain={['dataMin', 'dataMax']}
                       padding={{left: 0, right: 0}} tickLine={false} axisLine={false}
                       minTickGap={28}
                       tickFormatter={(value) => formatChartTick(value, activeRange)}/>
                <YAxis orientation="right" width={yAxisWidth} tickLine={false} axisLine={false}
                       domain={chartDomain}
                       tickCount={5}
                       tick={<ChartYAxisTick fill={colors.muted} panelFill={colors.panel} item={selected}/>}/>
                <Tooltip cursor={{stroke: colors.line, strokeDasharray: '3 3'}}
                         content={<ChartTooltip item={selected}/>}/>
                <Area type="linear" dataKey="current" stroke={colors.accent} strokeWidth={3}
                      fill="url(#goldGradient)" dot={false} activeDot={{r: 4}} connectNulls
                      strokeLinecap="round" strokeLinejoin="round" isAnimationActive={false}/>
            </AreaChart>
        );
    },
})));

function formatNumber(value, options = {}) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 2, ...options}).format(value);
}

function resolveSystemTheme() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveThemeDefault(value) {
    if (value === 'light' || value === 'dark') {
        return value;
    }

    return resolveSystemTheme();
}

function getInitialTheme(themeDefault) {
    try {
        const stored = localStorage.getItem('theme');
        if (stored) {
            return stored;
        }
    } catch {
        // localStorage may be unavailable in restricted contexts
    }

    return resolveThemeDefault(themeDefault || defaultConfig.themeDefault);
}

function persistTheme(theme) {
    try {
        localStorage.setItem('theme', theme);
    } catch {
        // ignore storage failures
    }
}

const chartRangeStorageKey = 'chartRange:v2';

function hasStoredChartRange() {
    try {
        return Boolean(localStorage.getItem(chartRangeStorageKey));
    } catch {
        return false;
    }
}

function persistChartRange(range) {
    try {
        localStorage.setItem(chartRangeStorageKey, rangeKey(range));
    } catch {
        // ignore storage failures
    }
}

function resolveChartRange(availableRanges, serverDefault, {preferStored = true} = {}) {
    const available = (availableRanges || defaultConfig.chartAvailableRanges).map(rangeKey);
    if (preferStored) {
        try {
            const stored = localStorage.getItem(chartRangeStorageKey);
            if (stored) {
                const key = rangeKey(stored);
                if (available.includes(key)) {
                    return key;
                }
            }
        } catch {
            // localStorage may be unavailable in restricted contexts
        }
    }

    const fallback = rangeKey(serverDefault || defaultConfig.chartDefaultRange);
    return available.includes(fallback) ? fallback : available[0];
}

function isUsdItem(item) {
    return item?.name?.includes('انس') || String(item?.currency || '').toUpperCase() === 'USD';
}

function displayValue(value, item) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
    return Number(value);
}

function formatPrice(value, item, options = {}) {
    const nextValue = displayValue(value, item);
    if (nextValue === null) return '—';
    const unit = isUsdItem(item) ? 'دلار' : 'تومان';
    return `${formatNumber(nextValue, options)} ${unit}`;
}

function formatAxisPrice(value, item) {
    const nextValue = displayValue(value, item);
    if (nextValue === null) return '—';
    return formatNumber(nextValue, {maximumFractionDigits: isUsdItem(item) ? 2 : 0});
}

function hasPercentValue(percent) {
    return percent !== null && percent !== undefined && !Number.isNaN(Number(percent));
}

function formatPercent(value) {
    if (!hasPercentValue(value)) return '—';
    return formatNumber(value);
}

function shouldShowChangeIcon(direction, percent) {
    const tone = changeTone(direction, percent);
    return !(tone === 'flat' && !hasPercentValue(percent));
}

function changeTone(direction, percent = null) {
    if (direction === 'desc') return 'down';
    if (direction === 'asc') return 'up';
    if (direction === 'none') return 'flat';
    const value = Number(percent);
    if (Number.isFinite(value) && value < 0) return 'down';
    if (Number.isFinite(value) && value > 0) return 'up';
    return 'flat';
}

function ChangeIcon({direction, percent = null, size = 16, variant = 'arrow'}) {
    const tone = changeTone(direction, percent);
    if (tone === 'down') return <ArrowDown size={size}/>;
    if (tone === 'up') return variant === 'trend' ? <TrendingUp size={size}/> : <ArrowUp size={size}/>;
    return <Minus size={size}/>;
}

function chartDomain([dataMin, dataMax]) {
    if (!Number.isFinite(dataMin) || !Number.isFinite(dataMax)) return ['dataMin', 'dataMax'];
    const span = Math.max(0, dataMax - dataMin);
    const baseline = Math.max(Math.abs(dataMin), Math.abs(dataMax), 1);
    const padding = Math.max(span * 0.18, baseline * 0.00025);
    return [Math.max(0, dataMin - padding), dataMax + padding];
}

function formatChartTick(value, range) {
    if (!value) return '';
    const date = new Date(value);
    const key = rangeKey(range);
    const options = key.endsWith('h') || key === '1d'
        ? {hour: '2-digit', minute: '2-digit'}
        : {month: '2-digit', day: '2-digit'};
    return new Intl.DateTimeFormat('fa-IR', options).format(date);
}

function rangeKey(range) {
    if (typeof range === 'number') return `${range}d`;
    const value = String(range || '').trim().toLowerCase();
    return /^\d+[hd]$/.test(value) ? value : `${parseInt(value || '1', 10) || 1}d`;
}

function rangeLabel(range) {
    const key = rangeKey(range);
    const amount = parseInt(key, 10);
    return key.endsWith('h') ? `${formatNumber(amount)} ساعت` : `${formatNumber(amount)} روز`;
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fa-IR', {dateStyle: 'medium', timeStyle: 'short'}).format(new Date(value));
}

function formatChartTooltipDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const datePart = new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date);
    const timePart = new Intl.DateTimeFormat('fa-IR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
    return {datePart, timePart};
}

function fetchStatusMessage(lastFetch, itemsCount) {
    if (!lastFetch && itemsCount === 0) {
        return 'هنوز هیچ دریافت موفقی ثبت نشده است. لطفاً کمی بعد دوباره تلاش کنید.';
    }

    if (lastFetch?.status === 'failed') {
        return 'آخرین دریافت قیمت‌ها ناموفق بود.';
    }

    if (lastFetch?.status === 'running' && itemsCount === 0) {
        return 'دریافت قیمت‌ها هنوز در حال اجراست و داده‌ای ذخیره نشده است.';
    }

    if (lastFetch?.status === 'success' && Number(lastFetch.items_count || 0) === 0) {
        return 'آخرین دریافت انجام شد اما داده قابل نمایش کافی وجود ندارد.';
    }

    return '';
}

function categoryLabel(category) {
    if (category === 'coin') return 'سکه';
    return 'طلا';
}

function setMeta(name, content) {
    if (!content) return;
    let tag = document.querySelector(`meta[name="${name}"]`);
    if (!tag) {
        tag = document.createElement('meta');
        tag.setAttribute('name', name);
        document.head.appendChild(tag);
    }
    tag.setAttribute('content', content);
}

function sanitizeHistory(points) {
    const rows = (points || []).map((point) => ({...point}));
    let lastGood = null;

    for (let index = 0; index < rows.length; index += 1) {
        const current = Number(rows[index].current);
        if (Number.isFinite(current) && current > 0) {
            rows[index].current = current;
            lastGood = current;
            continue;
        }

        let nextGood = null;
        for (let j = index + 1; j < rows.length; j += 1) {
            const candidate = Number(rows[j].current);
            if (Number.isFinite(candidate) && candidate > 0) {
                nextGood = candidate;
                break;
            }
        }

        if (lastGood !== null && nextGood !== null) {
            rows[index].current = (lastGood + nextGood) / 2;
            rows[index].isInterpolated = true;
        } else {
            rows[index].current = null;
            rows[index].isInvalid = true;
        }
    }

    return rows.filter((point) => Number(point.current) > 0);
}

function useElementSize() {
    const ref = useRef(null);
    const [size, setSize] = useState({width: 0, height: 0});

    useEffect(() => {
        const element = ref.current;
        if (!element) return undefined;

        const update = () => {
            const rect = element.getBoundingClientRect();
            setSize((current) => {
                const next = {
                    width: Math.max(0, Math.floor(rect.width)),
                    height: Math.max(0, Math.floor(rect.height)),
                };

                return current.width === next.width && current.height === next.height ? current : next;
            });
        };

        update();

        if (typeof ResizeObserver === 'undefined') {
            window.addEventListener('resize', update);
            return () => window.removeEventListener('resize', update);
        }

        const observer = new ResizeObserver(update);
        observer.observe(element);
        return () => observer.disconnect();
    }, []);

    return [ref, size];
}

async function fetchHistory(itemId, range, signal) {
    const url = `/api/market/items/${itemId}/history?range=${encodeURIComponent(rangeKey(range))}`;
    const result = await fetchJsonWithEtag(url, {signal, etagKey: `history:${itemId}:${rangeKey(range)}`});
    if (result.notModified) return {notModified: true};
    return {notModified: false, data: result.data};
}

function App() {
    const [config, setConfig] = useState(() => embeddedSummary ? buildConfigFromSummary(embeddedSummary) : defaultConfig);
    const [theme, setTheme] = useState(() => {
        const initialConfig = embeddedSummary ? buildConfigFromSummary(embeddedSummary) : defaultConfig;
        return getInitialTheme(initialConfig.themeDefault);
    });
    const [items, setItems] = useState(() => normalizeItems(embeddedSummary?.items));
    const [selectedId, setSelectedId] = useState(() => normalizeItems(embeddedSummary?.items)[0]?.id ?? null);
    const [history, setHistory] = useState([]);
    const [analytics, setAnalytics] = useState(null);
    const [query, setQuery] = useState('');
    const [range, setRange] = useState(() => {
        const initialConfig = embeddedSummary ? buildConfigFromSummary(embeddedSummary) : defaultConfig;
        return resolveChartRange(initialConfig.chartAvailableRanges, initialConfig.chartDefaultRange);
    });
    const [status, setStatus] = useState(() => (normalizeItems(embeddedSummary?.items).length ? 'ready' : 'loading'));
    const [error, setError] = useState('');
    const [lastFetch, setLastFetch] = useState(() => embeddedSummary?.lastFetch || null);
    const [historyLoading, setHistoryLoading] = useState(false);
    const historyCache = useRef(new Map());
    const refreshTimer = useRef(null);
    const lastFetchKey = useRef(embeddedSummary?.lastFetch?.finished_at || null);

    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.setProperty('--gold', config.themeAccent || defaultConfig.themeAccent);
    }, [theme, config.themeAccent]);

    useEffect(() => {
        if (items.length > 0) {
            document.body.classList.add('appReady');
        }
    }, [items.length]);

    useEffect(() => {
        if (localStorage.getItem('theme') || config.themeDefault !== 'system' || !window.matchMedia) return undefined;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const syncTheme = () => setTheme(resolveSystemTheme());
        media.addEventListener?.('change', syncTheme);
        return () => media.removeEventListener?.('change', syncTheme);
    }, [config.themeDefault]);

    const loadSummary = useCallback(async ({silent = false} = {}) => {
        if (!silent && !embeddedSummary) {
            setStatus('loading');
        }
        setError('');
        try {
            const result = await fetchJsonWithEtag('/api/market/summary', {etagKey: 'summary'});
            if (result.notModified) {
                if (!silent) setStatus('ready');
                return;
            }

            const data = result.data;
            const nextConfig = buildConfigFromSummary(data);
            setConfig(nextConfig);
            if (!localStorage.getItem('theme')) {
                setTheme(resolveThemeDefault(nextConfig.themeDefault));
            }
            setRange(resolveChartRange(
                nextConfig.chartAvailableRanges,
                nextConfig.chartDefaultRange,
                {preferStored: hasStoredChartRange()},
            ));
            setItems(normalizeItems(data.items));
            const nextFetchKey = data.lastFetch?.finished_at || data.lastFetch?.finishedAt || null;
            if (lastFetchKey.current && nextFetchKey && lastFetchKey.current !== nextFetchKey) {
                historyCache.current.clear();
            }
            lastFetchKey.current = nextFetchKey;
            setLastFetch(data.lastFetch || null);
            setSelectedId((current) => current || normalizeItems(data.items)[0]?.id || null);
            setStatus('ready');
        } catch {
            setError('در حال حاضر امکان دریافت اطلاعات بازار وجود ندارد. لطفاً کمی بعد دوباره تلاش کنید.');
            setStatus('error');
            if (!silent) {
                setItems([]);
                setHistory([]);
            }
        }
    }, []);

    useEffect(() => {
        loadSummary({silent: Boolean(embeddedSummary)});
    }, [loadSummary]);

    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                loadSummary({silent: true});
            }
        };
        const schedule = () => {
            window.clearInterval(refreshTimer.current);
            if (document.visibilityState === 'visible') {
                refreshTimer.current = window.setInterval(refresh, Math.max(15, config.autoRefreshSeconds || 60) * 1000);
            }
        };

        schedule();
        document.addEventListener('visibilitychange', schedule);
        return () => {
            window.clearInterval(refreshTimer.current);
            document.removeEventListener('visibilitychange', schedule);
        };
    }, [config.autoRefreshSeconds, loadSummary]);

    const selected = useMemo(() => items.find((item) => item.id === selectedId) || items[0] || null, [items, selectedId]);

    useEffect(() => {
        if (!selected || !range) return;
        const cacheKey = `${selected.id}:${range}`;
        const cached = historyCache.current.get(cacheKey);
        const controller = new AbortController();
        const cacheFresh = cached && (Date.now() - (cached.cachedAt || 0) < historyClientTtlMs(range));

        if (cached) {
            setHistory(cached.points || []);
            setAnalytics(cached.analytics || null);
            setHistoryLoading(false);
        } else {
            setHistoryLoading(true);
        }

        if (cacheFresh) {
            return () => controller.abort();
        }

        fetchHistory(selected.id, range, controller.signal)
            .then((result) => {
                if (result.notModified && cached) {
                    setHistoryLoading(false);
                    return;
                }

                const data = result.data;
                const normalized = {
                    ...data,
                    points: sanitizeHistory(data.points),
                    cachedAt: Date.now(),
                };
                historyCache.current.set(cacheKey, normalized);
                setHistory(normalized.points || []);
                setAnalytics(data.analytics || null);
                setHistoryLoading(false);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setHistoryLoading(false);
                    if (!cached) {
                        setHistory([]);
                        setAnalytics(null);
                    }
                }
            });

        return () => controller.abort();
    }, [selected?.id, range]);

    useEffect(() => {
        if (items.length === 0 || !range) return;
        const controller = new AbortController();
        var idleId = null;
        var timerId = null;

        const warmHistoryCache = () => {
            const shortRanges = new Set(['1h', '2h', '6h', '12h', '1d']);
            if (!shortRanges.has(range)) {
                return;
            }

            const queue = items
                .filter((item) => item.id !== selected?.id)
                .slice(0, 2)
                .filter((item) => !historyCache.current.has(`${item.id}:${range}`));

            queue.forEach((item) => {
                fetchHistory(item.id, range, controller.signal)
                    .then((result) => {
                        if (result.notModified) return;
                        historyCache.current.set(`${item.id}:${range}`, {
                            ...result.data,
                            points: sanitizeHistory(result.data.points),
                            cachedAt: Date.now(),
                        });
                    })
                    .catch(() => {
                    });
            });
        };

        if ('requestIdleCallback' in window) {
            idleId = window.requestIdleCallback(warmHistoryCache, {timeout: 2500});
        } else {
            timerId = window.setTimeout(warmHistoryCache, 1200);
        }

        return () => {
            controller.abort();
            if (idleId) {
                window.cancelIdleCallback?.(idleId);
            }
            if (timerId) {
                window.clearTimeout(timerId);
            }
        };
    }, [items, selected?.id, range]);

    const filtered = useMemo(() => items.filter((item) => item.name.includes(query)), [items, query]);
    const {gainers, unchanged, losers} = useMemo(() => ({
        gainers: items.filter((item) => item.direction === 'asc').length,
        unchanged: items.filter((item) => item.direction === 'none').length,
        losers: items.filter((item) => item.direction === 'desc').length,
    }), [items]);
    const ranges = config.chartAvailableRanges?.length ? config.chartAvailableRanges : defaultConfig.chartAvailableRanges;
    const activeRange = range || config.chartDefaultRange || defaultConfig.chartDefaultRange;
    const fetchNotice = fetchStatusMessage(lastFetch, items.length);

    useEffect(() => {
        if (items.length === 0) return;
        const primaryGold = items.find((item) => item.name.includes('۱۸') || item.name.includes('18')) || items.find((item) => item.category === 'gold');
        const primaryCoin = items.find((item) => item.category === 'coin');
        const description = `قیمت طلا امروز و قیمت لحظه‌ای سکه در بازار ایران. طلای ۱۸ عیار: ${formatPrice(primaryGold?.current, primaryGold)}، سکه: ${formatPrice(primaryCoin?.current, primaryCoin)}. مشاهده تغییرات زنده و نمودار تاریخی.`;
        document.title = 'قیمت طلا امروز و قیمت لحظه‌ای سکه | داشبورد بازار ایران';
        setMeta('description', description);
    }, [items]);

    return (
        <main className="shell">
            <header className="topbar">
                <div className="brand">
                    <span className="logo"><img src="/favicon.svg" alt="Ernoxin Gold" width={48} height={48}/></span>
                    <div><strong className="brandTitle">سکه و طلای ارنوکسین</strong><p>پایش قیمت طلا و سکه با
                        داده‌های {config.sourceName}</p></div>
                </div>
                <div className="actions">
                    <a className="navLink" href="/blog">بلاگ</a>
                    <button className="iconButton" onClick={() => {
                        const nextTheme = theme === 'dark' ? 'light' : 'dark';
                        setTheme(nextTheme);
                        persistTheme(nextTheme);
                    }}
                            title="تغییر پوسته" aria-label="تغییر پوسته">
                        {theme === 'dark' ? <Sun size={19}/> : <Moon size={19}/>}
                    </button>
                </div>
            </header>

            <section className="hero">
                <div>
                    <a className="eyebrow sourceLink" href={config.sourceUrl} target="_blank" rel="noopener noreferrer">منبع
                        رسمی: estjt.ir</a>
                    <h1>قیمت طلا امروز و قیمت لحظه‌ای سکه</h1>
                    <p>آخرین قیمت‌های بازار طلا و سکه ایران همراه با نمودار تعاملی و تاریخچه تغییرات.</p>
                </div>
                <div className="stats">
                    <Metric value={items.length} label="نماد فعال"/>
                    <Metric value={gainers} label="صعودی"/>
                    <Metric value={unchanged} label="بدون تغییر"/>
                    <Metric value={losers} label="نزولی"/>
                </div>
            </section>

            {error && <div className="notice noticeWithAction"><span>{error}</span>
                <button type="button" onClick={() => loadSummary()}><RefreshCw size={16}/>تلاش دوباره</button>
            </div>}
            {!error && fetchNotice && <div className="notice">{fetchNotice}</div>}

            <section className="layout">
                <aside className="marketPanel">
                    <div className="panelTitle">
                        <h2>بازارهای طلا و سکه</h2>
                        <small>آخرین دریافت: {formatDate(lastFetch?.finished_at || lastFetch?.finishedAt)}</small>
                    </div>
                    <div className="search"><Search size={18}/><input value={query}
                                                                      onChange={(e) => setQuery(e.target.value)}
                                                                      placeholder="جستجوی طلا، سکه یا دلار"/></div>
                    <div className="itemList">
                        {filtered.map((item) => <MarketItem key={item.id} item={item} active={selected?.id === item.id}
                                                            onClick={() => setSelectedId(item.id)}/>)}
                        {status !== 'loading' && filtered.length === 0 &&
                            <div className="empty">داده‌ای برای نمایش وجود ندارد.</div>}
                    </div>
                </aside>

                <section className="chartPanel" aria-label="نمودار قیمت بازار انتخاب ‌شده">
                    <div className="chartHeader">
                        <div><span>{selected ? categoryLabel(selected.category) : 'بازار'}</span>
                            <h2>{selected?.name ? `نمودار قیمت ${selected.name}` : 'نمودار قیمت طلا و سکه'}</h2></div>
                        <div className="range">{ranges.map((nextRange) => <button key={nextRange}
                                                                                  className={rangeKey(activeRange) === rangeKey(nextRange) ? 'active' : ''}
                                                                                  onClick={() => {
                                                                                      const key = rangeKey(nextRange);
                                                                                      setRange(key);
                                                                                      persistChartRange(key);
                                                                                  }}>{rangeLabel(nextRange)}</button>)}</div>
                    </div>

                    <div className="priceLine">
                        <strong>{formatPrice(selected?.current, selected)}</strong>
                        <span className={changeTone(selected?.direction, selected?.percent)}>
                            {shouldShowChangeIcon(selected?.direction, selected?.percent) && (
                                <ChangeIcon direction={selected?.direction} percent={selected?.percent} size={16}/>
                            )}
                            {formatPrice(selected?.change, selected)} ({formatPercent(selected?.percent)}٪)
                        </span>
                    </div>

                    <div className={`chartWrap ${historyLoading ? 'loading' : ''}`}>
                        {history.length > 0 ? (
                            <PriceChart history={history} selected={selected} activeRange={activeRange}
                                        accent={config.themeAccent || defaultConfig.themeAccent} theme={theme}/>
                        ) : (
                            <div className="chartEmpty"><WalletCards size={30}/><span>برای این بازه هنوز تاریخچه‌ای ثبت نشده است.</span>
                            </div>
                        )}
                    </div>

                    <div className="analyticsGrid">
                        <Metric value={analytics?.max} item={selected} label="بالاترین قیمت" compact price/>
                        <Metric value={analytics?.min} item={selected} label="پایین‌ترین قیمت" compact price/>
                        <Metric value={analytics?.changePercent} label="تغییرات ٪" compact
                                tone={changeTone(null, analytics?.changePercent)}/>
                    </div>
                </section>
            </section>
        </main>
    );
}

function PriceChart({history, selected, activeRange, accent, theme}) {
    const [chartRef, size] = useElementSize();
    const [colors, setColors] = useState({
        accent: accent || defaultConfig.themeAccent,
        line: '#263241',
        panel: '#111821',
        muted: '#9aa7b4',
    });
    const data = useMemo(() => history
            .map((point) => ({...point, current: Number(point.current)}))
            .filter((point) => Number.isFinite(point.current) && point.current > 0 && point.time),
        [history]);

    useEffect(() => {
        const styles = getComputedStyle(document.documentElement);
        setColors({
            accent: styles.getPropertyValue('--gold').trim() || accent || defaultConfig.themeAccent,
            line: styles.getPropertyValue('--line').trim() || '#263241',
            panel: styles.getPropertyValue('--panel').trim() || '#111821',
            muted: styles.getPropertyValue('--muted').trim() || '#9aa7b4',
        });
    }, [accent, theme]);

    return <div className="chartCanvas" ref={chartRef}>
        {size.width > 0 && size.height > 0 && data.length > 0 && (
            <React.Suspense fallback={<div className="chartSkeleton" aria-hidden="true"/>}>
                <ChartRenderer width={size.width} height={size.height} data={data} selected={selected}
                               activeRange={activeRange} colors={colors}/>
            </React.Suspense>
        )}
    </div>;
}

function Metric({value, label, compact = false, tone = '', item = null, price = false}) {
    return <div className={`metric ${compact ? 'compact' : ''} ${tone}`}>
        <strong>{price ? formatPrice(value, item) : formatNumber(value)}</strong><span>{label}</span></div>;
}

function MarketItem({item, active, onClick}) {
    const tone = changeTone(item.direction, item.percent);
    const Icon = item.category === 'coin' ? Coins : BarChart3;
    return (
        <button className={`marketItem ${active ? 'active' : ''}`} onClick={onClick}>
            <span className="itemIcon"><Icon size={20}/></span>
            <span className="itemMain"><b>{item.name}</b><small>{formatPrice(item.current, item)}</small></span>
            <span className={`badge ${tone}`}>
                {shouldShowChangeIcon(item.direction, item.percent) && (
                    <ChangeIcon direction={item.direction} percent={item.percent} size={14} variant="trend"/>
                )}
                {formatPercent(item.percent)}٪
            </span>
        </button>
    );
}

function ChartTooltip({active, payload, label, item}) {
    if (!active || !payload?.length) return null;
    const formatted = formatChartTooltipDate(label);
    return <div className="tooltip">
        {formatted ? (
            <>
                <span>{formatted.datePart}</span>
                <span>ساعت {formatted.timePart}</span>
            </>
        ) : (
            <span>—</span>
        )}
        <strong>{formatPrice(payload[0].value, item)}</strong>
    </div>;
}

createRoot(document.getElementById('root')).render(<App/>);
