<?php

declare(strict_types=1);

namespace TallCms\Cms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
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
}
