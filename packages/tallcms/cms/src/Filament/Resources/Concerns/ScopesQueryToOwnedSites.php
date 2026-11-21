<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Support\UserOwnership;

/**
 * Filter a resource's list query to records the current user owns, scoped via
 * site ownership.
 *
 * Why a query-level filter on top of the policy: Filament policies gate
 * individual-record access (view/update/etc.), but list queries don't invoke
 * policy methods per row — they just hit the database and render whatever
 * comes back. Without a query-level filter, a site_owner's Comments page
 * happily lists every tenant's comments, even though the policy would block
 * them from clicking into a row.
 *
 * super_admins bypass the filter. Single-site installs (no site_id column)
 * short-circuit before any filtering. Users with no owned sites get zero rows.
 * In a site panel, Filament's tenant scope already limits the query to the
 * tenant, so ownership by user_id does not apply there.
 */
trait ScopesQueryToOwnedSites
{
    protected static function scopeQueryToOwnedSites(Builder $query): Builder
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

        // Gate on the multisite plugin actually being booted, not just the
        // presence of a site_id column. If multisite was uninstalled or
        // disabled but the columns/tables linger, schema-only detection would
        // leave normal admins filtered to zero rows against a stale
        // tallcms_sites table.
        if (! function_exists('tallcms_multisite_active') || ! tallcms_multisite_active()) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, 'site_id')) {
            return $query;
        }

        try {
            $ownedSiteIds = DB::table('tallcms_sites')
                ->where('user_id', $user->getAuthIdentifier())
                ->pluck('id');
        } catch (\Throwable) {
            // tallcms_sites doesn't exist yet — nothing to scope against.
            return $query;
        }

        return $query->whereIn($table.'.site_id', $ownedSiteIds);
    }

    /**
     * Filter a query by user_id (record creator), regardless of whether
     * multisite is active.
     *
     * Use this for resources that the multisite plugin explicitly treats as
     * USER-OWNED rather than site-bound — Posts, Categories, Media,
     * MediaCollections. The plugin's MultisiteServiceProvider deliberately
     * does NOT stamp site_id on these models on creation; ownership is
     * carried by user_id. Filtering by site_id would make a user's freshly-
     * created records invisible to themselves in multisite installs, since
     * site_id is NULL by design.
     *
     * super_admin → bypass. No user_id column → passthrough (model isn't
     * user-owned, nothing to scope). In a site panel these records are the
     * tenant's (site_id stamped on create), and Filament's tenant scope
     * limits the query, so user_id does not apply there.
     */
    protected static function scopeQueryToOwnedByUser(Builder $query): Builder
    {
        return UserOwnership::scope($query);
    }
}
