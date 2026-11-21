<?php

declare(strict_types=1);

namespace TallCms\Cms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use TallCms\Cms\Filament\Concerns\RegistersTranslatablePlugin;
use TallCms\Cms\Filament\Pages\SiteDashboard;
use TallCms\Cms\Filament\Resources\CmsPages\CmsPageResource;
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
 * plugin.
 */
class SitePanelPlugin implements Plugin
{
    use RegistersTranslatablePlugin;

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
            ->pages([
                SiteDashboard::class,
            ])
            ->resources([
                CmsPageResource::class,
            ]);

        $this->registerTranslatablePlugin($panel);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
