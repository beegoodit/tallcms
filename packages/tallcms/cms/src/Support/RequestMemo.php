<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Closure;

/**
 * Keeps a computed value for the rest of the current request, e.g. a count
 * the navigation asks for twice (badge and badge colour). Stored on the
 * request object, so it never outlives the request (long-lived workers).
 */
class RequestMemo
{
    public static function remember(string $key, Closure $compute): mixed
    {
        $attributes = request()->attributes;
        $key = 'tallcms.memo.'.$key;

        if (! $attributes->has($key)) {
            $attributes->set($key, $compute());
        }

        return $attributes->get($key);
    }
}
