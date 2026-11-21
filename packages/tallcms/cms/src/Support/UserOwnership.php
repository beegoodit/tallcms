<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;

/**
 * The records of user-owned content (posts, categories, media, collections)
 * a user works with: their own in /admin, the tenant's in a site panel.
 *
 * In a site panel the records belong to the tenant (site_id stamped on
 * create), so the user_id filter would only hide the colleagues' records.
 * The tenant filter is applied here explicitly: Filament's own tenant scope
 * exists only for models whose resource is registered in the panel, and a
 * host may switch a resource off.
 */
class UserOwnership
{
    public static function scope(Builder $query): Builder
    {
        if (app(SiteContext::class)->tenantSiteId() !== null) {
            return static::scopeToTenant($query);
        }

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        try {
            if (Schema::hasColumn($table, 'user_id')) {
                return $query->where($table.'.user_id', $user->getAuthIdentifier());
            }
        } catch (\Throwable) {
            // Schema not yet migrated.
        }

        return $query;
    }

    /**
     * In a site panel: the tenant's records only. Elsewhere unchanged.
     */
    public static function scopeToTenant(Builder $query): Builder
    {
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if (! static::hasSiteIdColumn($table)) {
            return $query;
        }

        return $query->where($table.'.site_id', $tenantSiteId);
    }

    /**
     * @var array<string, bool>
     */
    protected static array $siteIdColumns = [];

    protected static function hasSiteIdColumn(string $table): bool
    {
        return static::$siteIdColumns[$table] ??= Schema::hasColumn($table, 'site_id');
    }
}
