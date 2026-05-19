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
                    'hasPart' => collect($pages)->map(fn($page, $slug) => [
                        '@type' => 'Article',
                        'name' => $page['title'],
                        'url' => url("{$basePath}/{$slug}"),
                    ])->values()->all(),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => url($basePath) . '?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                $this->breadcrumb([
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => 'بلاگ', 'url' => url($basePath)],
                ]),
            ],
        ];
    }

    public function article(array $page): array
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
                    'author' => config('learn.author'),
                    'publisher' => config('learn.author'),
                    'articleSection' => $page['category'] ?? 'آموزش طلا و سکه',
                    'about' => $page['keywords'] ?? [],
                    'keywords' => implode(', ', $page['keywords'] ?? []),
                    'wordCount' => $this->wordCount($page),
                    'isAccessibleForFree' => true,
                    'image' => $page['og_image'] ?? url(config('learn.default_og_image')),
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
                ]),
                $this->website(),
            ],
        ];
    }

    public function breadcrumb(array $items): array
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

    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            'name' => 'Ernoxin Gold',
            'url' => url('/'),
            'inLanguage' => 'fa-IR',
        ];
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
