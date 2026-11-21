<?php

declare(strict_types=1);

namespace TallCms\Cms\Services;

use Filament\Facades\Filament;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Models\Site;

/**
 * Core's site context: the Filament tenant if it is a site, otherwise the
 * default site (an installation without the multisite plugin has one site,
 * so every request, job and command works in it).
 */
class DefaultSiteContext implements SiteContext
{
    protected ?int $defaultSiteId = null;

    public function currentSiteId(): ?int
    {
        return $this->tenantSiteId() ?? $this->fallbackSiteId();
    }

    public function tenantSiteId(): ?int
    {
        if (! app()->bound('filament')) {
            return null;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Site ? (int) $tenant->getKey() : null;
    }

    /**
     * Where the site comes from when no site panel is involved.
     */
    protected function fallbackSiteId(): ?int
    {
        return $this->defaultSiteId();
    }

    /**
     * Memoized once found; a missing default site is looked up again, since
     * it may be created later in the same process (installer, tests).
     */
    protected function defaultSiteId(): ?int
    {
        if ($this->defaultSiteId !== null) {
            return $this->defaultSiteId;
        }

        try {
            $id = Site::query()->where('is_default', true)->value('id');
        } catch (\Throwable) {
            return null;
        }

        return $this->defaultSiteId = $id !== null ? (int) $id : null;
    }
}
