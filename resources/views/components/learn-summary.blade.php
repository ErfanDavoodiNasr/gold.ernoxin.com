@php
    $classes = ['ثابت و آموزشی' => 'fixed', 'وابسته به قیمت روز' => 'live', 'نیازمند بررسی منبع رسمی' => 'official'];
@endphp
<section class="summaryGrid" aria-label="تفکیک اعتبار اطلاعات">
    @foreach($summary as $heading => $items)
        <div class="summaryBox {{ $classes[$heading] ?? '' }}">
            <h3>{{ $heading }}</h3>
            <ul>
                @foreach($items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</section>
