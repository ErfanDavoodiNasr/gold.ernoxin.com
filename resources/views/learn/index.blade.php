@extends('layouts.learn')

@section('content')
    <x-learn-breadcrumbs />

    <main>
        <section class="hero">
            <span class="eyebrow">مرکز آموزش طلا و سکه</span>
            <h1>آموزش طلا و سکه</h1>
            <p class="lead">مقاله‌های این بخش برای توضیح مفاهیم پایدار نوشته شده‌اند. قیمت‌های روز، مقررات و ادعاهای رسمی در متن مقاله‌ها ثابت نمی‌شوند و باید از صفحه بازار زنده یا منابع رسمی بررسی شوند.</p>
            <div class="meta">
                <span class="pill">آخرین بازبینی محتوا: {{ config('learn.reviewed_at') }}</span>
                <span class="pill">{{ config('learn.disclaimer') }}</span>
            </div>
        </section>

        @if(!empty(config('learn.clusters')))
            <section>
                <h2>نقشه موضوعی محتوا</h2>
                <div class="cards">
                    @foreach(config('learn.clusters') as $cluster)
                        <div class="card">
                            <h3>{{ $cluster['name'] }}</h3>
                            <p>{{ $cluster['description'] }}</p>
                            <div class="links">
                                @foreach($cluster['pages'] as $slug)
                                    @if(isset($pages[$slug]))
                                        <a href="/learn/{{ $slug }}">{{ $pages[$slug]['title'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="cards" aria-label="صفحه‌های آموزشی">
            @foreach($pages as $slug => $page)
                <article class="card">
                    <h2><a href="/learn/{{ $slug }}">{{ $page['title'] }}</a></h2>
                    <p>{{ $page['meta_description'] }}</p>
                </article>
            @endforeach
        </section>
    </main>
@endsection
