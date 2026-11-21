<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use TallCms\Cms\Models\Site;

/**
 * The tenant site's theme in the site panel: choose one of the installed
 * themes and its daisyUI preset for this site only (SiteThemeActivator).
 *
 * ThemeManager with the tenant as its site: no site switcher, no installation
 * wide theme or rollback, and nothing that concerns the installation
 * (marketplace, upload, deletion, licences), which stays in /admin.
 */
class SiteTheme extends ThemeManager
{
    /**
     * Only inside a site panel, for users who may manage themes.
     */
    public static function canAccess(): bool
    {
        if (! Filament::getTenant() instanceof Site) {
            return false;
        }

        return auth()->user()?->can('View:ThemeManager') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('tallcms::pages.site_theme.navigation');
    }

    public function getTitle(): string
    {
        return __('tallcms::pages.site_theme.title');
    }

    /**
     * Always the tenant; the switcher session of /admin plays no part.
     */
    protected function getMultisiteContext(): ?object
    {
        $site = Filament::getTenant();
        abort_unless($site instanceof Site, 404);

        return DB::table('tallcms_sites')->where('id', $site->getKey())->first();
    }

    public function manageableSites(): array
    {
        return [];
    }

    public function switchSite(?string $siteId): void {}

    public function managesInstallation(): bool
    {
        return false;
    }

    public function visitSiteUrl(): string
    {
        $port = request()->getPort();
        $port = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return request()->getScheme().'://'.$this->getMultisiteContext()->domain.$port;
    }

    #[Computed]
    public function availableMarketplaceThemes(): Collection
    {
        return collect();
    }

    public function refreshLicenseStatus(string $licenseSlug): void {}

    public function deleteAction(): Action
    {
        return parent::deleteAction()->hidden();
    }

    public function activateLicenseAction(): Action
    {
        return parent::activateLicenseAction()->hidden();
    }

    public function deactivateLicenseAction(): Action
    {
        return parent::deactivateLicenseAction()->hidden();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
