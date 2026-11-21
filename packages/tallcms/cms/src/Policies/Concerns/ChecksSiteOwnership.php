<?php

declare(strict_types=1);

namespace TallCms\Cms\Policies\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;

/**
 * Layers site-ownership authorization on top of Shield's role/permission
 * checks. Used by record-scoped methods on policies for site-owned content
 * (CmsPage, TallcmsMenu) in multisite installs.
 *
 * Rule:
 *   - in a site panel: the record belongs to the tenant (the user's access to
 *     the tenant was checked by SiteAccess when the panel resolved it)
 *   - super_admin bypass
 *   - otherwise, user.id must equal the owning site's user_id
 *
 * Outside multisite (no `site_id` column on the content table, or the
 * multisite plugin isn't active), ownership doesn't apply and the check
 * passes through — single-site installs retain their pre-multisite behavior.
 */
trait ChecksSiteOwnership
{
    /**
     * Check whether the user is allowed to act on a record belonging to a
     * given site. Pass the record's `site_id` directly; caller doesn't need
     * to load the Site model.
     */
    protected function userOwnsContentSite(Authenticatable $user, ?int $siteId): bool
    {
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null) {
            return $siteId === $tenantSiteId;
        }

        if (! $this->multisiteScopingActive()) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        if ($siteId === null) {
            // Orphaned record — only super-admins (handled above) can touch it.
            // Owners get denied so the orphan is surfaced rather than silently
            // attached to the first admin who edits it.
            return false;
        }

        return DB::table('tallcms_sites')
            ->where('id', $siteId)
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();
    }

    /**
     * In a site panel, whether the record belongs to the tenant; null outside
     * a site panel. For user-owned content (posts, categories, media,
     * collections), which is the tenant's in the site panel and the user's
     * in /admin.
     */
    protected function recordIsInTenantSite(?int $siteId): ?bool
    {
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        return $tenantSiteId === null ? null : $siteId === $tenantSiteId;
    }

    /**
     * Whether multisite scoping is actually in play for this install.
     * Cheaper than loading the full multisite helper: we just need to know
     * whether `tallcms_sites` exists and whether site_id columns are on the
     * content tables.
     */
    protected function multisiteScopingActive(): bool
    {
        try {
            return Schema::hasTable('tallcms_sites');
        } catch (\Throwable) {
            return false;
        }
    }
}
