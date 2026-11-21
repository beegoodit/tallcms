<?php

declare(strict_types=1);

namespace TallCms\Cms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use TallCms\Cms\Models\Site;

/**
 * Which sites a user may open in the site panel.
 *
 * Who gets into the panel at all is the host app's canAccessPanel(); this
 * decides between sites. Plugins (multisite: team membership) bind their own.
 */
interface SiteAccess
{
    /**
     * @return Collection<int, Site> Sites the user may open, ordered by name.
     */
    public function accessibleSites(Authenticatable $user): Collection;

    public function canAccess(Authenticatable $user, Site $site): bool;

    /**
     * Limit a query on the host's user model to the users who belong to the
     * site, for selects that offer users (e.g. a page's author). Not an
     * access check: super admins may open every site without belonging to it.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $users
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function scopeUsers(Builder $users, Site $site): Builder;
}
