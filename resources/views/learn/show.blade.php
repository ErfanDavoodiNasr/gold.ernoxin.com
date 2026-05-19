@extends('layouts.learn')

@section('content')
    <x-learn-breadcrumbs :current="$page['title']" />

    <main class="contentGrid blogContent">
        <article class="articleProse">
            <header class="hero">
                <span class="eyebrow">{{ $page['category'] ?? 'مقاله آموزشی طلا و سکه' }}</span>
                <h1>{{ $page['h1'] }}</h1>
                <p class="lead">{{ $page['intro'] }}</p>
                <div class="meta">
                    <span class="pill">آخرین بازبینی محتوا: {{ config('learn.reviewed_at') }}</span>
                    @if(!empty($page['reading_time']))
                        <span class="pill">زمان مطالعه: {{ $page['reading_time'] }}</span>
                    @endif
                </div>
            </header>

            @php($tocItems = collect($page['sections'])->pluck('heading')->values())

            <section class="answerBox">
                <strong>پاسخ سریع</strong>
                <p>{{ $page['quick_summary'] ?? $page['short_answer'] ?? $page['intro'] }}</p>
            </section>

            @if(!empty($page['reader_questions']))
                <section class="readerQuestions">
                    <h2>این مقاله به چه سؤال‌هایی جواب می‌دهد؟</h2>
                    <ul>
                        @foreach($page['reader_questions'] as $question)
                            <li>{{ $question }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if(!empty($page['takeaways']))
                <section class="panel">
                    <h2>در یک نگاه</h2>
                    <ul>
                        @foreach($page['takeaways'] as $takeaway)
                            <li>{{ $takeaway }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @foreach($page['sections'] as $index => $section)
                <section class="section" id="section-{{ $index + 1 }}">
                    <h2>{{ $section['heading'] }}</h2>
                    @if(!empty($section['answer']))
                        <div class="answerBox">
                            <strong>پاسخ کوتاه</strong>
                            <p>{{ $section['answer'] }}</p>
                        </div>
                    @endif
                    @foreach($section['body'] as $paragraph)
                        <p>{!! $paragraph !!}</p>
                    @endforeach
                    @if(!empty($section['table']))
                        <table class="dataTable">
                            <thead>
                            <tr>
                                @foreach($section['table']['headers'] as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($section['table']['rows'] as $row)
                                <tr>
                                    @foreach($row as $cell)
                                        <td>{!! $cell !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach

            @if(!empty($page['important_notes']) || !empty($page['common_mistakes']))
                <section class="insightGrid" aria-label="نکات تکمیلی مقاله">
                    @if(!empty($page['important_notes']))
                        <div class="panel">
                            <h2>نکات مهم قبل از تصمیم</h2>
                            <ul>
                                @foreach($page['important_notes'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($page['common_mistakes']))
                        <div class="panel">
                            <h2>اشتباه‌های رایج</h2>
                            <ul>
                                @foreach($page['common_mistakes'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            @if(!empty($page['decision_points']))
                <section class="panel">
                    <h2>جزئیات کاربردی برای بررسی دقیق‌تر</h2>
                    <ul>
                        @foreach($page['decision_points'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if(!empty($page['glossary']))
                <section>
                    <h2>اصطلاحات رایج بازار</h2>
                    <div class="glossary">
                        @foreach($page['glossary'] as $term => $definition)
                            <div class="term">
                                <strong>{{ $term }}</strong>
                                <span>{{ $definition }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section>
                <h2>برای بررسی قیمت‌های به‌روز</h2>
                <p>اگر به عددهای لحظه‌ای نیاز دارید، صفحه قیمت زنده بهترین نقطه شروع است. در مقاله‌ها روی توضیح روشن مفاهیم و روش خواندن بازار تمرکز کرده‌ایم.</p>
                <x-learn-links :links="$page['market_links']" />
            </section>

            @if(!empty($page['related']))
                <section class="panel relatedPanel" aria-label="مسیر مطالعه مرتبط">
                    <h2>مسیر مطالعه مرتبط</h2>
                    <p>برای اینکه موضوع را در بازار ایران کامل‌تر ببینید، این مقاله‌ها را کنار همین مطلب بخوانید:</p>
                    <div class="relatedGrid">
                        @foreach($page['related'] as $relatedSlug)
                            @if(isset($pages[$relatedSlug]))
                                <a href="{{ $basePath }}/{{ $relatedSlug }}">
                                    <span>{{ $pages[$relatedSlug]['category'] ?? 'آموزش طلا و سکه' }}</span>
                                    <strong>{{ $pages[$relatedSlug]['title'] }}</strong>
                                    <small>{{ $pages[$relatedSlug]['quick_summary'] ?? $pages[$relatedSlug]['meta_description'] }}</small>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <x-learn-faq :faqs="$page['faqs']" />

            @if(!empty($page['conclusion']))
                <section class="section">
                    <h2>جمع‌بندی</h2>
                    <p>{{ $page['conclusion'] }}</p>
                </section>
            @endif

        </article>

        <aside>
            <div class="panel tocPanel">
                <h2>فهرست مقاله</h2>
                <div class="sideList">
                    <a href="#top">شروع مقاله</a>
                    @foreach($tocItems as $index => $heading)
                        <a href="#section-{{ $index + 1 }}">{{ $heading }}</a>
                    @endforeach
                    <a href="#faq">پرسش‌های متداول</a>
                </div>
            </div>

            <div class="panel">
                <h2>مطالب مرتبط</h2>
                <div class="sideList">
                    @foreach($page['related'] as $relatedSlug)
                        @if(isset($pages[$relatedSlug]))
                            <a href="{{ $basePath }}/{{ $relatedSlug }}">{{ $pages[$relatedSlug]['title'] }}</a>
                        @endif
                    @endforeach
                    <a href="/price/">قیمت طلا امروز و قیمت لحظه‌ای سکه</a>
                </div>
            </div>
        </aside>
    </main>
@endsection
