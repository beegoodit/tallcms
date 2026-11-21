<?php

declare(strict_types=1);

namespace TallCms\Cms\Contracts;

/**
 * The site the current request, job or command works in.
 *
 * Backend: the Filament tenant, when the panel's tenant is a site.
 * Frontend: the site the host resolves to. Asked lazily, on first use,
 * so Livewire calls see the tenant that the panel middleware restored.
 */
interface SiteContext
{
    /**
     * The site new content belongs to, or null if there is no context.
     */
    public function currentSiteId(): ?int;

    /**
     * The site panel's tenant, or null outside a site panel. Inside a site
     * panel, access to the tenant was already checked (SiteAccess), so content
     * checks only ask whether a record belongs to it.
     */
    public function tenantSiteId(): ?int;
}
