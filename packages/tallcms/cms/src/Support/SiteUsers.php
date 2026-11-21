<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Models\Site;

/**
 * The users a form may offer for a record of the current site (author
 * selects): in a site panel the users of the tenant (SiteAccess::scopeUsers()),
 * the record's current author, so existing content keeps saving, and the
 * user at work. Outside a site panel every user, as before.
 */
class SiteUsers
{
    public static function scope(Builder $users, ?Model $record, string $authorColumn = 'author_id'): Builder
    {
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId === null) {
            return $users;
        }

        $siteModel = config('tallcms.sites.model', Site::class);
        $site = $siteModel::query()->find($tenantSiteId);

        if (! $site instanceof Site) {
            return $users->whereRaw('1 = 0');
        }

        $key = $users->getModel()->getQualifiedKeyName();
        $ofSite = app(SiteAccess::class)
            ->scopeUsers($users->getModel()->newQuery(), $site)
            ->select($key);
        $always = array_values(array_filter([$record?->getAttribute($authorColumn), auth()->id()]));

        return $users->where(function (Builder $query) use ($key, $ofSite, $always): void {
            $query->whereIn($key, $ofSite)->orWhereIn($key, $always);
        });
    }
}
