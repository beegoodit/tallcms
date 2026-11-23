<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Services\SitePanelUrls;

/**
 * Start page of the site panel: which site this is and where it lives.
 *
 * Plugins add links below through the render hook RENDER_HOOK, which gets
 * the site as $data['site'] (multisite: "back to team").
 */
class SiteDashboard extends Dashboard
{
    public const RENDER_HOOK = 'tallcms::site-dashboard.end';

    public function getTitle(): string|Htmlable
    {
        return $this->site()?->name ?? parent::getTitle();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('tallcms::filament.pages.site-dashboard')
                ->viewData(fn (): array => [
                    'site' => $this->site(),
                    'frontendUrl' => $this->site() ? app(SitePanelUrls::class)->frontend($this->site()) : null,
                    'renderHook' => self::RENDER_HOOK,
                ]),
            $this->getWidgetsContentComponent(),
        ]);
    }

    protected function site(): ?Site
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Site ? $tenant : null;
    }
}
