<?php

namespace App\Http\Controllers;

use App\Services\LearnSchema;
use Illuminate\Http\Response;

class LearnPageController extends Controller
{
    public function __construct(private LearnSchema $schema)
    {
    }

    public function index(): Response
    {
        $pages = config('learn.pages', []);
        $title = 'آموزش طلا و سکه';
        $description = 'مجموعه مقاله‌های آموزشی فارسی درباره طلا و سکه با تمرکز بر اطلاعات ثابت، داده‌های وابسته به قیمت روز و موارد نیازمند بررسی منبع رسمی.';

        return response()->view('learn.index', [
            'pages' => $pages,
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => url('/learn'),
                'ogImage' => url(config('learn.default_og_image')),
                'jsonLd' => $this->schema->index($pages, $title, $description),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $pages = config('learn.pages', []);
        abort_unless(isset($pages[$slug]), 404);

        $page = $pages[$slug];
        $page['slug'] = $slug;
        $page['url'] = url("/learn/{$slug}");
        $page['quality_score'] = $page['quality_score'] ?? [
            'شفافیت factual' => 8,
            'کیفیت SEO' => 8,
            'استخراج AI' => 8,
            'خوانایی' => 8,
            'لینک‌سازی داخلی' => 8,
            'کاربردی بودن' => 8,
        ];
        $page['keywords'] = $page['keywords'] ?? [$page['title'], 'طلا', 'سکه', 'قیمت طلا'];

        return response()->view('learn.show', [
            'page' => $page,
            'pages' => $pages,
            'seo' => [
                'title' => $page['title'],
                'description' => $page['meta_description'],
                'canonical' => $page['url'],
                'ogImage' => $page['og_image'] ?? url(config('learn.default_og_image')),
                'jsonLd' => $this->schema->article($page),
            ],
        ]);
    }
}
