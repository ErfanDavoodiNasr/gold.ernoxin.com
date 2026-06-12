{!! '<? xml version = "1.0" encoding = "UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>بلاگ طلا و سکه ارنوکسین</title>
        <link>
        {{ url($basePath) }}</link>
        <description>مقاله‌های کاربردی فارسی درباره قیمت طلا، سکه، اجرت، مالیات، فاکتور و ریسک‌های خرید در بازار
            ایران.
        </description>
        <language>fa-IR</language>
        <lastBuildDate>{{ $lastBuildDate }}</lastBuildDate>
        <atom:link href="{{ url(" {$basePath}
        /feed.xml") }}" rel="self" type="application/rss+xml"/>
        @foreach($items as $item)
        <item>
            <title>{{ $item['title'] }}</title>
            <link>
            {{ $item['url'] }}</link>
            <guid isPermaLink="true">{{ $item['url'] }}</guid>
            <description>{{ $item['description'] }}</description>
            <pubDate>{{ $item['pubDate'] }}</pubDate>
            <category>{{ $item['category'] }}</category>
        </item>
        @endforeach
    </channel>
</rss>
