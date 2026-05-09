import React, {useEffect, useMemo, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis} from 'recharts';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    Coins,
    Moon,
    RefreshCw,
    Search,
    Sun,
    TrendingUp,
    WalletCards
} from 'lucide-react';
import '../css/app.css';

const defaultConfig = {
    chartDefaultRangeDays: 7,
    chartAvailableRanges: [1, 7, 30, 90],
    themeDefault: 'dark',
    themeAccent: '#d9a441',
    sourceName: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    sourceUrl: 'https://www.estjt.ir/price/',
};

function formatNumber(value, options = {}) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 2, ...options}).format(value);
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

function App() {
    const [config, setConfig] = useState(defaultConfig);
    const [theme, setTheme] = useState(localStorage.getItem('theme') || defaultConfig.themeDefault);
    const [items, setItems] = useState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [history, setHistory] = useState([]);
    const [analytics, setAnalytics] = useState(null);
    const [query, setQuery] = useState('');
    const [range, setRange] = useState(defaultConfig.chartDefaultRangeDays);
    const [status, setStatus] = useState('loading');
    const [error, setError] = useState('');
    const [lastFetch, setLastFetch] = useState(null);

    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.setProperty('--gold', config.themeAccent || defaultConfig.themeAccent);
        localStorage.setItem('theme', theme);
    }, [theme, config.themeAccent]);

    async function loadSummary() {
        setStatus('loading');
        setError('');
        try {
            const res = await fetch('/api/market/summary', {headers: {Accept: 'application/json'}});
            if (!res.ok) throw new Error('summary_failed');
            const data = await res.json();
            const nextConfig = {...defaultConfig, ...(data.config || {})};
            setConfig(nextConfig);
            setTheme((current) => current || nextConfig.themeDefault);
            setRange((current) => current || nextConfig.chartDefaultRangeDays);
            setItems(data.items || []);
            setLastFetch(data.lastFetch || null);
            setSelectedId((current) => current || data.items?.[0]?.id || null);
            setStatus('ready');
        } catch {
            setError('ارتباط با API برقرار نشد. تنظیمات سرور، دیتابیس و route ها را بررسی کنید.');
            setStatus('error');
            setItems([]);
            setHistory([]);
        }
    }

    useEffect(() => {
        loadSummary();
    }, []);

    const selected = useMemo(() => items.find((item) => item.id === selectedId) || items[0] || null, [items, selectedId]);

    useEffect(() => {
        if (!selected) return;
        setHistory([]);
        setAnalytics(null);
        fetch(`/api/market/items/${selected.id}/history?days=${range}`, {headers: {Accept: 'application/json'}})
            .then((r) => {
                if (!r.ok) throw new Error('history_failed');
                return r.json();
            })
            .then((data) => {
                setHistory(data.points || []);
                setAnalytics(data.analytics || null);
            })
            .catch(() => {
                setHistory([]);
                setAnalytics(null);
            });
    }, [selected?.id, range]);

    const filtered = useMemo(() => items.filter((item) => item.name.includes(query)), [items, query]);
    const gainers = items.filter((item) => item.direction === 'asc').length;
    const losers = items.filter((item) => item.direction === 'desc').length;
    const ranges = config.chartAvailableRanges?.length ? config.chartAvailableRanges : defaultConfig.chartAvailableRanges;
    const fetchNotice = fetchStatusMessage(lastFetch, items.length);

    return (
        <main className="shell">
            <header className="topbar">
                <div className="brand">
                    <span className="logo"><Coins size={25}/></span>
                    <div><h1>سکه و طلای ارنوکسین</h1><p>پایش قیمت طلا و سکه با داده‌های {config.sourceName}</p></div>
                </div>
                <div className="actions">
                    <button className="iconButton" onClick={loadSummary} title="به‌روزرسانی" aria-label="به‌روزرسانی">
                        <RefreshCw size={19} className={status === 'loading' ? 'spin' : ''}/>
                    </button>
                    <button className="iconButton" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
                            title="تغییر پوسته" aria-label="تغییر پوسته">
                        {theme === 'dark' ? <Sun size={19}/> : <Moon size={19}/>}
                    </button>
                </div>
            </header>

            <section className="hero">
                <div>
                    <span className="eyebrow">منبع رسمی: {config.sourceUrl}</span>
                    <h2>داشبورد تحلیلی قیمت طلا و سکه</h2>
                    <p>این سامانه برای بررسی روند، تغییرات تاریخی و مقایسه سریع بازار طلا و سکه طراحی شده است.</p>
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
                        <strong>بازارها</strong>
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
                            <h3>{selected?.name || 'داده‌ای انتخاب نشده است'}</h3></div>
                        <div className="range">{ranges.map((d) => <button key={d}
                                                                          className={range === d ? 'active' : ''}
                                                                          onClick={() => setRange(d)}>{formatNumber(d)} روز</button>)}</div>
                    </div>

                    <div className="priceLine">
                        <strong>{formatNumber(selected?.current)}</strong>
                        <span className={selected?.direction === 'desc' ? 'down' : 'up'}>
              {selected?.direction === 'desc' ? <ArrowDown size={16}/> : <ArrowUp size={16}/>}
                            {formatNumber(selected?.change)} ({formatNumber(selected?.percent)}٪)
            </span>
                    </div>

                    <div className="analyticsGrid">
                        <Metric value={analytics?.min} label="کمترین بازه" compact/>
                        <Metric value={analytics?.max} label="بیشترین بازه" compact/>
                        <Metric value={analytics?.avg} label="میانگین" compact/>
                        <Metric value={analytics?.changePercent} label="بازده بازه ٪" compact
                                tone={analytics?.changePercent < 0 ? 'down' : 'up'}/>
                    </div>

                    <div className="chartWrap">
                        {history.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={history} margin={{top: 12, right: 10, left: 0, bottom: 0}}>
                                    <defs>
                                        <linearGradient id="goldGradient" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stopColor="var(--gold)" stopOpacity="0.55"/>
                                            <stop offset="100%" stopColor="var(--gold)" stopOpacity="0.02"/>
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false}/>
                                    <XAxis dataKey="time" hide/>
                                    <YAxis orientation="right" width={82}
                                           tickFormatter={(v) => formatNumber(v / 1_000_000)}/>
                                    <Tooltip content={<ChartTooltip/>}/>
                                    <Area type="monotone" dataKey="current" stroke="var(--gold)" strokeWidth={3}
                                          fill="url(#goldGradient)" isAnimationActive/>
                                </AreaChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="chartEmpty"><WalletCards size={30}/><span>برای این بازه هنوز تاریخچه‌ای ثبت نشده است.</span>
                            </div>
                        )}
                    </div>
                </section>
            </section>
        </main>
    );
}

function Metric({value, label, compact = false, tone = ''}) {
    return <div className={`metric ${compact ? 'compact' : ''} ${tone}`}>
        <strong>{formatNumber(value)}</strong><span>{label}</span></div>;
}

function MarketItem({item, active, onClick}) {
    const positive = item.direction !== 'desc';
    return (
        <button className={`marketItem ${active ? 'active' : ''}`} onClick={onClick}>
            <span className="itemIcon">{item.category === 'coin' ? <Coins size={20}/> : <BarChart3 size={20}/>}</span>
            <span className="itemMain"><b>{item.name}</b><small>{formatNumber(item.current)}</small></span>
            <span className={positive ? 'badge up' : 'badge down'}>{positive ? <TrendingUp size={14}/> :
                <ArrowDown size={14}/>}{formatNumber(item.percent)}٪</span>
        </button>
    );
}

function ChartTooltip({active, payload, label}) {
    if (!active || !payload?.length) return null;
    return <div className="tooltip"><span>{formatDate(label)}</span><strong>{formatNumber(payload[0].value)}</strong>
    </div>;
}

createRoot(document.getElementById('root')).render(<App/>);
