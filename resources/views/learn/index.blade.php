@extends('layouts.learn')

@section('content')
    <x-learn-breadcrumbs />

    <main>
        <section class="hero">
            <span class="eyebrow">بلاگ طلا و سکه</span>
            <h1>بلاگ طلا و سکه ارنوکسین</h1>
            <p class="lead">راهنماهای خواندنی و کاربردی برای فهم قیمت طلا، سکه، اجرت، مالیات، فاکتور و ریسک‌های خرید. هر مقاله با پاسخ سریع شروع می‌شود و بعد جزئیات را مرحله‌به‌مرحله توضیح می‌دهد.</p>
            <div class="meta">
                <span class="pill">آخرین بازبینی محتوا: {{ config('learn.reviewed_at') }}</span>
                <span class="pill">{{ count($allPages) }} مقاله ایران‌محور</span>
            </div>
        </section>

        <section class="searchPanel" aria-label="جستجوی مقاله‌های بلاگ" data-blog-search>
            <form action="{{ $basePath }}" method="get" class="blogSearch" role="search">
                <label for="blog-search">جستجو در مقاله‌ها</label>
                <div class="searchRow">
                    <input id="blog-search" name="q" value="{{ $searchQuery }}" type="search" placeholder="مثلاً حباب سکه، فاکتور طلا، اجرت، صندوق طلا" autocomplete="off" aria-controls="blog-results" aria-describedby="blog-search-meta" data-search-input>
                    <button type="button" class="searchClear" data-search-clear @if($searchQuery === '') hidden @endif>پاک کردن</button>
                </div>
                <noscript>
                    <button type="submit" class="searchSubmit">جستجو</button>
                </noscript>
            </form>
            <p class="searchMeta" id="blog-search-meta" data-search-meta data-total="{{ count($allPages) }}">
                @if($searchQuery !== '')
                    {{ $resultCount }} نتیجه مرتبط برای «{{ $searchQuery }}»
                @else
                    عبارت موردنظر را تایپ کنید تا نتایج همان لحظه بر اساس عنوان، سؤال‌ها، خلاصه و متن اصلی مرتب شوند.
                @endif
            </p>
        </section>

        @if($searchQuery === '' && !empty(config('learn.clusters')))
            <section>
                <h2>موضوعات اصلی بلاگ</h2>
                <div class="cards">
                    @foreach(config('learn.clusters') as $cluster)
                        <div class="card">
                            <h3>{{ $cluster['name'] }}</h3>
                            <p>{{ $cluster['description'] }}</p>
                            <div class="links">
                                @foreach($cluster['pages'] as $slug)
                                    @if(isset($pages[$slug]))
                                        <a href="{{ $basePath }}/{{ $slug }}">{{ $pages[$slug]['title'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-label="مقاله‌های بلاگ">
            @if($searchQuery !== '')
                <h2 data-results-title>نتایج جستجو</h2>
            @else
                <h2 data-results-title>همه مقاله‌ها</h2>
            @endif
            <div class="cards" id="blog-results" data-search-results>
                @forelse($pages as $slug => $page)
                    <article class="card articleResult">
                        <span class="eyebrow">{{ $page['category'] ?? 'آموزش طلا و سکه' }}</span>
                        <h2><a href="{{ $basePath }}/{{ $slug }}">{{ $page['title'] }}</a></h2>
                        <p>{{ $page['search_excerpt'] ?? $page['quick_summary'] ?? $page['meta_description'] }}</p>
                        <div class="articleMeta">
                            <small>{{ $page['reading_time'] ?? '۶ دقیقه' }}</small>
                            <a href="{{ $basePath }}/{{ $slug }}">خواندن مقاله</a>
                        </div>
                    </article>
                @empty
                    <div class="panel emptySearch">
                        <h2>نتیجه‌ای پیدا نشد</h2>
                        <p>عبارت کوتاه‌تر یا واژه‌های رایج بازار ایران مثل «سکه»، «اجرت»، «فاکتور»، «عیار» یا «طلای آب‌شده» را امتحان کنید.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>

    <script type="application/json" id="blog-search-index">{!! json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script>
        (function () {
            var indexNode = document.getElementById('blog-search-index');
            var panel = document.querySelector('[data-blog-search]');
            var input = document.querySelector('[data-search-input]');
            var results = document.querySelector('[data-search-results]');
            var meta = document.querySelector('[data-search-meta]');
            var title = document.querySelector('[data-results-title]');
            var clear = document.querySelector('[data-search-clear]');

            if (!indexNode || !panel || !input || !results || !meta || !title) {
                return;
            }

            var searchIndex = JSON.parse(indexNode.textContent || '[]');
            var stopWords = ['و', 'یا', 'در', 'از', 'به', 'با', 'برای', 'را', 'که', 'این', 'آن', 'اون', 'چیست', 'چیه', 'چطور', 'چگونه', 'کدام', 'ایا', 'آیا', 'بهترین', 'ای', 'تی', 'اف'];
            var genericTokens = ['طلا', 'سکه', 'قیمت', 'بازار'];
            var synonyms = {
                'ای تی اف': ['etf', 'صندوق', 'بورس'],
                'etf': ['صندوق', 'بورس'],
                'بورس': ['صندوق', 'گواهی', 'سرمایه گذاری'],
                'اب شده': ['آبشده', 'انگ'],
                'رسید': ['فاکتور'],
                'مالیات': ['ارزش افزوده'],
                'سکه': ['حباب', 'امامی', 'بهار']
            };

            function normalize(text) {
                var map = {'ي': 'ی', 'ك': 'ک', 'ۀ': 'ه', 'ة': 'ه', 'ؤ': 'و', 'إ': 'ا', 'أ': 'ا', 'آ': 'ا'};
                return String(text || '')
                    .replace(/[يكۀةؤإأآ]/g, function (char) { return map[char] || char; })
                    .toLowerCase()
                    .replace(/[^\p{L}\p{N}\s]+/gu, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function tokenize(query) {
                var normalized = normalize(query);
                var tokens = normalized.split(/\s+/).filter(function (token) {
                    return token.length >= 2 && stopWords.indexOf(token) === -1;
                });
                var specificTokens = tokens.filter(function (token) {
                    return genericTokens.indexOf(token) === -1;
                });

                if (specificTokens.length > 0) {
                    tokens = specificTokens;
                }

                if (normalized.indexOf('صندوق طلا') !== -1) {
                    tokens = ['صندوق طلا', 'صندوق سرمایه گذاری', 'etf'];
                }

                Object.keys(synonyms).forEach(function (phrase) {
                    if (normalized.indexOf(normalize(phrase)) !== -1) {
                        tokens = tokens.concat(synonyms[phrase].map(normalize));
                    }
                });

                return Array.from(new Set(tokens.filter(Boolean)));
            }

            function count(text, token) {
                return token ? Math.max(0, text.split(token).length - 1) : 0;
            }

            function matchesToken(text, token) {
                return new RegExp('(^|\\s)' + token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'u').test(text);
            }

            function scoreItem(item, tokens, normalizedQuery) {
                var fields = [
                    ['title', item.title, 30],
                    ['category', item.category, 12],
                    ['keywords', item.keywords, 18],
                    ['summary', [item.summary, item.description].join(' '), 10],
                    ['body', item.searchText, 3]
                ];

                return fields.reduce(function (sum, field) {
                    var fieldName = field[0];
                    var text = normalize(field[1]);
                    var weight = field[2];
                    var fieldScore = normalizedQuery && matchesToken(text, normalizedQuery) ? weight * 4 : 0;
                    if (fieldScore > 0 && fieldName === 'title' && text.indexOf(normalizedQuery) === 0) {
                        fieldScore += weight * 3;
                    }

                    tokens.forEach(function (token) {
                        if (matchesToken(text, token)) {
                            fieldScore += weight + Math.min(4, count(text, token));
                            fieldScore += Math.floor(weight / 2);
                        }
                    });

                    return sum + fieldScore;
                }, 0);
            }

            function stripTags(text) {
                return String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function excerpt(item, tokens) {
                var candidates = [item.summary, item.description, item.searchText];
                for (var i = 0; i < candidates.length; i += 1) {
                    var plain = stripTags(candidates[i]);
                    var normalized = normalize(plain);
                    if (tokens.some(function (token) { return matchesToken(normalized, token); })) {
                        return plain.length > 230 ? plain.slice(0, 227) + '...' : plain;
                    }
                }

                return item.summary || item.description || '';
            }

            function formatNumber(number) {
                return Number(number).toLocaleString('fa-IR');
            }

            function createResultCard(item, text) {
                var article = document.createElement('article');
                var category = document.createElement('span');
                var heading = document.createElement('h2');
                var link = document.createElement('a');
                var summary = document.createElement('p');
                var articleMeta = document.createElement('div');
                var readingTime = document.createElement('small');
                var readLink = document.createElement('a');

                article.className = 'card articleResult';
                category.className = 'eyebrow';
                articleMeta.className = 'articleMeta';

                category.textContent = item.category || 'آموزش طلا و سکه';
                link.href = item.url;
                link.textContent = item.title;
                summary.textContent = text;
                readingTime.textContent = item.readingTime || '۶ دقیقه';
                readLink.href = item.url;
                readLink.textContent = 'خواندن مقاله';

                heading.appendChild(link);
                articleMeta.appendChild(readingTime);
                articleMeta.appendChild(readLink);
                article.appendChild(category);
                article.appendChild(heading);
                article.appendChild(summary);
                article.appendChild(articleMeta);

                return article;
            }

            function render(query) {
                var normalizedQuery = normalize(query);
                var tokens = tokenize(query);
                var items = searchIndex;

                if (tokens.length > 0) {
                    items = searchIndex
                        .map(function (item) {
                            return Object.assign({}, item, {
                                searchScore: scoreItem(item, tokens, normalizedQuery),
                                searchExcerpt: excerpt(item, tokens)
                            });
                        })
                        .filter(function (item) { return item.searchScore >= 10; })
                        .sort(function (a, b) { return b.searchScore - a.searchScore; })
                        .slice(0, 18);
                }

                results.innerHTML = '';

                if (items.length === 0) {
                    var empty = document.createElement('div');
                    var emptyTitle = document.createElement('h2');
                    var emptyText = document.createElement('p');
                    empty.className = 'panel emptySearch';
                    emptyTitle.textContent = 'نتیجه‌ای پیدا نشد';
                    emptyText.textContent = 'عبارت کوتاه‌تر یا واژه‌های رایج بازار ایران مثل «سکه»، «اجرت»، «فاکتور»، «عیار»، «صندوق طلا» یا «طلای آب‌شده» را امتحان کنید.';
                    empty.appendChild(emptyTitle);
                    empty.appendChild(emptyText);
                    results.appendChild(empty);
                } else {
                    var fragment = document.createDocumentFragment();
                    items.forEach(function (item) {
                        fragment.appendChild(createResultCard(item, item.searchExcerpt || item.summary || item.description));
                    });
                    results.appendChild(fragment);
                }

                if (tokens.length > 0) {
                    title.textContent = 'نتایج جستجو';
                    meta.textContent = formatNumber(items.length) + ' نتیجه مرتبط برای «' + query.trim() + '»';
                } else {
                    title.textContent = 'همه مقاله‌ها';
                    meta.textContent = 'عبارت موردنظر را تایپ کنید تا نتایج همان لحظه بر اساس عنوان، سؤال‌ها، خلاصه و متن اصلی مرتب شوند.';
                }

                if (clear) {
                    clear.hidden = tokens.length === 0;
                }

                var url = new URL(window.location.href);
                if (query.trim() !== '') {
                    url.searchParams.set('q', query.trim());
                } else {
                    url.searchParams.delete('q');
                }
                window.history.replaceState({}, '', url.toString());
            }

            input.addEventListener('input', function () {
                render(input.value);
            });

            input.form.addEventListener('submit', function (event) {
                event.preventDefault();
                render(input.value);
            });

            if (clear) {
                clear.addEventListener('click', function () {
                    input.value = '';
                    input.focus();
                    render('');
                });
            }

            render(input.value);
        })();
    </script>
@endsection
