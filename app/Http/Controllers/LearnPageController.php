<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LearnPageController extends Controller
{
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
                'jsonLd' => $this->indexJsonLd($pages, $title, $description),
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

        return response()->view('learn.show', [
            'page' => $page,
            'pages' => $pages,
            'seo' => [
                'title' => $page['title'],
                'description' => $page['meta_description'],
                'canonical' => $page['url'],
                'jsonLd' => $this->articleJsonLd($page),
            ],
        ]);
    }

    private function indexJsonLd(array $pages, string $title, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => url('/learn#webpage'),
                    'url' => url('/learn'),
                    'name' => $title,
                    'description' => $description,
                    'inLanguage' => 'fa-IR',
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => 'Ernoxin Gold',
                        'url' => url('/'),
                    ],
                    'hasPart' => collect($pages)->map(fn($page, $slug) => [
                        '@type' => 'Article',
                        'name' => $page['title'],
                        'url' => url("/learn/{$slug}"),
                    ])->values()->all(),
                ],
                $this->breadcrumbJsonLd([
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => 'آموزش', 'url' => url('/learn')],
                ]),
            ],
        ];
    }

    private function articleJsonLd(array $page): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Article',
                    '@id' => $page['url'] . '#article',
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $page['url'] . '#webpage',
                    ],
                    'headline' => $page['title'],
                    'description' => $page['meta_description'],
                    'inLanguage' => 'fa-IR',
                    'dateModified' => config('learn.reviewed_at_iso'),
                    'datePublished' => config('learn.reviewed_at_iso'),
                    'author' => [
                        '@type' => 'Organization',
                        'name' => 'Ernoxin',
                        'url' => url('/'),
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'Ernoxin',
                        'url' => url('/'),
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $page['url'] . '#faq',
                    'mainEntity' => collect($page['faqs'])->map(fn($faq) => [
                        '@type' => 'Question',
                        'name' => $faq['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $faq['answer'],
                        ],
                    ])->all(),
                ],
                $this->breadcrumbJsonLd([
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => 'آموزش', 'url' => url('/learn')],
                    ['name' => $page['title'], 'url' => $page['url']],
                ]),
            ],
        ];
    }

    private function breadcrumbJsonLd(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->map(fn($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }
}
