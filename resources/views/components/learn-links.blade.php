<div class="links">
    @foreach($links as $link)
    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
    @endforeach
</div>
