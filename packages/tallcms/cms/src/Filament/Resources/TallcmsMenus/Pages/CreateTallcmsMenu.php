<?php

namespace TallCms\Cms\Filament\Resources\TallcmsMenus\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Filament\Resources\TallcmsMenus\TallcmsMenuResource;

class CreateTallcmsMenu extends CreateRecord
{
    protected static string $resource = TallcmsMenuResource::class;

    /**
     * Owning site id, captured from the ?site=<id> query parameter attached by
     * the Site resource's Menus relation manager. Persisted as a Livewire
     * property so it survives the Livewire update cycle that runs on save.
     * Ignored in a site panel: the site comes from the tenant only.
     */
    public ?int $ownerSiteId = null;

    public function mount(): void
    {
        parent::mount();

        $siteParam = request()->query('site');
        if ($siteParam !== null && is_numeric($siteParam) && app(SiteContext::class)->tenantSiteId() === null) {
            $this->ownerSiteId = (int) $siteParam;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->ownerSiteId !== null
            && Schema::hasColumn('tallcms_menus', 'site_id')
            && empty($data['site_id'])
        ) {
            $data['site_id'] = $this->ownerSiteId;
        }

        return $data;
    }
}
