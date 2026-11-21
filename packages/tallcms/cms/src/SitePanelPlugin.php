<?php

declare(strict_types=1);

namespace TallCms\Cms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use TallCms\Cms\Filament\Concerns\RegistersTranslatablePlugin;
use TallCms\Cms\Filament\Concerns\TogglesContentResources;
use TallCms\Cms\Filament\Pages\MenuItemsManager;
use TallCms\Cms\Filament\Pages\SiteDashboard;
use TallCms\Cms\Filament\Resources\CmsPages\CmsPageResource;
use TallCms\Cms\Filament\Resources\TallcmsContactSubmissions\TallcmsContactSubmissionResource;
use TallCms\Cms\Filament\Resources\TallcmsMenus\TallcmsMenuResource;
use TallCms\Cms\Models\Site;

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
 */
class SitePanelPlugin implements Plugin
{
    use RegistersTranslatablePlugin;
    use TogglesContentResources;

    public const ID = 'tallcms-site';

    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->tenant(config('tallcms.sites.model', Site::class), slugAttribute: 'uuid')
            ->pages($this->getPages())
            ->resources($this->getResources());

        $this->registerTranslatablePlugin($panel);
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

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    public function getResources(): array
    {
        $resources = [];

        if ($this->hasPages) {
            $resources[] = CmsPageResource::class;
        }

        if ($this->hasContactSubmissions) {
            $resources[] = TallcmsContactSubmissionResource::class;
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
