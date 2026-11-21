<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use TallCms\Cms\Filament\Resources\SiteResource\SiteForm;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Services\SiteSettingsFormState;

/**
 * The tenant site's settings in the site panel: the settings tabs of the
 * site edit form (General to Languages), without what belongs to the
 * platform (domain, plan, owner, status, domain verification).
 *
 * Values are stored as the site's overrides of the global defaults, as in
 * the site edit forms of /admin (SiteSettingsFormState).
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'tallcms::filament.pages.site-edit';

    public ?array $data = [];

    /**
     * Only inside a site panel, for users with the page permission.
     */
    public static function canAccess(): bool
    {
        if (! Filament::getTenant() instanceof Site) {
            return false;
        }

        return auth()->user()?->can('View:SiteSettings') ?? false;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return tallcms_label('site_settings', 'navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return tallcms_nav_group('configuration');
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public function getTitle(): string
    {
        return tallcms_label('site_settings', 'navigation');
    }

    public function mount(): void
    {
        $site = $this->site();

        $this->form->fill(['locale' => $site->locale] + app(SiteSettingsFormState::class)->load((int) $site->getKey()));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $site = $this->site();

        $site->update(['locale' => $data['locale'] ?? null]);
        app(SiteSettingsFormState::class)->save((int) $site->getKey(), $data);

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
        return [
            Tabs::make('Site Settings')
                ->tabs([
                    SiteForm::settingsGeneralTab(),
                    SiteForm::brandingTab(),
                    SiteForm::contactTab(),
                    SiteForm::socialTab(),
                    SiteForm::embedCodeTab(),
                    SiteForm::publishingTab(),
                    SiteForm::maintenanceTab(),
                    SiteForm::languagesTab(includeSiteLocaleField: true),
                ])
                ->persistTabInQueryString()
                ->columnSpanFull(),
        ];
    }

    protected function site(): Site
    {
        $site = Filament::getTenant();
        abort_unless($site instanceof Site, 404);

        return $site;
    }
}
