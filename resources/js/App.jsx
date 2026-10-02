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
import {
    defaultConfig,
    chartRangeStorageKey,
    normalizeItems,
    buildConfigFromSummary,
    historyClientTtlMs,
    isShortHistoryRange,
    chartYAxisWidth,
    formatNumber,
    formatDate,
    formatPrice,
    formatAxisPrice,
    hasPercentValue,
    formatPercent,
    changeTone,
    chartDomain,
    formatChartTick,
    formatChartTooltipDate,
    rangeKey,
    rangeLabel,
    normalizeAvailableRanges,
    coerceChartRange,
    resolveThemeDefault,
    resolveSystemTheme,
    isUsdItem,
    priceUnitLabel,
} from './market-utils.js';

const etagStore = new Map();

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


function hasStoredChartRange() {
    try {
        return Boolean(localStorage.getItem(chartRangeStorageKey));
    } catch {
        return false;
    }
}

function persistChartRange(range) {
    const key = rangeKey(range);
    if (!key) return;
    try {
        localStorage.setItem(chartRangeStorageKey, key);
    } catch {
        // ignore storage failures
    }
}

function resolveChartRange(availableRanges, serverDefault, {preferStored = true} = {}) {
    const available = normalizeAvailableRanges(availableRanges);
    const fromTrend = rangeFromTrendPath(available);
    if (fromTrend) {
        return fromTrend;
    }
    if (preferStored) {
        try {
            const stored = localStorage.getItem(chartRangeStorageKey);
            if (stored) {
                const key = rangeKey(stored);
                if (key && available.includes(key)) {
                    return key;
                }
            }
        } catch {
            // localStorage may be unavailable in restricted contexts
        }
    }

    return coerceChartRange(serverDefault, available, available[0] || defaultConfig.chartDefaultRange);
}


function trendRangeFromPath(pathname = typeof window !== 'undefined' ? window.location.pathname : '') {
    const match = String(pathname).match(/\/price\/trends\/(\d+[hd]?)/);
    return match ? match[1] : null;
}

function rangeFromTrendPath(availableRanges) {
    const slug = trendRangeFromPath();
    if (!slug) return null;
    const key = slug.endsWith('h')
        ? rangeKey(slug)
        : rangeKey(slug.endsWith('d') ? slug : `${slug}d`);
    const available = normalizeAvailableRanges(availableRanges);
    return key && available.includes(key) ? key : null;
}

function syncTrendUrl(range) {
    if (typeof window === 'undefined' || !window.history?.replaceState) return;
    const key = rangeKey(range);
    if (!key) return;
    const slug = key.endsWith('h') ? key : String(parseInt(key, 10));
    if (!slug) return;
    const next = `/price/trends/${slug}`;
    if (window.location.pathname === next) return;
    if (!window.location.pathname.startsWith('/price')) return;
    window.history.replaceState(null, '', next);
}


function shouldShowChangeIcon(direction, percent) {
    const tone = changeTone(direction, percent);
    return !(tone === 'flat' && !hasPercentValue(percent));
}


function ChangeIcon({direction, percent = null, size = 16, variant = 'arrow'}) {
    const tone = changeTone(direction, percent);
    if (tone === 'down') return <ArrowDown size={size}/>;
    if (tone === 'up') return variant === 'trend' ? <TrendingUp size={size}/> : <ArrowUp size={size}/>;
    return <Minus size={size}/>;
}


function seoRangeTitleLabel(rangeKey) {
    if (!rangeKey) return null;
    const amount = parseInt(rangeKey, 10);
    if (!Number.isFinite(amount) || amount < 1) return null;
    return rangeKey.endsWith('h') ? `${formatNumber(amount)} ساعت` : `${formatNumber(amount)} روز`;
}

function fetchStatusMessage(lastFetch, itemsCount) {
    if (!lastFetch && itemsCount === 0) {
        return 'هنوز هیچ دریافت موفقی ثبت نشده است. لطفاً کمی بعد دوباره تلاش کنید.';
    }

    if (lastFetch?.status === 'failed') {
        return lastFetch?.message || 'آخرین دریافت قیمت‌ها ناموفق بود.';
    }

    if (lastFetch?.status === 'running' && itemsCount === 0) {
        return 'دریافت قیمت‌ها هنوز در حال اجراست و داده‌ای ذخیره نشده است.';
    }

    if (lastFetch?.status === 'partial') {
        return lastFetch?.message || 'آخرین دریافت ناقص بود؛ بعضی نمادها ممکن است قدیمی باشند. لطفاً دوباره تلاش کنید.';
    }

    if (lastFetch?.status === 'success' && Number(lastFetch.items_count || 0) === 0) {
        return 'آخرین دریافت انجام شد اما داده قابل نمایش کافی وجود ندارد.';
    }

    return '';
}

