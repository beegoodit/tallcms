<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Widgets\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use TallCms\Cms\Contracts\SiteContext;

/**
 * Multisite scoping helpers for dashboard widgets.
 *
 * In a site panel the site is the panel's tenant. Elsewhere it is read from
 * session('multisite_admin_site_id'), with role-based fallback when the
 * session isn't set yet.
 *
 * Session value semantics:
 *   - '__all_sites__'    → null (caller's signal to skip the site filter)
 *   - numeric            → that specific site_id
 *   - missing            → role-based fallback:
 *                            super_admin → default site
 *                            others      → first owned site
 *
 * Guards QueryException so installs without the multisite plugin (no
 * tallcms_sites table) degrade gracefully to null instead of erroring out.
 */
trait HasMultisiteWidgetContext
{
    protected function getMultisiteSiteId(): ?int
    {
        // In a site panel the widget is about that site, whatever the session says.
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null) {
            return $tenantSiteId;
        }

        $sessionValue = session('multisite_admin_site_id');

        if ($sessionValue === '__all_sites__') {
            return null;
        }

        if ($sessionValue && is_numeric($sessionValue)) {
            return (int) $sessionValue;
        }

        try {
            if (auth()->check() && ! auth()->user()->hasRole('super_admin')) {
                $firstOwned = DB::table('tallcms_sites')
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->orderBy('created_at')
                    ->value('id');

                return $firstOwned ? (int) $firstOwned : null;
            }

            $default = DB::table('tallcms_sites')->where('is_default', true)->value('id');

            return $default ? (int) $default : null;
        } catch (QueryException) {
            return null;
        }
    }

    protected function getMultisiteName(?int $siteId): ?string
    {
        $sessionValue = session('multisite_admin_site_id');

        if ($sessionValue === '__all_sites__' && app(SiteContext::class)->tenantSiteId() === null) {
            return __('tallcms::widgets.all_sites');
        }

        if (! $siteId) {
            return null;
        }

        try {
            $site = DB::table('tallcms_sites')->where('id', $siteId)->first();

            return $site?->name;
        } catch (QueryException) {
            return null;
        }
    }

    protected function isAllSitesSelected(): bool
    {
        return app(SiteContext::class)->tenantSiteId() === null
            && session('multisite_admin_site_id') === '__all_sites__';
    }
}
