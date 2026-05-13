@extends('layouts.learn')

@section('content')
    <x-learn-breadcrumbs :current="$page['title']" />

    <main class="contentGrid">
        <article>
            <header class="hero">
                <span class="eyebrow">مقاله آموزشی طلا و سکه</span>
                <h1>{{ $page['h1'] }}</h1>
                <p class="lead">{{ $page['intro'] }}</p>
                <div class="meta">
                    <span class="pill">آخرین بازبینی محتوا: {{ config('learn.reviewed_at') }}</span>
                    <span class="pill">{{ config('learn.disclaimer') }}</span>
                </div>
            </header>

            <x-learn-summary :summary="$page['summary']" />

            <section class="answerBox">
                <strong>تعریف کوتاه</strong>
                <p>{{ $page['short_answer'] ?? $page['intro'] }}</p>
            </section>

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

            <div class="disclaimer">{{ config('learn.disclaimer') }}</div>

            @foreach($page['sections'] as $section)
                <section class="section">
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

            @if(!empty($page['glossary']))
                <section>
                    <h2>واژه‌نامه کوتاه</h2>
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
                <h2>لینک‌های مرتبط با قیمت‌های به‌روز</h2>
                <p>این مقاله عدد قیمت روز را ذخیره نمی‌کند. برای داده‌های وابسته به بازار، لینک‌های زیر را بررسی کنید.</p>
                <x-learn-links :links="$page['market_links']" />
            </section>

            <x-learn-faq :faqs="$page['faqs']" />

            <section class="panel">
                <h2>نویسنده و منابع</h2>
                <p>{{ config('learn.author_note') }}</p>
                <ul class="sourceList">
                    @foreach(($page['sources'] ?? config('learn.default_sources')) as $source)
                        <li><a href="{{ $source['url'] }}" rel="nofollow noopener">{{ $source['label'] }}</a> - {{ $source['note'] }}</li>
                    @endforeach
                </ul>
            </section>
        </article>

        <aside>
            <div class="panel">
                <h2>مطالب مرتبط</h2>
                <div class="sideList">
                    @foreach($page['related'] as $relatedSlug)
                        @if(isset($pages[$relatedSlug]))
                            <a href="/learn/{{ $relatedSlug }}">{{ $pages[$relatedSlug]['title'] }}</a>
                        @endif
                    @endforeach
                    <a href="/price/">قیمت طلا امروز و قیمت لحظه‌ای سکه</a>
                </div>
            </div>

            <div class="panel" style="margin-top:14px">
                <h2>موارد نیازمند بازبینی تخصصی</h2>
                <ul class="note">
                    @foreach($page['expert_review_notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>

            @if(!empty($page['quality_score']))
                <div class="panel" style="margin-top:14px">
                    <h2>امتیاز کیفیت محتوا</h2>
                    <div class="scoreGrid">
                        @foreach($page['quality_score'] as $label => $score)
                            <span>{{ $label }}: {{ $score }}/۱۰</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </main>
@endsection
