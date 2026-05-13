<section class="panel" id="faq">
    <h2>پرسش‌های متداول</h2>
    @foreach($faqs as $faq)
        <div class="faqItem">
            <h3>{{ $faq['question'] }}</h3>
            <p>{{ $faq['answer'] }}</p>
        </div>
    @endforeach
</section>