function fetchNoticeIsCritical(lastFetch) {
    return lastFetch?.status === 'failed' || lastFetch?.status === 'partial';
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
    return (points || []).map((point) => {
        const current = Number(point.current);
        if (Number.isFinite(current) && current > 0) {
            return {...point, current};
        }

        return {...point, current: null};
    });
}

function summaryFingerprint(data) {
    const items = normalizeItems(data?.items);
    const prices = items.map((item) =>
        [item.id, item.current, item.direction, item.percent, item.change, item.stale ? 1 : 0].join(':'),
    ).join('|');
    const fetchKey = data?.lastFetch?.finished_at || data?.lastFetch?.finishedAt || '';
    return `${fetchKey}#${prices}`;
}

function seoPriceFingerprint(items) {
    const list = normalizeItems(items);
    const primaryGold = list.find((item) => item.name?.includes('۱۸') || item.name?.includes('18'))
        || list.find((item) => item.category === 'gold');
    const primaryCoin = list.find((item) => item.category === 'coin');
    return `${trendRangeFromPath()}|${primaryGold?.current ?? ''}|${primaryCoin?.current ?? ''}`;
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

async function fetchHistory(item, range, signal) {
    const itemRef = typeof item === 'object' && item !== null
        ? (item.slug || item.id)
        : item;
    const rangeParam = rangeKey(range);
    if (!itemRef || !rangeParam) {
        throw new Error('invalid_history_request');
    }
    const url = `/api/market/items/${encodeURIComponent(itemRef)}/history?range=${encodeURIComponent(rangeParam)}`;
    const result = await fetchJsonWithEtag(url, {signal, etagKey: `history:${itemRef}:${rangeParam}`});
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
    // historyTick bumps on short-range price updates so the chart refetches.
    // Long ranges keep client cache until TTL / range change (no per-tick refetch).
    const [historyTick, setHistoryTick] = useState(0);
    const historyCache = useRef(new Map());
    const warmedRanges = useRef(new Set());
    const rangeRef = useRef(range);
    rangeRef.current = range;
    const refreshTimer = useRef(null);
    const lastFetchKey = useRef(embeddedSummary?.lastFetch?.finished_at || null);
    const summaryFp = useRef(embeddedSummary ? summaryFingerprint(embeddedSummary) : null);
    const summarySeq = useRef(0);
    const seoFp = useRef(null);

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
        const seq = ++summarySeq.current;
        if (!silent && !embeddedSummary) {
            setStatus('loading');
        }
        setError('');
        try {
            const result = await fetchJsonWithEtag('/api/market/summary', {etagKey: 'summary'});
            if (seq !== summarySeq.current) return;
            if (result.notModified) {
                if (!silent) setStatus('ready');
                return;
            }

            const data = result.data;
            const nextFp = summaryFingerprint(data);
            if (nextFp === summaryFp.current) {
                if (!silent) setStatus('ready');
                return;
            }
            summaryFp.current = nextFp;

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
                const currentRange = rangeRef.current;
                // Short ranges: soft-invalidate active window and refetch.
                // Long ranges: keep client history until TTL / range change.
                if (isShortHistoryRange(currentRange)) {
                    for (const key of [...historyCache.current.keys()]) {
                        if (key.endsWith(`:${currentRange}`)) {
                            historyCache.current.delete(key);
                        }
                    }
                    setHistoryTick((tick) => tick + 1);
                }
            }
            lastFetchKey.current = nextFetchKey;
            setLastFetch(data.lastFetch || null);
            setSelectedId((current) => current || normalizeItems(data.items)[0]?.id || null);
            setStatus('ready');
        } catch {
            if (seq !== summarySeq.current) return;
            setError('در حال حاضر امکان دریافت اطلاعات بازار وجود ندارد. لطفاً کمی بعد دوباره تلاش کنید.');
            setStatus('error');
            // Keep last good prices/history — a failed refresh must not blank the dashboard.
        }
    }, []);

    // Embedded #market-summary already painted — skip the duplicate first fetch; poll after interval.
    useEffect(() => {
        if (embeddedSummary) return undefined;
        loadSummary({silent: false});
        return undefined;
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

        fetchHistory(selected, range, controller.signal)
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
    }, [selected?.id, range, historyTick]);

    useEffect(() => {
        if (items.length === 0 || !range) return;
        if (!isShortHistoryRange(range)) return;
        if (warmedRanges.current.has(range)) return;
        warmedRanges.current.add(range);

        const controller = new AbortController();
        var idleId = null;
        var timerId = null;

        const warmHistoryCache = () => {
            // One warm fetch max; skip on save-data — shared host rebuilds are costly.
            if (navigator.connection?.saveData) return;

            const warmItem = items
                .filter((item) => item.id !== selected?.id)
                .find((item) => !historyCache.current.has(`${item.id}:${range}`));
            if (!warmItem) return;

            fetchHistory(warmItem, range, controller.signal)
                .then((result) => {
                    if (result.notModified) return;
                    historyCache.current.set(`${warmItem.id}:${range}`, {
                        ...result.data,
                        points: sanitizeHistory(result.data.points),
                        cachedAt: Date.now(),
                    });
                })
                .catch(() => {
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
    const fetchNoticeCritical = fetchNoticeIsCritical(lastFetch);

    useEffect(() => {
        if (items.length === 0) return;
        const nextSeo = seoPriceFingerprint(items);
        if (nextSeo === seoFp.current) return;
        seoFp.current = nextSeo;

        const rangeSlug = trendRangeFromPath();
        if (rangeSlug) {
            const key = rangeSlug.endsWith('h')
                ? rangeSlug
                : (rangeSlug.endsWith('d') ? rangeSlug : `${rangeSlug}d`);
            const label = seoRangeTitleLabel(key);
            if (label) {
                document.title = `نمودار ${label}ه قیمت طلا و سکه | قیمت لحظه‌ای بازار ایران`;
                setMeta('description', `بررسی روند ${label}ه قیمت طلا و سکه با داده‌های تاریخی، نمودار تعاملی و آخرین قیمت‌های ثبت‌شده بازار ایران.`);
                return;
            }
        }
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
            {!error && fetchNotice && (
                <div className={`notice ${fetchNoticeCritical ? 'noticeWithAction' : ''}`}>
                    <span>{fetchNotice}</span>
                    {fetchNoticeCritical && (
                        <button type="button" onClick={() => loadSummary()}><RefreshCw size={16}/>تلاش دوباره</button>
                    )}
                </div>
            )}

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
                                                                                      if (!key) return;
                                                                                      setRange(key);
                                                                                      persistChartRange(key);
                                                                                      syncTrendUrl(key);
                                                                                  }}>{rangeLabel(nextRange)}</button>)}</div>
                    </div>

                    <div className="priceLine">
                        <strong>{formatPrice(selected?.current, selected)}</strong>
                        <span className={changeTone(null, analytics?.changePercent)}>
                            {shouldShowChangeIcon(null, analytics?.changePercent) && (
                                <ChangeIcon direction={null} percent={analytics?.changePercent} size={16}/>
                            )}
                            {formatPrice(analytics?.change, selected)} ({formatPercent(analytics?.changePercent)}٪)
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
                        <Metric value={analytics?.avg} item={selected} label="میانگین قیمت" compact price/>
                    </div>
                </section>
            </section>
        </main>
    );
}

function PriceChart({history, selected, activeRange, accent, theme}) {
    const [wrapRef, size] = useElementSize();
    const canvasRef = useRef(null);
    const layoutRef = useRef(null);
    const [hover, setHover] = useState(null);
    const [colors, setColors] = useState({
        accent: accent || defaultConfig.themeAccent,
        line: '#263241',
        panel: '#111821',
        muted: '#9aa7b4',
        text: '#e8eef4',
    });

    const series = useMemo(() => history
        .map((point) => {
            const t = new Date(point.time).getTime();
            const current = Number(point.current);
            return {
                t,
                v: Number.isFinite(current) && current > 0 ? current : null,
            };
        })
        .filter((point) => Number.isFinite(point.t)), [history]);

    useEffect(() => {
        const styles = getComputedStyle(document.documentElement);
        setColors({
            accent: styles.getPropertyValue('--gold').trim() || accent || defaultConfig.themeAccent,
            line: styles.getPropertyValue('--line').trim() || '#263241',
            panel: styles.getPropertyValue('--panel').trim() || '#111821',
            muted: styles.getPropertyValue('--muted').trim() || '#9aa7b4',
            text: styles.getPropertyValue('--text').trim() || '#e8eef4',
        });
    }, [accent, theme]);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || size.width < 2 || size.height < 2) return;

        const usable = series.filter((p) => p.v !== null);
        if (!usable.length) {
            layoutRef.current = null;
            return;
        }

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(size.width * dpr);
        canvas.height = Math.floor(size.height * dpr);
        canvas.style.width = `${size.width}px`;
        canvas.style.height = `${size.height}px`;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, size.width, size.height);

        const values = usable.map((p) => p.v);
        const yAxisW = chartYAxisWidth(selected, values);
        const pad = {top: 12, right: yAxisW + 4, left: 8, bottom: 28};
        const plotW = Math.max(1, size.width - pad.left - pad.right);
        const plotH = Math.max(1, size.height - pad.top - pad.bottom);
        const tMin = series[0].t;
        const tMax = series[series.length - 1].t;
        const tSpan = Math.max(1, tMax - tMin);
        const [yMin, yMax] = chartDomain([Math.min(...values), Math.max(...values)]);
        const ySpan = Math.max(1e-9, yMax - yMin);

        const xAt = (t) => pad.left + ((t - tMin) / tSpan) * plotW;
        const yAt = (v) => pad.top + (1 - (v - yMin) / ySpan) * plotH;

        layoutRef.current = {pad, plotW, plotH, tMin, tSpan, xAt, yAt, series};

        // Horizontal grid
        ctx.strokeStyle = colors.line;
        ctx.globalAlpha = 0.82;
        ctx.lineWidth = 1;
        const tickCount = 5;
        for (let i = 0; i < tickCount; i += 1) {
            const ratio = tickCount === 1 ? 0 : i / (tickCount - 1);
            const y = pad.top + ratio * plotH;
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(pad.left + plotW, y);
            ctx.stroke();
        }
        ctx.globalAlpha = 1;

        // Area + line (gap on nulls)
        const fillGrad = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
        fillGrad.addColorStop(0, withAlpha(colors.accent, 0.32));
        fillGrad.addColorStop(1, withAlpha(colors.accent, 0.02));

        let seg = [];
        const flush = () => {
            if (seg.length < 1) return;
            if (seg.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(seg[0].x, pad.top + plotH);
                for (const p of seg) ctx.lineTo(p.x, p.y);
                ctx.lineTo(seg[seg.length - 1].x, pad.top + plotH);
                ctx.closePath();
                ctx.fillStyle = fillGrad;
                ctx.fill();

                ctx.beginPath();
                ctx.moveTo(seg[0].x, seg[0].y);
                for (let i = 1; i < seg.length; i += 1) ctx.lineTo(seg[i].x, seg[i].y);
                ctx.strokeStyle = colors.accent;
                ctx.lineWidth = 3;
                ctx.lineJoin = 'round';
                ctx.lineCap = 'round';
                ctx.stroke();
            } else {
                ctx.beginPath();
                ctx.arc(seg[0].x, seg[0].y, 3, 0, Math.PI * 2);
                ctx.fillStyle = colors.accent;
                ctx.fill();
            }
            seg = [];
        };

        for (const point of series) {
            if (point.v === null) {
                flush();
                continue;
            }
            seg.push({x: xAt(point.t), y: yAt(point.v)});
        }
        flush();

        // Y ticks (right)
        ctx.font = '11px Vazirmatn, Tahoma, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        for (let i = 0; i < tickCount; i += 1) {
            const ratio = tickCount === 1 ? 0 : i / (tickCount - 1);
            const value = yMax - ratio * ySpan;
            const label = formatAxisPrice(value, selected);
            if (label === '—') continue;
            const y = pad.top + ratio * plotH;
            const tw = Math.max(48, label.length * 6.8 + 8);
            ctx.fillStyle = withAlpha(colors.panel, 0.94);
            roundRect(ctx, pad.left + plotW + 2, y - 10, tw, 20, 4);
            ctx.fill();
            ctx.fillStyle = colors.muted;
            ctx.fillText(label, pad.left + plotW + 6, y);
        }

        // X ticks
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillStyle = colors.muted;
        const minGap = 56;
        let lastX = -Infinity;
        const xTicks = Math.max(2, Math.floor(plotW / minGap));
        for (let i = 0; i <= xTicks; i += 1) {
            const t = tMin + (i / xTicks) * tSpan;
            const x = xAt(t);
            if (x - lastX < minGap * 0.85 && i !== 0 && i !== xTicks) continue;
            lastX = x;
            ctx.fillText(formatChartTick(t, activeRange), x, pad.top + plotH + 8);
        }

        // Hover crosshair + dot
        if (hover && Number.isFinite(hover.v)) {
            const hx = xAt(hover.t);
            const hy = yAt(hover.v);
            ctx.save();
            ctx.setLineDash([3, 3]);
            ctx.strokeStyle = colors.line;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(hx, pad.top);
            ctx.lineTo(hx, pad.top + plotH);
            ctx.stroke();
            ctx.restore();

            ctx.beginPath();
            ctx.arc(hx, hy, 4, 0, Math.PI * 2);
            ctx.fillStyle = colors.accent;
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = colors.panel;
            ctx.stroke();
        }
    }, [series, size, colors, selected, activeRange, hover]);

    const onPointer = (event) => {
        const layout = layoutRef.current;
        const canvas = canvasRef.current;
        if (!layout || !canvas) return;
        const rect = canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        if (x < layout.pad.left || x > layout.pad.left + layout.plotW) {
            setHover(null);
            return;
        }
        const t = layout.tMin + ((x - layout.pad.left) / layout.plotW) * layout.tSpan;
        let best = null;
        let bestDist = Infinity;
        for (const point of layout.series) {
            if (point.v === null) continue;
            const dist = Math.abs(point.t - t);
            if (dist < bestDist) {
                bestDist = dist;
                best = point;
            }
        }
        if (!best) {
            setHover(null);
            return;
        }
        setHover({
            t: best.t,
            v: best.v,
            x: layout.xAt(best.t),
            y: layout.yAt(best.v),
        });
    };

    const tooltip = hover ? formatChartTooltipDate(hover.t) : null;
    const tipLeft = hover
        ? Math.min(Math.max(hover.x + 12, 8), Math.max(8, size.width - 140))
        : 0;
    const tipTop = hover
        ? Math.min(Math.max(hover.y - 56, 8), Math.max(8, size.height - 72))
        : 0;

    return (
        <div className="chartCanvas" ref={wrapRef}>
            {size.width > 0 && size.height > 0 && series.some((p) => p.v !== null) ? (
                <>
                    <canvas
                        ref={canvasRef}
                        onPointerMove={onPointer}
                        onPointerLeave={() => setHover(null)}
                        style={{display: 'block', width: '100%', height: '100%', touchAction: 'none'}}
                    />
                    {hover && (
                        <div className="tooltip chartHoverTip" style={{left: tipLeft, top: tipTop}}>
                            {tooltip ? (
                                <>
                                    <span>{tooltip.datePart}</span>
                                    <span>ساعت {tooltip.timePart}</span>
                                </>
                            ) : (
                                <span>—</span>
                            )}
                            <strong>{formatPrice(hover.v, selected)}</strong>
                        </div>
                    )}
                </>
            ) : null}
        </div>
    );
}

