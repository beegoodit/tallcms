<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Services\SitePanelUrls;

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
     * Only inside a site panel of an installation that applies site themes,
     * for users who may manage themes.
     */
    public static function canAccess(): bool
    {
        if (! Filament::getTenant() instanceof Site) {
            return false;
        }

        if (! static::siteThemesAreApplied()) {
            return false;
        }

        return auth()->user()?->can('View:ThemeManager') ?? false;
    }

    /**
     * A site's own theme is applied to the frontend by the multisite plugin
     * (core's frontend reads config/theme.php only); without it the choice
     * made on this page would show as active and change nothing.
     */
    public static function siteThemesAreApplied(): bool
    {
        return app()->bound('tallcms.multisite.resolver');
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
    protected function resolveMultisiteContext(): ?object
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
        $site = Filament::getTenant();
        abort_unless($site instanceof Site, 404);

        return app(SitePanelUrls::class)->frontend($site);
    }

    #[Computed]
    public function availableMarketplaceThemes(): Collection
    {
        return collect();
    }

    public function refreshLicenseStatus(string $licenseSlug): void {}

    /**
     * Rollback belongs to the installation's theme (config/theme.php).
     */
    public function canRollback(): bool
    {
        return false;
    }

    public function rollbackTheme(): void {}

    /**
     * The theme cache belongs to the installation.
     */
    public function refreshThemes(): void {}

    /**
     * Whether a theme is licensed, for the "licence required" hint; the key,
     * the licensed domain, expiry and download links stay in /admin.
     */
    protected function refreshLicenseStatuses(): void
    {
        parent::refreshLicenseStatuses();

        $this->licenseStatuses = array_map(
            fn (array $status): array => Arr::only($status, ['has_license', 'is_valid', 'status', 'status_label', 'status_color']),
            $this->licenseStatuses,
        );
    }

    /**
     * Without the theme's path on the server.
     */
    public function showThemeDetails(string $slug): void
    {
        parent::showThemeDetails($slug);

        if ($this->themeDetails !== null) {
            $this->themeDetails['path'] = null;
        }
    }

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
