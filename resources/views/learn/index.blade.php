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
            </div>
        </section>

        @if(!empty(config('learn.clusters')))
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

        <section class="cards" aria-label="مقاله‌های بلاگ">
            @foreach($pages as $slug => $page)
                <article class="card">
                    <h2><a href="{{ $basePath }}/{{ $slug }}">{{ $page['title'] }}</a></h2>
                    <p>{{ $page['meta_description'] }}</p>
                </article>
            @endforeach
        </section>
    </main>
@endsection
