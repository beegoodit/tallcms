<?php

declare(strict_types=1);

namespace TallCms\Cms\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Models\Site;

/**
 * Core's site access: every site, inactive ones included (a site can be
 * prepared before it goes live). Without the multisite plugin there is one.
 */
class DefaultSiteAccess implements SiteAccess
{
    public function accessibleSites(Authenticatable $user): Collection
    {
        return $this->siteModel()::query()->orderBy('name')->get();
    }

    public function canAccess(Authenticatable $user, Site $site): bool
    {
        return true;
    }

    /**
     * Without the multisite plugin every user belongs to the one site.
     */
    public function scopeUsers(Builder $users, Site $site): Builder
    {
        return $users;
    }

    /**
     * @return class-string<Site>
     */
    protected function siteModel(): string
    {
        return config('tallcms.sites.model', Site::class);
    }
}
