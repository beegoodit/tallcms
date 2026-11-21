<?php

declare(strict_types=1);

namespace TallCms\Cms\Rules;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use TallCms\Cms\Contracts\SiteContext;

/**
 * User-scoped unique validation helper.
 *
 * Wraps Rule::unique() and scopes to the authenticated user's user_id
 * (or author_id for posts). Falls back to global uniqueness when the
 * column doesn't exist or no user is authenticated.
 *
 * In a site panel the records are the tenant's, so uniqueness is checked
 * within the tenant's site instead.
 */
class UserAwareUnique
{
    public static function rule(string $table, string $column, ?int $ignoreId = null): Unique
    {
        $rule = Rule::unique($table, $column);

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null && Schema::hasColumn($table, 'site_id')) {
            return $rule->where('site_id', $tenantSiteId);
        }

        $ownerColumn = static::getOwnerColumn($table);

        if ($ownerColumn && auth()->check()) {
            $rule->where($ownerColumn, auth()->id());
        }

        return $rule;
    }

    protected static function getOwnerColumn(string $table): ?string
    {
        // Posts use user_id (not author_id — author is editorial metadata)
        if ($table === 'tallcms_posts' && Schema::hasColumn($table, 'user_id')) {
            return 'user_id';
        }

        if (Schema::hasColumn($table, 'user_id')) {
            return 'user_id';
        }

        return null;
    }
}
