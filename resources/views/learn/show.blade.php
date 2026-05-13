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

            <div class="disclaimer">{{ config('learn.disclaimer') }}</div>

            @foreach($page['sections'] as $section)
                <section class="section">
                    <h2>{{ $section['heading'] }}</h2>
                    @foreach($section['body'] as $paragraph)
                        <p>{!! $paragraph !!}</p>
                    @endforeach
                </section>
            @endforeach

            <section>
                <h2>لینک‌های مرتبط با قیمت‌های به‌روز</h2>
                <p>این مقاله عدد قیمت روز را ذخیره نمی‌کند. برای داده‌های وابسته به بازار، لینک‌های زیر را بررسی کنید.</p>
                <x-learn-links :links="$page['market_links']" />
            </section>

            <x-learn-faq :faqs="$page['faqs']" />
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
        </aside>
    </main>
@endsection
