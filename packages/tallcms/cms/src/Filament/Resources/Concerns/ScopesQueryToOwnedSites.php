<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Support\UserOwnership;

/**
 * Limit a resource's list query to the sites the current user may work in.
 *
 * Why a query-level filter on top of the policy: policies gate single
 * records, but a list query is not checked per row.
 *
 * In a site panel the query is limited to the tenant. Anywhere else
 * SiteAccess names the user's sites; a super admin also sees records without
 * a site. The user stored on a site (tallcms_sites.user_id) grants nothing.
 */
trait ScopesQueryToOwnedSites
{
    protected static function scopeQueryToOwnedSites(Builder $query): Builder
    {
        if (app(SiteContext::class)->tenantSiteId() !== null) {
            return UserOwnership::scopeToTenant($query);
        }

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, 'site_id')) {
            return $query;
        }

        return $query->whereIn(
            $table.'.site_id',
            app(SiteAccess::class)->accessibleSites($user)->modelKeys(),
        );
    }

    /**
     * Posts, categories, media and collections: those of the current site
     * (see UserOwnership). The name is historical; who created a record does
     * not decide who sees it.
     */
    protected static function scopeQueryToOwnedByUser(Builder $query): Builder
    {
        return UserOwnership::scope($query);
    }
}
