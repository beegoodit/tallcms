<?php

declare(strict_types=1);

namespace TallCms\Cms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Log;
use TallCms\Cms\Filament\Concerns\RegistersTranslatablePlugin;
use TallCms\Cms\Filament\Concerns\TogglesContentResources;
use TallCms\Cms\Filament\Pages\MenuItemsManager;
use TallCms\Cms\Filament\Pages\SiteDashboard;
use TallCms\Cms\Filament\Pages\SiteSettings;
use TallCms\Cms\Filament\Pages\SiteTheme;
use TallCms\Cms\Filament\Resources\CmsCategories\CmsCategoryResource;
use TallCms\Cms\Filament\Resources\CmsComments\CmsCommentResource;
use TallCms\Cms\Filament\Resources\CmsPages\CmsPageResource;
use TallCms\Cms\Filament\Resources\CmsPosts\CmsPostResource;
use TallCms\Cms\Filament\Resources\MediaCollection\MediaCollectionResource;
use TallCms\Cms\Filament\Resources\TallcmsContactSubmissions\TallcmsContactSubmissionResource;
use TallCms\Cms\Filament\Resources\TallcmsMedia\TallcmsMediaResource;
use TallCms\Cms\Filament\Resources\TallcmsMenus\TallcmsMenuResource;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Services\PluginManager;

/**
 * The site panel (e.g. /admin/sites/{uuid}): one site's backend, the site as
 * Filament tenant, and with it the current site for new content (SiteContext).
 *
 * The host app owns the panel (path, theme, middleware, auth) and adds this
 * plugin. The host user implements Filament's HasTenants, e.g. through
 * HasSiteTenants. Sites are created elsewhere, so there is no tenant
 * registration.
 *
 * Content resources are registered here one by one, not through
 * TallCmsPlugin, which also brings platform pages, Shield and every installed
 * plugin. The host switches them off as on TallCmsPlugin (withoutMenus(), ...).
 *
 * Of the installed plugins, only those that declare they work per site
 * (plugin.json "site_panel": true) are added; a host that lists its panel's
 * plugins itself calls withoutInstalledPlugins().
 */
class SitePanelPlugin implements Plugin
{
    use RegistersTranslatablePlugin;
    use TogglesContentResources;

    public const ID = 'tallcms-site';

    protected bool $hasInstalledPlugins = true;

    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Do not add the installed per-site plugins; the host adds what it wants.
     */
    public function withoutInstalledPlugins(): static
    {
        $this->hasInstalledPlugins = false;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->tenant(config('tallcms.sites.model', Site::class), slugAttribute: 'uuid')
            ->pages($this->getPages())
            ->resources($this->getResources());

        $this->registerTranslatablePlugin($panel);
        $this->registerInstalledPlugins($panel);
    }

    /**
     * Add the installed plugins that work per site. As in TallCmsPlugin, a
     * failure here must not take the panel down.
     */
    protected function registerInstalledPlugins(Panel $panel): void
    {
        if (! $this->hasInstalledPlugins || ! config('tallcms.plugin_mode.plugins_enabled', true)) {
            return;
        }

        try {
            foreach (app(PluginManager::class)->getSitePanelFilamentPlugins() as $filamentPlugin) {
                $panel->plugin($filamentPlugin);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to register installed plugin Filament plugins in the site panel', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<class-string>
     */
    public function getPages(): array
    {
        $pages = [SiteDashboard::class];

        if ($this->hasMenus) {
            $pages[] = MenuItemsManager::class;
        }

        if ($this->hasSiteSettings) {
            $pages[] = SiteSettings::class;
        }

        if ($this->hasThemeManager && config('tallcms.plugin_mode.themes_enabled', true)) {
            $pages[] = SiteTheme::class;
        }

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    public function getResources(): array
    {
        $resources = [];

        if ($this->hasCategories) {
            $resources[] = CmsCategoryResource::class;
        }

        if ($this->hasPages) {
            $resources[] = CmsPageResource::class;
        }

        if ($this->hasPosts) {
            $resources[] = CmsPostResource::class;
        }

        if ($this->hasComments && config('tallcms.comments.enabled', true)) {
            $resources[] = CmsCommentResource::class;
        }

        if ($this->hasContactSubmissions) {
            $resources[] = TallcmsContactSubmissionResource::class;
        }

        if ($this->hasMedia) {
            $resources[] = TallcmsMediaResource::class;
            $resources[] = MediaCollectionResource::class;
        }

        if ($this->hasMenus) {
            $resources[] = TallcmsMenuResource::class;
        }

        return $resources;
    }

    public function boot(Panel $panel): void
    {
        // Same groups and order as TallCmsPlugin, unless the host set its own.
        if (empty($panel->getNavigationGroups())) {
            $panel->navigationGroups(TallCmsPlugin::defaultNavigationGroups());
        }
    }
}
