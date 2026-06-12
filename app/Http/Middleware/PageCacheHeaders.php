<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PageCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') || !$response->isSuccessful()) {
            return $response;
        }

        if ($request->is('api/*') || $request->is('blog/search-index.json')) {
            return $response;
        }

        if ($request->is('price', 'price/', 'price/trends/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=120, stale-while-revalidate=300');
            return $response;
        }

        if ($request->is('price/mozaneh', 'price/coin-bubble', 'price/ounce')) {
            return $response;
        }

        if ($request->is('blog', 'blog/*') && !$request->is('blog/search-index.json', 'blog/feed.xml')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800');
            return $response;
        }

        if ($request->is('sitemap.xml')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=86400');
            return $response;
        }

        return $response;
    }
}
