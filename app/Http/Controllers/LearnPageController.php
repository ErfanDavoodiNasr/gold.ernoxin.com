<?php

namespace App\Http\Controllers;

use App\Services\LearnSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LearnPageController extends Controller
{
    public function __construct(private LearnSchema $schema)
    {
    }

    public function index(Request $request): Response
    {
        $allPages = $this->pages();
        $query = trim((string)$request->query('q', ''));
        $pages = $query !== '' ? $this->searchPages($allPages, $query) : $allPages;
        $title = 'بلاگ طلا و سکه ارنوکسین';
        $description = 'مقاله‌های کاربردی فارسی درباره قیمت طلا، سکه، اجرت، مالیات، فاکتور، اصالت و ریسک‌های خرید.';
        $basePath = config('learn.base_path', '/blog');

        return response()->view('learn.index', [
            'pages' => $pages,
            'allPages' => $allPages,
            'basePath' => $basePath,
            'searchQuery' => $query,
            'resultCount' => count($pages),
            'searchIndexUrl' => "{$basePath}/search-index.json",
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => url($basePath),
                'ogImage' => url(config('learn.default_og_image')),
                'type' => 'website',
                'jsonLd' => $this->schema->index($allPages, $title, $description),
            ],
        ]);
    }

    private function pages(): array
    {
        return Cache::remember($this->contentCacheKey('pages:v4'), 86400, function () {
            $extras = config('learn_extras', []);
            unset($extras['defaults']);

            return collect(config('learn.pages', []))
                ->map(function ($page, $slug) use ($extras) {
                    $sourcePage = array_merge($page, $extras[$slug] ?? []);
                    $merged = array_merge($page, $extras[$slug] ?? []);
                    $merged['_source_search_text'] = $this->pageBodyText($sourcePage);
                    unset(
                        $merged['author_note'],
                        $merged['default_sources'],
                        $merged['expert_review_notes'],
                        $merged['practical_example'],
                        $merged['decision_checklist']
                    );

                    return $this->enrichPage($merged, $slug);
                })
                ->all();
        });
    }

    private function contentCacheKey(string $name): string
    {
        $files = [
            config_path('learn.php'),
            config_path('learn_articles.php'),
            config_path('learn_extras.php'),
        ];

        $signature = collect($files)
            ->map(fn($file) => is_file($file) ? filemtime($file) . ':' . filesize($file) : 'missing')
            ->implode('|');

        return 'learn:' . $name . ':' . md5($signature);
    }

    private function pageBodyText(array $page): string
    {
        $parts = [];
        foreach (($page['sections'] ?? []) as $section) {
            $parts[] = $section['heading'] ?? '';
            foreach (($section['body'] ?? []) as $paragraph) {
                if (str_contains($paragraph, 'برای کامل‌تر شدن مسیر مطالعه')) {
                    continue;
                }
                $parts[] = $paragraph;
            }
        }
        foreach (($page['faqs'] ?? []) as $faq) {
            $parts[] = $faq['question'] ?? '';
            $parts[] = $faq['answer'] ?? '';
        }
        array_push($parts, ...($page['important_notes'] ?? []), ...($page['common_mistakes'] ?? []), ...($page['decision_points'] ?? []));

        return implode(' ', $parts);
    }

    private function enrichPage(array $page, string $slug): array
    {
        $page['sections'] = $page['sections'] ?? [];
        $page['related'] = $page['related'] ?? $this->fallbackRelated($slug);
        $page['market_links'] = $page['market_links'] ?? [
            ['label' => 'قیمت زنده طلا و سکه', 'url' => '/price/'],
            ['label' => 'روند ۳۰ روزه بازار', 'url' => '/price/trends/30'],
        ];
        $page['faqs'] = $page['faqs'] ?? [];

        $page['keywords'] = array_values(array_unique(array_merge($page['keywords'] ?? [], [
            $page['title'] ?? '',
            'قیمت طلا امروز',
            'قیمت سکه امروز',
            'راهنمای خرید طلا',
            'بازار طلا ایران',
        ])));

        return $page;
    }

    private function fallbackRelated(string $slug): array
    {
        $fallbacks = [
            'gold-price-guide',
            'gold-price-calculation',
            'gold-invoice-guide',
            'buying-gold-safely',
        ];

        return array_values(array_filter($fallbacks, fn($item) => $item !== $slug));
    }

    private function searchPages(array $pages, string $query): array
    {
        $normalizedQuery = $this->normalizeSearchText($query);
        $tokens = collect($this->searchTokens($normalizedQuery));

        if ($tokens->isEmpty()) {
            return $pages;
        }

        return collect($pages)
            ->map(function ($page, $slug) use ($tokens, $normalizedQuery) {
                $score = $this->searchScore($page, $tokens->all(), $normalizedQuery);
                if ($score < 10) {
                    return null;
                }

                $page['slug'] = $slug;
                $page['search_score'] = $score;
                $page['search_excerpt'] = $this->searchExcerpt($page, $tokens->all());
                return $page;
            })
            ->filter()
            ->sortByDesc('search_score')
            ->take(18)
            ->mapWithKeys(fn($page) => [$page['slug'] => $page])
            ->all();
    }

    private function normalizeSearchText(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(['ي', 'ك', 'ۀ', 'ة', 'ؤ', 'إ', 'أ', 'آ'], ['ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', 'ا'], $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function searchTokens(string $normalizedQuery): array
    {
        $stopWords = [
            'و', 'یا', 'در', 'از', 'به', 'با', 'برای', 'را', 'که', 'این', 'آن', 'اون',
            'چیست', 'چیه', 'چطور', 'چگونه', 'کدام', 'ایا', 'آیا', 'بهترین', 'ای', 'تی', 'اف',
        ];
        $genericTokens = ['طلا', 'سکه', 'قیمت', 'بازار'];

        $tokens = collect(preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn($token) => trim($token))
            ->filter(fn($token) => mb_strlen($token) >= 2)
            ->reject(fn($token) => in_array($token, $stopWords, true))
            ->values();

        $specificTokens = $tokens->reject(fn($token) => in_array($token, $genericTokens, true))->values();
        if ($specificTokens->isNotEmpty()) {
            $tokens = $specificTokens;
        }

        if (str_contains($normalizedQuery, 'صندوق طلا')) {
            $tokens = collect(['صندوق طلا', 'صندوق سرمایه گذاری', 'etf']);
        }

        $synonyms = [
            'ای تی اف' => ['etf', 'صندوق', 'بورس'],
            'etf' => ['صندوق', 'بورس'],
            'بورس' => ['صندوق', 'گواهی', 'سرمایه گذاری'],
            'اب شده' => ['آبشده', 'انگ'],
            'رسید' => ['فاکتور'],
            'مالیات' => ['ارزش افزوده'],
            'سکه' => ['حباب', 'امامی', 'بهار'],
        ];

        foreach ($synonyms as $phrase => $extraTokens) {
            if (str_contains($normalizedQuery, $this->normalizeSearchText($phrase))) {
                $tokens = $tokens->merge($extraTokens);
            }
        }

        return $tokens
            ->map(fn($token) => $this->normalizeSearchText($token))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function searchScore(array $page, array $tokens, string $query): int
    {
        $fields = [
            'title' => [$page['title'] ?? '', 30],
            'h1' => [$page['h1'] ?? '', 24],
            'category' => [$page['category'] ?? '', 12],
            'keywords' => [implode(' ', $page['keywords'] ?? []), 18],
            'summary' => [implode(' ', [$page['meta_description'] ?? '', $page['quick_summary'] ?? '', $page['intro'] ?? '']), 10],
            'body' => [$page['_source_search_text'] ?? $this->pageBodyText($page), 3],
        ];

        $score = 0;
        foreach ($fields as $fieldName => [$text, $weight]) {
            $normalized = $this->normalizeSearchText($text);
            if ($query !== '' && $this->containsSearchToken($normalized, $query)) {
                $score += $weight * 4;
                if (in_array($fieldName, ['title', 'h1'], true) && str_starts_with($normalized, $query)) {
                    $score += $weight * 3;
                }
            }

            foreach ($tokens as $token) {
                if ($this->containsSearchToken($normalized, $token)) {
                    $score += $weight + min(4, substr_count($normalized, $token));
                    $score += intdiv($weight, 2);
                }
            }
        }

        return $score;
    }

    private function containsSearchToken(string $normalizedText, string $token): bool
    {
        return preg_match('/(^|\s)' . preg_quote($token, '/') . '/u', $normalizedText) === 1;
    }

    private function searchExcerpt(array $page, array $tokens): string
    {
        $candidates = array_merge(
            [$page['quick_summary'] ?? '', $page['meta_description'] ?? '', $page['intro'] ?? ''],
            collect($page['sections'] ?? [])->flatMap(fn($section) => $section['body'] ?? [])->all()
        );

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, 'برای کامل‌تر شدن مسیر مطالعه')) {
                continue;
            }

            $normalized = $this->normalizeSearchText($candidate);
            foreach ($tokens as $token) {
                if ($this->containsSearchToken($normalized, $token)) {
                    return Str::limit(trim(strip_tags($candidate)), 230);
                }
            }
        }

        return Str::limit($page['meta_description'] ?? $page['intro'] ?? '', 230);
    }

    public function searchIndexJson(): JsonResponse
    {
        $ttl = 86400;
        $payload = [
            'updatedAt' => config('learn.reviewed_at_iso'),
            'items' => $this->buildSearchIndex($this->pages(), config('learn.base_path', '/blog')),
        ];

        return response()
            ->json($payload)
            ->withHeaders([
                'Cache-Control' => "public, max-age={$ttl}, s-maxage={$ttl}, stale-while-revalidate=604800",
                'X-Robots-Tag' => 'noindex',
            ]);
    }

    private function buildSearchIndex(array $pages, string $basePath): array
    {
        return Cache::remember($this->contentCacheKey('search-index:v3'), 86400, fn() => collect($pages)
            ->map(fn($page, $slug) => [
                'slug' => $slug,
                'url' => "{$basePath}/{$slug}",
                'title' => $page['title'] ?? '',
                'category' => $page['category'] ?? 'آموزش طلا و سکه',
                'summary' => $page['quick_summary'] ?? $page['meta_description'] ?? '',
                'description' => $page['meta_description'] ?? '',
                'readingTime' => $page['reading_time'] ?? '۶ دقیقه',
                'search' => [
                    'title' => $this->normalizeSearchText($page['title'] ?? ''),
                    'category' => $this->normalizeSearchText($page['category'] ?? 'آموزش طلا و سکه'),
                    'keywords' => $this->normalizeSearchText(implode(' ', $page['keywords'] ?? [])),
                    'summary' => $this->normalizeSearchText(implode(' ', [$page['meta_description'] ?? '', $page['quick_summary'] ?? '', $page['intro'] ?? ''])),
                    'body' => $this->normalizeSearchText($page['_source_search_text'] ?? $this->pageBodyText($page)),
                ],
                'plainText' => Str::limit(trim(strip_tags($page['_source_search_text'] ?? $this->pageBodyText($page))), 700),
            ])
            ->values()
            ->all());
    }

    public function show(string $slug): Response
    {
        $pages = $this->pages();
        abort_unless(isset($pages[$slug]), 404);

        $page = $pages[$slug];
        $page['slug'] = $slug;
        $basePath = config('learn.base_path', '/blog');
        $page['url'] = url("{$basePath}/{$slug}");
        $page['keywords'] = $page['keywords'] ?? [$page['title'], 'طلا', 'سکه', 'قیمت طلا'];

        return response()->view('learn.show', [
            'page' => $page,
            'pages' => $pages,
            'basePath' => $basePath,
            'seo' => [
                'title' => $page['meta_title'] ?? $page['title'],
                'description' => $page['meta_description'],
                'canonical' => $page['url'],
                'keywords' => $page['keywords'],
                'modifiedTime' => config('learn.reviewed_at_iso'),
                'publishedTime' => config('learn.reviewed_at_iso'),
                'ogImage' => $page['og_image'] ?? url(config('learn.default_og_image')),
                'type' => 'article',
                'jsonLd' => $this->schema->article($page),
            ],
        ]);
    }

    private function matchesSearchTokens(array $page, array $tokens): bool
    {
        $corpus = $this->normalizeSearchText(implode(' ', [
            $page['title'] ?? '',
            $page['h1'] ?? '',
            $page['category'] ?? '',
            implode(' ', $page['keywords'] ?? []),
            $page['meta_description'] ?? '',
            $page['quick_summary'] ?? '',
            $page['intro'] ?? '',
            $page['_source_search_text'] ?? $this->pageBodyText($page),
        ]));

        foreach ($tokens as $token) {
            if (!$this->containsSearchToken($corpus, $token)) {
                return false;
            }
        }

        return true;
    }

    private function iranMarketSection(string $slug, string $topic, array $page): array
    {
        if ($slug === 'gold-etf-fund-guide') {
            return [
                'صندوق طلا در بازار ایران فقط با قیمت روز طلا سنجیده نمی‌شود. قیمت تابلو صندوق، NAV، ترکیب دارایی، کارمزد، بازارگردان، حجم معاملات و فاصله خرید و فروش باید کنار هم خوانده شوند.',
                'اگر صندوق را با سکه فیزیکی مقایسه می‌کنید، ریسک نگهداری، اصالت و حباب سکه را در یک طرف بگذارید و ریسک نوسان بازار سرمایه، فاصله قیمت با NAV و نقدشوندگی صندوق را در طرف دیگر.',
                'این مقاله صندوق یا زمان خرید معرفی نمی‌کند؛ هدف این است که هنگام دیدن نام صندوق‌های طلا بدانید چه داده‌هایی را از صفحه رسمی صندوق و سامانه‌های معتبر بررسی کنید.',
                $this->relatedReadingSentence($slug, $page),
            ];
        }

        $base = [
            "{$topic} در بازار ایران معمولاً با چند لایه کنار هم فهمیده می‌شود: قیمت پایه بازار، نوع کالا، عیار، وزن، هزینه‌های فاکتور و شرایط فروش مجدد. اگر یکی از این لایه‌ها حذف شود، نتیجه ظاهراً ساده می‌شود اما برای تصمیم عملی قابل اتکا نیست.",
            'در ایران، یک عدد قیمت ممکن است از تابلوی بازار، گفت‌وگوی مغازه، سایت قیمت، کانال خبری یا فاکتور فروشنده بیاید. قبل از مقایسه، منبع عدد، زمان اعلام، واحد قیمت و اینکه عدد برای خرید از مشتری است یا فروش به مشتری را روشن کنید.',
        ];

        $specific = [
            'gold-bubble' => 'در حباب سکه، بازار ایران اهمیت ویژه‌ای دارد چون قیمت معامله‌شده سکه فقط از وزن طلای آن پیروی نمی‌کند؛ تقاضای نقدی، انتظارات، اختلاف خرید و فروش و نوع دقیق سکه هم اثر می‌گذارند.',
            'gold-vat' => 'در مالیات و اجزای قانونی فاکتور، اتکا به شنیده‌های بازار کافی نیست. نرخ، مبنا و شیوه ثبت باید با قانون و اطلاعیه رسمی همان زمان تطبیق داده شود.',
            'gold-coin-guide' => 'در سکه، عنوان دقیق کالا مهم است. سکه امامی، بهار آزادی، نیم، ربع و یک‌گرمی بازار و نقدشوندگی یکسان ندارند و نباید فقط با واژه کلی «سکه» مقایسه شوند.',
            'gold-invoice-guide' => 'در فاکتور طلا، خوانا بودن جزئیات از خود عدد نهایی مهم‌تر است. وزن، عیار، اجرت، سود، مالیات، تاریخ و مشخصات فروشنده باید بعداً قابل بازخوانی باشد.',
            'online-gold-buying-risks' => 'در خرید آنلاین طلا، قیمت پایین بدون مسیر تحویل روشن، فاکتور قابل پیگیری و هویت معتبر فروشنده مزیت محسوب نمی‌شود؛ چون ریسک بعد از پرداخت منتقل می‌شود.',
            'gold-etf-fund-guide' => 'در صندوق طلا، بازار ایران را باید از مسیر بازار سرمایه هم خواند. قیمت تابلو، NAV، ترکیب دارایی، کارمزد، بازارگردان و حجم معاملات کنار قیمت طلا و سکه معنی پیدا می‌کنند.',
        ];

        $base[] = $specific[$slug] ?? 'برای همین، این مقاله عدد روز یا توصیه خرید و فروش نمی‌دهد؛ هدف این است که هنگام دیدن قیمت در بازار ایران بدانید چه سؤال‌هایی باید بپرسید و کدام بخش‌ها را جداگانه بررسی کنید.';
        $base[] = $this->relatedReadingSentence($slug, $page);

        return $base;
    }

    private function relatedReadingSentence(string $slug, array $page): string
    {
        $links = $this->relatedLinks($slug, $page);
        if (!$links) {
            return 'برای تکمیل بررسی، صفحه قیمت زنده و چند مقاله هم‌خانواده را کنار این مطلب بخوانید تا تصویر کامل‌تری از بازار ایران داشته باشید.';
        }

        return 'برای کامل‌تر شدن مسیر مطالعه، بعد از این مقاله سراغ ' . implode('، ', $links) . ' هم بروید؛ این لینک‌ها کمک می‌کنند موضوع را فقط از یک زاویه نبینید.';
    }

    private function relatedLinks(string $slug, array $page): array
    {
        $rawPages = config('learn.pages', []);
        return collect($page['related'] ?? [])
            ->reject(fn($relatedSlug) => $relatedSlug === $slug || !isset($rawPages[$relatedSlug]))
            ->take(3)
            ->map(fn($relatedSlug) => '<a href="' . e(config('learn.base_path', '/blog') . '/' . $relatedSlug) . '">' . e($rawPages[$relatedSlug]['title'] ?? $relatedSlug) . '</a>')
            ->values()
            ->all();
    }

    private function topicSpecificGuidance(string $slug, string $topic): string
    {
        $guides = [
            'gold-price-guide' => 'برای این موضوع، مقایسه «طلای ۱۸ عیار»، «مظنه»، «سکه» و «انس جهانی» فقط وقتی معنی دارد که هر عدد با واحد خودش و در یک بازه زمانی نزدیک خوانده شود.',
            '18k-gold' => 'در طلای ۱۸ عیار، نسبت خلوص با قیمت نهایی یکی نیست؛ وزن، اجرت، سود فروشنده، شرایط بازخرید و نوع کالا می‌تواند نتیجه فاکتور را تغییر دهد.',
            '24k-gold' => 'طلای ۲۴ عیار در متن آموزشی به خلوص اشاره دارد، اما برای معامله واقعی باید شکل کالا، قابلیت فروش، اجرت یا کارمزد و منبع قیمت هم روشن باشد.',
            'gold-karat-difference' => 'برای مقایسه عیارها، ابتدا وزن و قیمت پایه را هم‌سطح کنید؛ یک گرم ۱۸ عیار و یک گرم ۲۴ عیار مقدار طلای خالص یکسانی ندارند.',
            'gold-making-charge' => 'در اجرت ساخت، عدد درصدی یا مبلغ ثابت را بدون دیدن وزن، طرح، روش ساخت و شرایط فروش مجدد مقایسه نکنید.',
            'gold-vat' => 'در موضوع مالیات، متن آموزشی فقط چارچوب سؤال‌ها را مشخص می‌کند؛ نرخ و مبنای محاسبه باید با قانون و اطلاعیه رسمی همان زمان تطبیق داده شود.',
            'gold-coin-guide' => 'در سکه، نوع دقیق سکه، سال یا طرح، اصالت، اختلاف خرید و فروش و وضعیت عرضه و تقاضا کنار قیمت تابلو اهمیت دارد.',
            'imami-vs-bahar-coin' => 'برای مقایسه سکه امامی و بهار آزادی، فقط عدد قیمت کافی نیست؛ نقدشوندگی، تقاضای بازار و اختلاف خرید و فروش هر کدام را جدا ببینید.',
            'gold-bubble' => 'در حباب سکه، ورودی‌های فرمول باید هم‌زمان باشند؛ استفاده از قیمت سکه امروز کنار انس یا نرخ ارز قدیمی نتیجه قابل اتکا نمی‌دهد.',
            'gold-invoice-guide' => 'در فاکتور، هر ردیف باید قابل توضیح باشد: مشخصات کالا، وزن، عیار، مبنای قیمت، اجرت، هزینه‌های قانونی، مبلغ نهایی و اطلاعات فروشنده.',
            'gold-price-board-vs-shop' => 'اختلاف قیمت تابلو و قیمت مغازه زمانی قابل بررسی است که بدانید عدد تابلو قیمت پایه است یا قیمت معامله، و چه هزینه‌هایی به فاکتور اضافه شده است.',
            'used-vs-new-gold' => 'در مقایسه طلای نو و دست دوم، اجرت کمتر تنها معیار نیست؛ اصالت، سلامت کالا، فاکتور، شرایط بازخرید و ریسک فروشنده را هم بررسی کنید.',
            'gold-price-calculation' => 'در محاسبه قیمت، ابتدا واحد وزن، عیار و قیمت مبنا را مشخص کنید و سپس فقط هزینه‌هایی را اضافه کنید که در فاکتور شفاف نوشته شده‌اند.',
            'gold-authenticity-check' => 'برای بررسی اصالت، ظاهر کالا فقط یک نشانه است؛ فاکتور، فروشنده معتبر، عیار، وزن و امکان پیگیری بعد از معامله اهمیت بیشتری دارد.',
            'online-gold-buying-risks' => 'در خرید آنلاین، قبل از پرداخت مسیر تحویل، بیمه یا مسئولیت ارسال، شرایط مرجوعی، فاکتور و اطلاعات قابل پیگیری فروشنده را بررسی کنید.',
            'gold-etf-fund-guide' => 'در صندوق طلا، واحد صندوق را با مالکیت مستقیم سکه یکی ندانید؛ NAV، ترکیب دارایی، کارمزد، نقدشوندگی و فاصله قیمت تابلو با ارزش دارایی‌ها باید هم‌زمان بررسی شود.',
            'melted-gold-ang-inquiry' => 'در انگ آب‌شده، خود کد فقط یک سرنخ است؛ اعتبار مسیر استعلام، وزن، عیار، فاکتور و فروشنده قابل پیگیری است که معامله را قابل بررسی می‌کند.',
            'gold-hallmark-code-guide' => 'در کدهای روی طلا، عدد 750 یا 18K را با فاکتور، عیار اعلام‌شده و وزن کالا تطبیق دهید؛ حک روی کالا به‌تنهایی قیمت یا اصالت کامل را ثابت نمی‌کند.',
            'gold-deposit-certificate-guide' => 'در گواهی سپرده، قواعد نماد، خزانه، کارمزد، تحویل و نقدشوندگی را قبل از مقایسه با سکه یا شمش فیزیکی بخوانید.',
            'persian-coin-guide' => 'در سکه پارسیان، وزن و عیار را دقیق‌تر از نام کالا بررسی کنید؛ این محصول با سکه بانکی مثل امامی یا بهار آزادی یکی نیست.',
            'gold-coin-vacuum-package' => 'در وکیوم سکه، بسته‌بندی سالم کمک‌کننده است اما جای بررسی اصالت، نوع دقیق سکه، فروشنده و تسویه امن را نمی‌گیرد.',
            'gold-jewelry-stone-weight' => 'در طلای نگین‌دار، وزن سنگ و اجزای غیرطلا را از وزن طلای قابل محاسبه جدا کنید تا قیمت و فروش مجدد واقع‌بینانه‌تر شود.',
            'gold-small-budget-options' => 'در خرید با بودجه کم، اجرت، اختلاف خرید و فروش و فاکتور سهم بیشتری در نتیجه نهایی دارند؛ گزینه ارزان‌تر همیشه کم‌ریسک‌تر نیست.',
            'gold-return-exchange-policy' => 'در تعویض، مرجوعی و بازخرید، هر مسیر را جداگانه بپرسید و برای موارد مهم به پاسخ قابل ثبت در فاکتور یا شرایط فروش تکیه کنید.',
            'gold-scam-red-flags' => 'در پیشنهادهای مشکوک، قیمت پایین را فقط وقتی قابل بررسی بدانید که فروشنده، فاکتور، اصالت، تحویل و مسیر پرداخت شفاف باشد.',
            'gold-wedding-set-buying-guide' => 'در سرویس طلا، زیبایی و استفاده را کنار اجرت، سنگ، وزن، شرایط تعویض و فروش مجدد ببینید؛ خرید احساسی نباید محاسبه را حذف کند.',
        ];

        return $guides[$slug] ?? "{$topic} را با داده زنده، منبع معتبر و شرایط واقعی معامله کنار هم بررسی کنید تا متن آموزشی با تصمیم عملی اشتباه گرفته نشود.";
    }

    private function practicalChecklist(string $slug): array
    {
        $checklists = [
            'gold-price-guide' => [
                'نوع قیمت را مشخص کنید: گرم ۱۸ عیار، مظنه، سکه، آب‌شده یا محصول ساخته‌شده.',
                'زمان به‌روزرسانی و منبع قیمت را کنار عدد بنویسید تا بعداً با داده قدیمی مقایسه نشود.',
                'اگر قیمت برای خرید واقعی است، اجرت، سود، مالیات یا هزینه‌های فاکتور را جدا از قیمت پایه ببینید.',
            ],
            '18k-gold' => [
                'عیار ۱۸ را با وزن یا قیمت نهایی اشتباه نگیرید؛ هر کدام یک ورودی جداست.',
                'وزن، عیار و قیمت پایه را با فاکتور تطبیق دهید.',
                'اگر کالا نو، دست دوم یا دارای سنگ است، اثر آن را روی قیمت و فروش مجدد بپرسید.',
            ],
            'gold-coin-guide' => [
                'نوع دقیق سکه را مشخص کنید: امامی، بهار آزادی، نیم، ربع یا یک‌گرمی.',
                'قیمت همان نوع سکه را با همان زمان به‌روزرسانی مقایسه کنید.',
                'اصالت، بسته‌بندی، فاکتور و اختلاف خرید و فروش را قبل از پرداخت بررسی کنید.',
            ],
            'gold-bubble' => [
                'برای هر عدد حباب، فرمول، قیمت مبنا و زمان محاسبه را بخواهید.',
                'حباب را با هزینه معامله، اختلاف خرید و فروش و نقدشوندگی کنار هم ببینید.',
                'از حباب به‌تنهایی نتیجه قطعی درباره خرید یا فروش نگیرید.',
            ],
            'gold-ounce-mesghal' => [
                'واحد هر عدد را مشخص کنید: انس، مثقال یا گرم.',
                'عیار مبنا و روش تبدیل را قبل از مقایسه بررسی کنید.',
                'عددهای جهانی و داخلی را بدون نرخ تبدیل و زمان داده کنار هم نگذارید.',
            ],
            'buying-gold-safely' => [
                'قبل از دیدن قیمت، نوع کالا و هدف خرید را مشخص کنید.',
                'فاکتور، اصالت، فروشنده، شرایط تحویل و امکان پیگیری را بررسی کنید.',
                'قیمت زنده را فقط یکی از ورودی‌های تصمیم بدانید، نه کل تصمیم.',
            ],
            '24k-gold' => [
                'خلوص ۲۴ عیار را با مناسب بودن برای هر کاربرد یکی ندانید.',
                'شکل کالا، قابلیت فروش، فاکتور و منبع قیمت را جداگانه بررسی کنید.',
                'برای مقایسه با ۱۸ عیار، تبدیل عیار و واحد وزن را دقیق انجام دهید.',
            ],
            'gold-karat-difference' => [
                'قبل از مقایسه عیارها، وزن را یکسان کنید.',
                'به یاد داشته باشید عیار بالاتر الزاماً انتخاب بهتر برای هر کاربرد نیست.',
                'برای خرید واقعی، عیار اعلام‌شده باید در فاکتور و مشخصات کالا قابل پیگیری باشد.',
            ],
            'gold-making-charge' => [
                'اجرت را جدا از قیمت طلای خام و سود فروشنده بخوانید.',
                'روش اعلام اجرت را بپرسید: مبلغی، درصدی یا ترکیبی.',
                'اثر اجرت در فروش مجدد را قبل از خرید از فروشنده سؤال کنید.',
            ],
            'gold-vat' => [
                'از مقاله آموزشی نرخ ثابت برداشت نکنید و منبع رسمی همان زمان را بررسی کنید.',
                'در فاکتور ببینید مالیات روی کدام بخش‌ها محاسبه شده است.',
                'اگر توضیح فروشنده مبهم است، قبل از پرداخت موضوع را روشن کنید.',
            ],
            'how-gold-price-is-set' => [
                'انس جهانی، نرخ ارز، عیار، وزن و بازار داخلی را جداگانه ببینید.',
                'داده‌های غیرهم‌زمان را برای نتیجه‌گیری کنار هم نگذارید.',
                'قیمت داخلی را با تبدیل ساده جهانی یکی فرض نکنید.',
            ],
            'gold-price-factors' => [
                'اثر قیمت جهانی، نرخ ارز، عرضه و تقاضا و هزینه معامله را جداگانه بررسی کنید.',
                'از یک عامل واحد نتیجه قطعی درباره آینده قیمت نگیرید.',
                'برای تصمیم عملی، اختلاف خرید و فروش و نقدشوندگی را هم وارد بررسی کنید.',
            ],
            'imami-vs-bahar-coin' => [
                'عنوان دقیق سکه را در هر منبع قیمت کنترل کنید.',
                'اختلاف قیمت دو نوع سکه را بدون توجه به نقدشوندگی و تقاضا فرصت قطعی ندانید.',
                'اصالت و شرایط فروش مجدد را برای همان نوع سکه بپرسید.',
            ],
            'used-vs-new-gold' => [
                'طلای دست دوم را فقط با اجرت کمتر نسنجید؛ فاکتور و اصالت مهم‌تر است.',
                'برای طلای نو، اجرت و شرایط فروش مجدد را جداگانه بخوانید.',
                'اگر کالا فاکتور ندارد، ریسک پیگیری و فروش بعدی را جدی‌تر بگیرید.',
            ],
            'gold-price-calculation' => [
                'محاسبه را از وزن، عیار و قیمت مبنای همان زمان شروع کنید.',
                'اجرت، سود، مالیات و هزینه‌ها را جداگانه اضافه کنید.',
                'اگر عیار متفاوت است، قبل از ضرب قیمت، تبدیل عیار را درست انجام دهید.',
            ],
            'gold-price-board-vs-shop' => [
                'بپرسید قیمت تابلو قیمت خرید است یا فروش و برای چه واحدی اعلام شده است.',
                'مبلغ نهایی مغازه را با اجزای فاکتور، نه فقط قیمت خام، مقایسه کنید.',
                'اگر اختلاف زیاد است، محاسبه مرحله‌به‌مرحله بخواهید.',
            ],
            'gold-invoice-guide' => [
                'نام فروشنده، تاریخ، نوع کالا، وزن، عیار و مبلغ نهایی را همان لحظه بخوانید.',
                'رسید کارت‌خوان را جای فاکتور کامل قبول نکنید.',
                'شرایط تعویض، مرجوعی یا بازخرید را پیش از پرداخت بپرسید.',
            ],
            'gold-authenticity-check' => [
                'ظاهر، رنگ یا سنگینی را معیار قطعی اصالت ندانید.',
                'فاکتور، فروشنده معتبر، وزن، عیار و امکان پیگیری را با هم بررسی کنید.',
                'در طلای دست دوم یا آنلاین، احتیاط و بررسی مدارک را بیشتر کنید.',
            ],
            'online-gold-buying-risks' => [
                'هویت فروشنده، مجوزهای قابل بررسی و راه ارتباط پس از خرید را کنترل کنید.',
                'شرایط تحویل، بیمه یا مسئولیت ارسال و مرجوعی را قبل از پرداخت بخوانید.',
                'قیمت پایین را بدون فاکتور و اصالت قابل پیگیری مزیت ندانید.',
            ],
            'gold-etf-fund-guide' => [
                'قیمت تابلو صندوق را کنار NAV و زمان به‌روزرسانی آن بخوانید.',
                'ترکیب دارایی، کارمزد، بازارگردان، حجم معاملات و اختلاف خرید و فروش را بررسی کنید.',
                'صندوق طلا را با سکه فیزیکی فقط بعد از مقایسه ریسک نگهداری، نقدشوندگی و کارمزدها بسنجید.',
            ],
            'melted-gold-ang-inquiry' => [
                'انگ، عیار، وزن و فاکتور را کنار هم بررسی کنید.',
                'روش استعلام یا پیگیری انگ را قبل از پرداخت بپرسید.',
                'قیمت پایین آب‌شده را بدون فروشنده قابل پیگیری مزیت ندانید.',
            ],
            'gold-hallmark-code-guide' => [
                'عدد 750 یا 18K را با فاکتور و عیار ثبت‌شده تطبیق دهید.',
                'کد روی کالا را جای فاکتور کامل قبول نکنید.',
                'در طلای دست دوم یا آنلاین، اصالت و فروشنده را جداگانه بررسی کنید.',
            ],
            'gold-deposit-certificate-guide' => [
                'مشخصات نماد، دارایی پایه و قواعد تحویل را بخوانید.',
                'کارمزد، نقدشوندگی و اختلاف قیمت خرید و فروش را بررسی کنید.',
                'گواهی سپرده را با سکه فیزیکی بدون توجه به هزینه‌ها مقایسه نکنید.',
            ],
            'persian-coin-guide' => [
                'وزن، عیار، تولیدکننده و بسته‌بندی را روی فاکتور کنترل کنید.',
                'سکه پارسیان را با ربع‌سکه یا سکه بانکی یکی فرض نکنید.',
                'اگر هدف فروش مجدد است، مبنای خرید از مشتری را قبل از خرید بپرسید.',
            ],
            'gold-coin-vacuum-package' => [
                'وکیوم را نشانه کمکی بدانید، نه تضمین اصالت.',
                'بسته‌بندی آسیب‌دیده را قبل از معامله قیمت‌گذاری و توضیح‌دار کنید.',
                'نوع سکه، خریدار معتبر و تسویه را در زمان فروش روشن کنید.',
            ],
            'gold-jewelry-stone-weight' => [
                'وزن طلا و وزن سنگ یا نگین را تا حد امکان تفکیک کنید.',
                'اثر سنگ، اجرت و طرح را در فروش مجدد بپرسید.',
                'طلای نگین‌دار را با طلای ساده فقط بر اساس وزن کل مقایسه نکنید.',
            ],
            'gold-small-budget-options' => [
                'هدف خرید را مشخص کنید: هدیه، استفاده یا نگهداری ارزش.',
                'اجرت و اختلاف خرید و فروش را در گزینه‌های کم‌مبلغ جدی بگیرید.',
                'سکه پارسیان، صندوق، آب‌شده و طلای کم‌وزن را با معیار یکسان نسنجید.',
            ],
            'gold-return-exchange-policy' => [
                'شرایط تعویض، مرجوعی و بازخرید را جداگانه بپرسید.',
                'برای موارد مهم، ثبت در فاکتور یا شرایط فروش را بخواهید.',
                'در خرید آنلاین، زمان تحویل، مغایرت و برگشت کالا را قبل از پرداخت بخوانید.',
            ],
            'gold-scam-red-flags' => [
                'قیمت خیلی پایین را با وزن، عیار، فاکتور و تحویل کنترل کنید.',
                'به فروشنده غیرقابل پیگیری یا پرداخت به حساب نامرتبط اعتماد نکنید.',
                'اگر عجله یا ابهام زیاد است، معامله را متوقف و منبع معتبر را بررسی کنید.',
            ],
            'gold-wedding-set-buying-guide' => [
                'وزن، عیار، اجرت، سنگ و شرایط تعویض سرویس را قبل از پرداخت بخوانید.',
                'برای سرویس نگین‌دار، وزن و ارزش سنگ را جداگانه بپرسید.',
                'اگر فروش مجدد مهم است، طرح‌های بسیار خاص و اجرت بالا را با احتیاط بیشتری بسنجید.',
            ],
        ];

        return $checklists[$slug] ?? [
            'نوع کالا، واحد، عیار، زمان قیمت و منبع داده را قبل از مقایسه روشن کنید.',
            'فاکتور، اصالت، فروشنده و شرایط فروش مجدد را جدا از عدد قیمت بررسی کنید.',
            'برای داده‌های زنده از صفحه قیمت استفاده کنید و از مقاله آموزشی عدد روز برداشت نکنید.',
        ];
    }
}
