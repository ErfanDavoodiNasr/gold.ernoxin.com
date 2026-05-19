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

        <section class="searchPanel" aria-label="جستجوی مقاله‌های بلاگ">
            <form action="{{ $basePath }}" method="get" class="blogSearch">
                <label for="blog-search">جستجو در مقاله‌ها</label>
                <div class="searchRow">
                    <input id="blog-search" name="q" value="{{ $searchQuery }}" type="search" placeholder="مثلاً حباب سکه، فاکتور طلا، اجرت، طلای آب‌شده">
                    <button type="submit">جستجو</button>
                    @if($searchQuery !== '')
                        <a href="{{ $basePath }}">پاک کردن</a>
                    @endif
                </div>
            </form>
            @if($searchQuery !== '')
                <p class="searchMeta">
                    {{ $resultCount }} نتیجه مرتبط برای «{{ $searchQuery }}»
                </p>
            @else
                <p class="searchMeta">عبارت موردنظر را وارد کنید تا مقاله‌ها بر اساس عنوان، سؤال‌ها، خلاصه و متن اصلی مرتب شوند.</p>
            @endif
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
                <h2>نتایج جستجو</h2>
            @else
                <h2>همه مقاله‌ها</h2>
            @endif
            <div class="cards">
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
@endsection
