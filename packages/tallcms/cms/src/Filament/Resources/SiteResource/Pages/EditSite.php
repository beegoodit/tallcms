<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Resources\SiteResource\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Filament\Resources\SiteResource\SiteForm;
use TallCms\Cms\Filament\Resources\SiteResource\SiteResource;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Services\SiteSettingsFormState;

/**
 * Core Site edit page.
 *
 * In standalone mode: always edits the default site.
 * In multisite mode: the multisite plugin overrides this with
 * a proper resource edit page that works with multiple records.
 *
 * Authorization is handled by the SiteResource, not HasPageShield
 * (which is incompatible with resource pages).
 */
class EditSite extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SiteResource::class;

    protected string $view = 'tallcms::filament.pages.site-edit';

    public ?array $data = [];

    protected ?Site $siteRecord = null;

    public function getTitle(): string
    {
        return tallcms_label('site_settings', 'navigation');
    }

    public function mount(): void
    {
        $this->siteRecord = $this->getSiteRecord();
        $siteId = $this->siteRecord->id;

        $formData = [
            // Site model fields
            'name' => $this->siteRecord->name,
            'domain' => $this->siteRecord->domain,
            'theme' => $this->siteRecord->theme,
            'locale' => $this->siteRecord->locale,
        ];

        $formData += app(SiteSettingsFormState::class)->load($siteId);

        $this->form->fill($formData);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $site = $this->getSiteRecord();

        // Save Site model fields
        $site->update([
            'name' => $data['name'],
            'domain' => $data['domain'] ?? $site->domain,
            'theme' => $data['theme'] ?? $site->theme,
            'locale' => $data['locale'] ?? $site->locale,
        ]);

        app(SiteSettingsFormState::class)->save($site->id, $data);

        Notification::make()
            ->title(__('tallcms::ui.t_site_settings_saved'))
            ->body(__('tallcms::ui.n_settings_saved_for_site', ['name' => $site->name]))
            ->success()
            ->send();
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        return SiteForm::schema($this->getSiteRecord());
    }

    /**
     * Get the site record being edited.
     * In standalone: always the default site.
     */
    protected function getSiteRecord(): Site
    {
        if ($this->siteRecord) {
            return $this->siteRecord;
        }

        // Guard: table may not exist yet on fresh upgrades
        if (! Schema::hasTable('tallcms_sites')) {
            abort(503, 'Please run "php artisan migrate" to complete the TallCMS 4.0 upgrade.');
        }

        return $this->siteRecord = Site::getDefault() ?? Site::first() ?? $this->createDefaultSite();
    }

    protected function createDefaultSite(): Site
    {
        return Site::create([
            'name' => config('app.name', 'My Site'),
            'domain' => Site::normalizeDomain(parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?? 'localhost'),
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
