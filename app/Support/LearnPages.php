<?php

namespace App\Support;

/**
 * Lazy learn article bodies. Kept out of config/ so /price and API bootstraps stay light.
 */
class LearnPages
{
    private static ?array $pages = null;

    private static ?array $extras = null;

    public static function all(): array
    {
        return self::$pages ??= require resource_path('data/learn_pages.php');
    }

    public static function extras(): array
    {
        return self::$extras ??= require resource_path('data/learn_extras.php');
    }

    /** @return list<string> Paths that invalidate the learn content cache when changed. */
    public static function contentFiles(): array
    {
        return [
            config_path('learn.php'),
            resource_path('data/learn_articles.php'),
            resource_path('data/learn_inline_pages.php'),
            resource_path('data/learn_extras.php'),
        ];
    }
}
