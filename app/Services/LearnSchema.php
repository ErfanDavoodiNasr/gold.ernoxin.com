<?php

namespace App\Services;

class LearnSchema
{
    public function index(array $pages, string $title, string $description): array
    {
        $basePath = config('learn.base_path', '/blog');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => url($basePath . '#webpage'),
                    'url' => url($basePath),
                    'name' => $title,
                    'description' => $description,
                    'inLanguage' => 'fa-IR',
                    'isPartOf' => $this->website(),
                    'publisher' => ['@id' => url('/#organization')],
                    'about' => ['قیمت طلا', 'قیمت سکه', 'خرید طلا', 'بازار طلا ایران'],
                    'mainEntity' => ['@id' => url($basePath . '#articles')],
                    'hasPart' => collect($pages)->map(fn($page, $slug) => [
                        '@type' => 'BlogPosting',
                        'name' => $page['title'],
                        'url' => url("{$basePath}/{$slug}"),
                    ])->values()->all(),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => url($basePath) . '?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => url($basePath . '#articles'),
                    'name' => 'فهرست مقاله‌های بلاگ طلا و سکه',
                    'numberOfItems' => count($pages),
                    'itemListElement' => collect($pages)->map(fn($page, $slug) => [
                        '@type' => 'ListItem',
                        'position' => array_search($slug, array_keys($pages), true) + 1,
                        'name' => $page['title'],
                        'url' => url("{$basePath}/{$slug}"),
                    ])->values()->all(),
                ],
                $this->breadcrumb([
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => 'بلاگ', 'url' => url($basePath)],
                ]),
                $this->organization(),
                $this->website(),
            ],
        ];
    }

    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/#website'),
            'name' => 'Ernoxin Gold',
            'url' => url('/'),
            'inLanguage' => 'fa-IR',
            'publisher' => ['@id' => url('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url(config('learn.base_path', '/blog')) . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function breadcrumb(array $items, ?string $id = null): array
    {
        $schema = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->map(fn($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];

        if ($id) {
            $schema['@id'] = $id;
        }

        return $schema;
    }

    private function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/#organization'),
            'name' => 'Ernoxin Gold',
            'url' => url('/'),
            'logo' => url(config('learn.default_og_image')),
        ];
    }

    public function article(array $page): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $page['url'] . '#webpage',
                    'url' => $page['url'],
                    'name' => $page['meta_title'] ?? $page['title'],
                    'description' => $page['meta_description'],
                    'inLanguage' => 'fa-IR',
                    'dateModified' => config('learn.reviewed_at_iso'),
                    'isPartOf' => ['@id' => url('/#website')],
                    'breadcrumb' => ['@id' => $page['url'] . '#breadcrumb'],
                    'mainEntity' => ['@id' => $page['url'] . '#article'],
                ],
                [
                    '@type' => 'BlogPosting',
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
                    'author' => config('learn.author'),
                    'publisher' => config('learn.author'),
                    'articleSection' => $page['category'] ?? 'آموزش طلا و سکه',
                    'about' => $page['keywords'] ?? [],
                    'mentions' => $this->mentions($page),
                    'keywords' => implode(', ', $page['keywords'] ?? []),
                    'citation' => collect($page['sources'] ?? [])->pluck('url')->filter()->values()->all(),
                    'wordCount' => $this->wordCount($page),
                    'isAccessibleForFree' => true,
                    'image' => $page['og_image'] ?? url(config('learn.default_og_image')),
                    'speakable' => [
                        '@type' => 'SpeakableSpecification',
                        'cssSelector' => ['h1', '.lead', '.answerBox'],
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
                $this->breadcrumb([
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => 'بلاگ', 'url' => url(config('learn.base_path', '/blog'))],
                    ['name' => $page['title'], 'url' => $page['url']],
                ], $page['url'] . '#breadcrumb'),
                $this->organization(),
                $this->website(),
            ],
        ];
    }

    private function mentions(array $page): array
    {
        return collect($page['keywords'] ?? [])
            ->take(8)
            ->map(fn($keyword) => [
                '@type' => 'Thing',
                'name' => $keyword,
            ])
            ->values()
            ->all();
    }

    private function wordCount(array $page): int
    {
        $parts = [$page['title'] ?? '', $page['intro'] ?? '', $page['quick_summary'] ?? '', $page['conclusion'] ?? ''];

        foreach (($page['sections'] ?? []) as $section) {
            $parts[] = $section['heading'] ?? '';
            array_push($parts, ...($section['body'] ?? []));
        }

        foreach (($page['faqs'] ?? []) as $faq) {
            $parts[] = $faq['question'] ?? '';
            $parts[] = $faq['answer'] ?? '';
        }

        foreach (($page['important_notes'] ?? []) as $item) {
            $parts[] = $item;
        }

        foreach (($page['common_mistakes'] ?? []) as $item) {
            $parts[] = $item;
        }

        foreach (($page['decision_points'] ?? []) as $item) {
            $parts[] = $item;
        }

        $text = trim(strip_tags(implode(' ', $parts)));

        if ($text === '') {
            return 0;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0]);
    }
}
