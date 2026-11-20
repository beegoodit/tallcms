<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Concerns;

use Illuminate\Support\Collection;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Models\Site;

/**
 * For the host's user model: the sites it may open in the site panel.
 *
 * Call these from Filament's HasTenants::getTenants() / canAccessTenant()
 * when the panel's tenant is a site (see SitePanelPlugin).
 */
trait HasSiteTenants
{
    /**
     * @return Collection<int, Site>
     */
    public function getSiteTenants(): Collection
    {
        return app(SiteAccess::class)->accessibleSites($this);
    }

    public function canAccessSiteTenant(Site $site): bool
    {
        return app(SiteAccess::class)->canAccess($this, $site);
    }
}
