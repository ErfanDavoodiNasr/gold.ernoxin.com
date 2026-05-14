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
        $pages = $this->pages();
        $title = 'بلاگ طلا و سکه ارنوکسین';
        $description = 'مقاله‌های کاربردی فارسی درباره قیمت طلا، سکه، اجرت، مالیات، فاکتور، اصالت و ریسک‌های خرید با پاسخ سریع و محتوای قابل فهم.';
        $basePath = config('learn.base_path', '/blog');

        return response()->view('learn.index', [
            'pages' => $pages,
            'basePath' => $basePath,
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => url($basePath),
                'ogImage' => url(config('learn.default_og_image')),
                'jsonLd' => $this->schema->index($pages, $title, $description),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $pages = $this->pages();
        abort_unless(isset($pages[$slug]), 404);

        $page = $pages[$slug];
        $page['slug'] = $slug;
        $basePath = config('learn.base_path', '/blog');
        $page['url'] = url("{$basePath}/{$slug}");
        $page['keywords'] = $page['keywords'] ?? [$page['title'], 'طلا', 'سکه', 'قیمت طلا'];

        return response()->view('learn.show', [
            'page' => $page,
            'pages' => $pages,
            'basePath' => $basePath,
            'seo' => [
                'title' => $page['title'],
                'description' => $page['meta_description'],
                'canonical' => $page['url'],
                'ogImage' => $page['og_image'] ?? url(config('learn.default_og_image')),
                'jsonLd' => $this->schema->article($page),
            ],
        ]);
    }

    private function pages(): array
    {
        $extras = config('learn_extras', []);
        $defaults = $extras['defaults'] ?? [];
        unset($extras['defaults']);

        return collect(config('learn.pages', []))
            ->map(fn($page, $slug) => array_merge($defaults, $page, $extras[$slug] ?? []))
            ->all();
    }
}