function withAlpha(color, alpha) {
    const value = String(color || '').trim();
    if (value.startsWith('#') && (value.length === 7 || value.length === 4)) {
        const hex = value.length === 4
            ? `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`
            : value;
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }
    return value;
}

function roundRect(ctx, x, y, w, h, r) {
    const radius = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
}

function Metric({value, label, compact = false, tone = '', item = null, price = false}) {
    return <div className={`metric ${compact ? 'compact' : ''} ${tone}`}>
        <strong>{price ? formatPrice(value, item) : formatNumber(value)}</strong><span>{label}</span></div>;
}

function MarketItem({item, active, onClick}) {
    const tone = changeTone(item.direction, item.percent);
    const Icon = item.category === 'coin' ? Coins : BarChart3;
    return (
        <button className={`marketItem ${active ? 'active' : ''} ${item.stale ? 'stale' : ''}`} onClick={onClick}>
            <span className="itemIcon"><Icon size={20}/></span>
            <span className="itemMain">
                <b>{item.name}{item.stale ? <span className="staleBadge">قدیمی</span> : null}</b>
                <small>{formatPrice(item.current, item)}</small>
            </span>
            <span className={`badge ${tone}`}>
                {shouldShowChangeIcon(item.direction, item.percent) && (
                    <ChangeIcon direction={item.direction} percent={item.percent} size={14} variant="trend"/>
                )}
                {formatPercent(item.percent)}٪
            </span>
        </button>
    );
}

createRoot(document.getElementById('root')).render(<App/>);
