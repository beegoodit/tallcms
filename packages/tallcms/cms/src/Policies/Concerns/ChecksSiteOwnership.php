<?php

declare(strict_types=1);

namespace TallCms\Cms\Policies\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Models\Site;

/**
 * The site half of a content policy: on top of the permission, the record's
 * site must be one the user may work in.
 *
 * Rule:
 *   - in a site panel: the record belongs to the tenant (the user's access to
 *     the tenant was checked by SiteAccess when the panel resolved it);
 *   - anywhere else (previews, API, tools, jobs): SiteAccess decides for the
 *     record's site;
 *   - a record without a site is a super admin's only, so it is surfaced
 *     rather than silently attached to whoever edits it first.
 *
 * Who created a record (user_id, author_id) and the user stored on the site
 * (tallcms_sites.user_id) grant nothing.
 */
trait ChecksSiteOwnership
{
    /**
     * Whether the user may work on a record of the given site. Pass the
     * record's `site_id`.
     */
    protected function userMayAccessContentSite(Authenticatable $user, int|string|null $siteId): bool
    {
        $siteId = $siteId === null ? null : (int) $siteId;
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null) {
            return $siteId === $tenantSiteId;
        }

        if ($siteId === null) {
            return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        }

        $siteModel = config('tallcms.sites.model', Site::class);
        $site = $siteModel::query()->withoutGlobalScopes()->find($siteId);

        return $site instanceof Site && app(SiteAccess::class)->canAccess($user, $site);
    }
}
