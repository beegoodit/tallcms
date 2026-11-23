<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;

/**
 * The posts, categories, media and collections a user works with in a panel:
 * those of the current site.
 *
 * They belong to a site like pages and menus; who created a record (user_id)
 * does not decide who sees it. In a site panel the site is the tenant. In a
 * panel without a tenant it is the installation's site when there is exactly
 * that one (SiteContext), and none on a multisite installation: there nobody
 * sees these records except a super admin, who sees all sites'.
 *
 * The filter is applied here explicitly: Filament's own tenant scope exists
 * only for models whose resource is registered in the panel, and a host may
 * switch a resource off.
 */
class UserOwnership
{
    public static function scope(Builder $query): Builder
    {
        $context = app(SiteContext::class);

        if ($context->tenantSiteId() !== null) {
            return static::scopeToTenant($query);
        }

        $table = $query->getModel()->getTable();

        try {
            if (! Schema::hasColumn($table, 'site_id')) {
                return $query;
            }
        } catch (\Throwable) {
            // Schema not yet migrated.
            return $query;
        }

        $siteId = $context->currentSiteId();

        if ($siteId !== null) {
            return $query->where($table.'.site_id', $siteId);
        }

        $user = auth()->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
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

        if (! Schema::hasColumn($table, 'site_id')) {
            return $query;
        }

        return $query->where($table.'.site_id', $tenantSiteId);
    }
}
