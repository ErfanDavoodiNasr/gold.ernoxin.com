import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis} from 'recharts';
import {ArrowDown, ArrowUp, BarChart3, Coins, Moon, Search, Sun, TrendingUp, WalletCards} from 'lucide-react';
import '../css/app.css';

const defaultConfig = {
    chartDefaultRange: '1d',
    chartDefaultRangeDays: 1,
    chartAvailableRanges: ['1h', '2h', '6h', '12h', '1d', '7d', '30d', '90d'],
    autoRefreshSeconds: 60,
    themeDefault: 'system',
    themeAccent: '#d9a441',
    sourceName: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    sourceUrl: 'https://www.estjt.ir/price/',
};

function formatNumber(value, options = {}) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 2, ...options}).format(value);
}

function resolveSystemTheme() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function getInitialTheme() {
    return localStorage.getItem('theme') || resolveSystemTheme();
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

function chartUnitLabel(item) {
    return isUsdItem(item) ? 'قیمت، دلار' : 'قیمت، تومان';
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

function fetchStatusMessage(lastFetch, itemsCount) {
    if (!lastFetch && itemsCount === 0) {
        return 'هنوز هیچ دریافت موفقی ثبت نشده است. اتصال دیتابیس و دسترسی سرور به منبع قیمت را بررسی کنید.';
    }

    if (lastFetch?.status === 'failed') {
        return lastFetch.message
            ? `آخرین دریافت ناموفق بود: ${lastFetch.message}`
            : 'آخرین دریافت ناموفق بود. لاگ سرور را بررسی کنید.';
    }

    if (lastFetch?.status === 'running' && itemsCount === 0) {
        return 'دریافت قیمت‌ها هنوز در حال اجراست و داده‌ای ذخیره نشده است.';
    }

    if (lastFetch?.status === 'success' && Number(lastFetch.items_count || 0) === 0) {
        return 'آخرین دریافت موفق بود اما هیچ آیتمی از منبع استخراج نشد. احتمالاً ساختار صفحه منبع تغییر کرده است.';
    }

    return '';
}

function categoryLabel(category) {
    return category === 'coin' ? 'سکه' : 'طلا';
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

async function fetchHistory(itemId, range, signal) {
    const res = await fetch(`/api/market/items/${itemId}/history?range=${encodeURIComponent(rangeKey(range))}`, {
        headers: {Accept: 'application/json'},
        signal,
    });
    if (!res.ok) throw new Error('history_failed');
    return res.json();
}

function App() {
    const [config, setConfig] = useState(defaultConfig);
    const [theme, setTheme] = useState(getInitialTheme);
    const [items, setItems] = useState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [history, setHistory] = useState([]);
    const [analytics, setAnalytics] = useState(null);
    const [query, setQuery] = useState('');
    const [range, setRange] = useState(null);
    const [status, setStatus] = useState('loading');
    const [error, setError] = useState('');
    const [lastFetch, setLastFetch] = useState(null);
    const [historyLoading, setHistoryLoading] = useState(false);
    const historyCache = useRef(new Map());
    const refreshTimer = useRef(null);
    const lastFetchKey = useRef(null);

    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.setProperty('--gold', config.themeAccent || defaultConfig.themeAccent);
        localStorage.setItem('theme', theme);
    }, [theme, config.themeAccent]);

    useEffect(() => {
        document.body.classList.add('appReady');
    }, []);

    useEffect(() => {
        if (localStorage.getItem('theme') || !window.matchMedia) return undefined;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const syncTheme = () => setTheme(resolveSystemTheme());
        media.addEventListener?.('change', syncTheme);
        return () => media.removeEventListener?.('change', syncTheme);
    }, []);

    const loadSummary = useCallback(async ({silent = false} = {}) => {
        if (!silent) {
            setStatus('loading');
        }
        setError('');
        try {
            const res = await fetch('/api/market/summary', {headers: {Accept: 'application/json'}});
            if (!res.ok) throw new Error('summary_failed');
            const data = await res.json();
            const nextConfig = {...defaultConfig, ...(data.config || {})};
            nextConfig.chartDefaultRange = rangeKey(nextConfig.chartDefaultRange || nextConfig.chartDefaultRangeDays);
            nextConfig.chartAvailableRanges = (nextConfig.chartAvailableRanges || defaultConfig.chartAvailableRanges).map(rangeKey);
            setConfig(nextConfig);
            setRange((current) => current || nextConfig.chartDefaultRange);
            setItems(data.items || []);
            const nextFetchKey = data.lastFetch?.finished_at || data.lastFetch?.finishedAt || null;
            if (lastFetchKey.current && nextFetchKey && lastFetchKey.current !== nextFetchKey) {
                historyCache.current.clear();
            }
            lastFetchKey.current = nextFetchKey;
            setLastFetch(data.lastFetch || null);
            setSelectedId((current) => current || data.items?.[0]?.id || null);
            setStatus('ready');
        } catch {
            setError('ارتباط با API برقرار نشد. تنظیمات سرور، دیتابیس و route ها را بررسی کنید.');
            setStatus('error');
            if (!silent) {
                setItems([]);
                setHistory([]);
            }
        }
    }, []);

    useEffect(() => {
        loadSummary();
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

        if (cached) {
            setHistory(cached.points || []);
            setAnalytics(cached.analytics || null);
            setHistoryLoading(false);
        } else {
            setHistoryLoading(true);
        }

        fetchHistory(selected.id, range, controller.signal)
            .then((data) => {
                historyCache.current.set(cacheKey, data);
                setHistory(data.points || []);
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
        const queue = items
            .filter((item) => item.id !== selected?.id)
            .slice(0, 8)
            .filter((item) => !historyCache.current.has(`${item.id}:${range}`));

        queue.forEach((item) => {
            fetchHistory(item.id, range, controller.signal)
                .then((data) => historyCache.current.set(`${item.id}:${range}`, data))
                .catch(() => {
                });
        });

        return () => controller.abort();
    }, [items, selected?.id, range]);

    const filtered = useMemo(() => items.filter((item) => item.name.includes(query)), [items, query]);
    const gainers = items.filter((item) => item.direction === 'asc').length;
    const losers = items.filter((item) => item.direction === 'desc').length;
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
                    <span className="logo"><Coins size={25}/></span>
                    <div><strong className="brandTitle">سکه و طلای ارنوکسین</strong><p>پایش قیمت طلا و سکه با
                        داده‌های {config.sourceName}</p></div>
                </div>
                <div className="actions">
                    <button className="iconButton" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
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
                    <Metric value={losers} label="نزولی"/>
                </div>
            </section>

            {error && <div className="notice">{error}</div>}
            {!error && fetchNotice && <div className="notice">{fetchNotice}</div>}

            <section className="layout">
                <aside className="marketPanel">
                    <div className="panelTitle">
                        <h2>بازارهای طلا و سکه</h2>
                        <small>آخرین دریافت: {formatDate(lastFetch?.finished_at || lastFetch?.finishedAt)}</small>
                    </div>
                    <div className="search"><Search size={18}/><input value={query}
                                                                      onChange={(e) => setQuery(e.target.value)}
                                                                      placeholder="جستجوی طلا یا سکه"/></div>
                    <div className="itemList">
                        {filtered.map((item) => <MarketItem key={item.id} item={item} active={selected?.id === item.id}
                                                            onClick={() => setSelectedId(item.id)}/>)}
                        {status !== 'loading' && filtered.length === 0 &&
                            <div className="empty">داده‌ای برای نمایش وجود ندارد.</div>}
                    </div>
                </aside>

                <section className="chartPanel">
                    <div className="chartHeader">
                        <div><span>{selected ? categoryLabel(selected.category) : 'بازار'}</span>
                            <h2>{selected?.name ? `نمودار قیمت ${selected.name}` : 'نمودار قیمت طلا و سکه'}</h2></div>
                        <div className="range">{ranges.map((nextRange) => <button key={nextRange}
                                                                                  className={rangeKey(activeRange) === rangeKey(nextRange) ? 'active' : ''}
                                                                                  onClick={() => setRange(rangeKey(nextRange))}>{rangeLabel(nextRange)}</button>)}</div>
                    </div>

                    <div className="priceLine">
                        <strong>{formatPrice(selected?.current, selected)}</strong>
                        <span className={selected?.direction === 'desc' ? 'down' : 'up'}>
              {selected?.direction === 'desc' ? <ArrowDown size={16}/> : <ArrowUp size={16}/>}
                            {formatPrice(selected?.change, selected)} ({formatNumber(selected?.percent)}٪)
            </span>
                    </div>

                    <div className={`chartWrap ${historyLoading ? 'loading' : ''}`}>
                        {history.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={history} margin={{top: 22, right: 8, left: 4, bottom: 14}}>
                                    <defs>
                                        <linearGradient id="goldGradient" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stopColor="var(--gold)" stopOpacity="0.32"/>
                                            <stop offset="100%" stopColor="var(--gold)" stopOpacity="0.02"/>
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="0" vertical={false}/>
                                    <XAxis dataKey="time" tickLine={false} axisLine={false}
                                           minTickGap={28}
                                           tickFormatter={(v) => formatChartTick(v, activeRange)}/>
                                    <YAxis orientation="right" width={104} tickLine={false} axisLine={false}
                                           domain={chartDomain}
                                           tickCount={5}
                                           label={{value: chartUnitLabel(selected), position: 'insideTopRight', offset: -14}}
                                           tickFormatter={(v) => formatAxisPrice(v, selected)}/>
                                    <Tooltip cursor={{stroke: 'var(--line)', strokeDasharray: '3 3'}}
                                             content={<ChartTooltip item={selected}/>}/>
                                    <Area type="monotone" dataKey="current" stroke="var(--gold)" strokeWidth={3}
                                          fill="url(#goldGradient)" dot={false} activeDot={{r: 4}} isAnimationActive/>
                                </AreaChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="chartEmpty"><WalletCards size={30}/><span>برای این بازه هنوز تاریخچه‌ای ثبت نشده است.</span>
                            </div>
                        )}
                    </div>

                    <div className="analyticsGrid">
                        <Metric value={analytics?.max} item={selected} label="بالاترین قیمت" compact price/>
                        <Metric value={analytics?.min} item={selected} label="پایین‌ترین قیمت" compact price/>
                        <Metric value={analytics?.changePercent} label="تغییرات ٪" compact
                                tone={analytics?.changePercent < 0 ? 'down' : 'up'}/>
                    </div>
                </section>
            </section>
        </main>
    );
}

function Metric({value, label, compact = false, tone = '', item = null, price = false}) {
    return <div className={`metric ${compact ? 'compact' : ''} ${tone}`}>
        <strong>{price ? formatPrice(value, item) : formatNumber(value)}</strong><span>{label}</span></div>;
}

function MarketItem({item, active, onClick}) {
    const positive = item.direction !== 'desc';
    return (
        <button className={`marketItem ${active ? 'active' : ''}`} onClick={onClick}>
            <span className="itemIcon">{item.category === 'coin' ? <Coins size={20}/> : <BarChart3 size={20}/>}</span>
            <span className="itemMain"><b>{item.name}</b><small>{formatPrice(item.current, item)}</small></span>
            <span className={positive ? 'badge up' : 'badge down'}>{positive ? <TrendingUp size={14}/> :
                <ArrowDown size={14}/>}{formatNumber(item.percent)}٪</span>
        </button>
    );
}

function ChartTooltip({active, payload, label, item}) {
    if (!active || !payload?.length) return null;
    return <div className="tooltip">
        <span>{formatDate(label)}</span><strong>{formatPrice(payload[0].value, item)}</strong>
    </div>;
}

createRoot(document.getElementById('root')).render(<App/>);
