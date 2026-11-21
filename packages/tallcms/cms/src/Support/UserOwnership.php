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
 * create) and Filament's tenant scope limits every query on them, so the
 * user_id filter would only hide the colleagues' records.
 */
class UserOwnership
{
    public static function scope(Builder $query): Builder
    {
        if (app(SiteContext::class)->tenantSiteId() !== null) {
            return $query;
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
}
