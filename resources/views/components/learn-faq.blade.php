<section class="panel" id="faq">
    <h2>پرسش‌های متداول</h2>
    @foreach($faqs as $faq)
        <details class="faqItem">
            <summary>{{ $faq['question'] }}</summary>
            <p>{{ $faq['answer'] }}</p>
        </details>
    @endforeach
</section>
