<?php

namespace TallCms\Cms\Filament\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Filament\Forms\OwnerSitePicker;
use TallCms\Cms\Models\TallcmsMenu;
use TallCms\Cms\Models\TallcmsMenuItem;
use TallCms\Cms\Services\LocaleRegistry;
use Wsmallnews\FilamentNestedset\Pages\NestedsetPage;

class MenuItemsManager extends NestedsetPage
{
    protected static ?string $model = TallcmsMenuItem::class;

    protected static string $recordTitleAttribute = 'label';

    protected static ?string $tabFieldName = 'menu_id';

    protected static ?int $level = 5; // Allow up to 5 levels of nesting

    protected static bool $shouldRegisterNavigation = false; // Hide from navigation

    /**
     * The package's tenant scope filters by team_id, which menu items don't
     * have: they belong to a site through their menu (see menusQuery()).
     */
    protected static bool $isScopedToTenant = false;

    /**
     * Active locale for translations (consistent with LaraZeus LocaleSwitcher)
     */
    public ?string $activeLocale = null;

    /**
     * In a site panel, only users who may list menus open the page.
     */
    public static function canAccess(): bool
    {
        if (app(SiteContext::class)->tenantSiteId() !== null) {
            return auth()->user()?->can('viewAny', TallcmsMenu::class) ?? false;
        }

        return parent::canAccess();
    }

    public function mount(): void
    {
        parent::mount();

        // Initialize locale from session (consistent with LaraZeus) or use default
        $this->activeLocale = $this->getStoredActiveLocale() ?? $this->getDefaultTranslatableLocale();
    }

    /**
     * Get stored locale from session (consistent with LaraZeus behavior)
     */
    protected function getStoredActiveLocale(): ?string
    {
        if (! tallcms_i18n_enabled()) {
            return null;
        }

        // Use LaraZeus session key for consistency
        $locale = session()->get('spatie_translatable_active_locale');

        if ($locale && in_array($locale, $this->getTranslatableLocales(), true)) {
            return $locale;
        }

        return null;
    }

    /**
     * Called when activeLocale property is updated (by LocaleSwitcher)
     * This hook is consistent with LaraZeus Translatable trait behavior
     */
    public function updatedActiveLocale(): void
    {
        // Validate the locale
        if (! in_array($this->activeLocale, $this->getTranslatableLocales(), true)) {
            $this->activeLocale = $this->getDefaultTranslatableLocale();
        }

        // Persist to session (consistent with LaraZeus)
        if (filament('spatie-translatable')->getPersistLocale()) {
            session()->put('spatie_translatable_active_locale', $this->activeLocale);
        }

        // Refresh the tree view
        $this->dispatch('filament-nestedset-updated');
    }

    /**
     * Get available locales for translation
     * Required by LocaleSwitcher::make()
     */
    public function getTranslatableLocales(): array
    {
        if (! tallcms_i18n_enabled()) {
            return [config('app.locale', 'en')];
        }

        return app(LocaleRegistry::class)->getLocaleCodes();
    }

    /**
     * Get the default translatable locale
     */
    public function getDefaultTranslatableLocale(): string
    {
        if (! tallcms_i18n_enabled()) {
            return config('app.locale', 'en');
        }

        return app(LocaleRegistry::class)->getDefaultLocale();
    }

    public function getRecordLabel(Model $record): HtmlString|string
    {
        $locale = $this->activeLocale ?? $this->getDefaultTranslatableLocale();

        return $record->getTranslation(static::getRecordTitleAttribute(), $locale, true) ?: ' ';
    }

    /**
     * Get locale label for display (used in form field labels)
     */
    public function getLocaleLabel(string $locale): string
    {
        // Try to get label from LaraZeus plugin first (for consistency)
        try {
            $label = filament('spatie-translatable')->getLocaleLabel($locale);
            if ($label) {
                return $label;
            }
        } catch (\Throwable) {
            // Fall through to local registry
        }

        if (! tallcms_i18n_enabled()) {
            return $locale;
        }

        $registry = app(LocaleRegistry::class);
        $locales = $registry->getLocales();

        if ($locales->has($locale)) {
            $config = $locales->get($locale);

            return $config['native'] ?? $config['label'] ?? $locale;
        }

        return $locale;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Add locale switcher if i18n is enabled (using LaraZeus LocaleSwitcher for consistency)
        if (tallcms_i18n_enabled()) {
            $actions[] = LocaleSwitcher::make();
        }

        // Add default actions from parent
        $actions[] = $this->createAction();
        $actions[] = $this->fixTreeAction();

        return $actions;
    }

    /**
     * Menus this page works on: in a site panel the tenant's, otherwise all.
     */
    protected function menusQuery(): Builder
    {
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId === null) {
            return TallcmsMenu::query();
        }

        return TallcmsMenu::withoutGlobalScopes()->where('site_id', $tenantSiteId);
    }

    protected function menuIsAccessible(mixed $menuId): bool
    {
        return $menuId !== null && $menuId !== '' && $this->menusQuery()->whereKey($menuId)->exists();
    }

    /**
     * Called by the package for every tree query: the active tab comes from
     * the URL and the client, so a menu outside menusQuery() shows no items
     * and none of them can be edited, moved or deleted.
     */
    protected function getEloquentQuery(Builder $query): Builder
    {
        if ($this->activeTab === null || $this->menuIsAccessible($this->activeTab)) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function getTabs(): array
    {
        $menus = $this->menusQuery()->get();
        $tabs = [];

        foreach ($menus as $menu) {
            $tabs[$menu->id] = Tab::make()
                ->label($menu->name.' ('.$menu->allItems()->count().')');
        }

        return $tabs;
    }

    /**
     * Override createAction to handle translations
     */
    public function createAction(): CreateAction
    {
        return $this->configureItemCreation(parent::createAction());
    }

    /**
     * "Create child node" in a row: the same rules as createAction. The
     * package's own version takes menu_id from the active tab, which the
     * client controls, without checking it.
     */
    public function createChildAction(): CreateAction
    {
        return $this->configureItemCreation(parent::createChildAction());
    }

    /**
     * An item whose id is not in the tree query (unknown, or of a menu this
     * page does not work on) is not found, instead of a null record.
     */
    public function deleteAction(): DeleteAction
    {
        return parent::deleteAction()
            ->record(function (array $arguments): ?Model {
                $id = $arguments['id'] ?? 0;

                return $id ? $this->getQuery()->findOrFail($id) : null;
            });
    }

    /**
     * Creation of an item in the active tab's menu: the menu must be one of
     * menusQuery(), and the label is stored for the active locale.
     */
    protected function configureItemCreation(CreateAction $action): CreateAction
    {
        return $action
            ->mutateFormDataUsing(function (array $data): array {
                // Store label as translation for current locale
                if (isset($data['label']) && tallcms_i18n_enabled()) {
                    $data['_translatable_label'] = $data['label'];
                    unset($data['label']);
                }

                return $data;
            })
            ->using(function (array $data, array $arguments): Model {
                $translatableLabel = $data['_translatable_label'] ?? null;
                unset($data['_translatable_label']);

                // Get parent from form data or arguments (same as parent class)
                $parentId = $data['parent_id'] ?? ($arguments['parentId'] ?? 0);
                $parent = $parentId ? $this->getQuery()->find($parentId) : null;
                unset($data['parent_id']);

                // Add menu_id from active tab (this is the key fix)
                abort_unless($this->menuIsAccessible($this->activeTab), 404);
                $data['menu_id'] = $this->activeTab;

                // Create the record with parent relationship
                $record = TallcmsMenuItem::create(
                    attributes: $data,
                    parent: $parent,
                );

                // Handle translatable label
                if ($translatableLabel !== null && tallcms_i18n_enabled()) {
                    $activeLocale = $this->activeLocale ?? $this->getDefaultTranslatableLocale();
                    $defaultLocale = $this->getDefaultTranslatableLocale();

                    // Always set the active locale translation
                    $record->setTranslation('label', $activeLocale, $translatableLabel);

                    // Also set default locale if different (ensures menu never renders blank)
                    if ($activeLocale !== $defaultLocale) {
                        $record->setTranslation('label', $defaultLocale, $translatableLabel);
                    }

                    $record->save();
                } elseif ($translatableLabel !== null) {
                    $record->label = $translatableLabel;
                    $record->save();
                }

                return $record;
            });
    }

    /**
     * Override editAction to handle translations
     */
    public function editAction(): EditAction
    {
        return parent::editAction()
            ->fillForm(function (Model $record): array {
                $data = $record->toArray();

                // Label is always stored as translatable JSON (Spatie normalizes the column).
                // Always extract a string for the text input — otherwise toArray() returns
                // the full translations array and the field renders as "[object Object]".
                $locale = tallcms_i18n_enabled()
                    ? ($this->activeLocale ?? $this->getDefaultTranslatableLocale())
                    : $this->getDefaultTranslatableLocale();

                $data['label'] = $record->getTranslation('label', $locale, true) ?: '';

                return $data;
            })
            ->mutateFormDataUsing(function (array $data): array {
                if (isset($data['label']) && tallcms_i18n_enabled()) {
                    $data['_translatable_label'] = $data['label'];
                    unset($data['label']);
                }

                return $data;
            })
            ->using(function (Model $record, array $data): Model {
                $translatableLabel = $data['_translatable_label'] ?? null;
                unset($data['_translatable_label']);

                abort_unless($this->menuIsAccessible($data['menu_id'] ?? $record->getAttribute('menu_id')), 404);

                $record->fill($data);

                if ($translatableLabel !== null && tallcms_i18n_enabled()) {
                    $activeLocale = $this->activeLocale ?? $this->getDefaultTranslatableLocale();
                    $record->setTranslation('label', $activeLocale, $translatableLabel);
                } elseif ($translatableLabel !== null) {
                    $record->label = $translatableLabel;
                }

                $record->save();

                return $record;
            });
    }

    protected function schema(array $arguments): array
    {
        return [
            Hidden::make('menu_id')
                ->default(function () use ($arguments) {
                    return $arguments['tab'] ?? request()->get('activeTab');
                }),

            TextInput::make('label')
                ->label(fn () => tallcms_i18n_enabled()
                    ? 'Menu Label ('.$this->getLocaleLabel($this->activeLocale ?? $this->getDefaultTranslatableLocale()).')'
                    : 'Menu Label')
                ->required()
                ->maxLength(255)
                ->placeholder(__('tallcms::fields.home'))
                ->helperText(fn () => tallcms_i18n_enabled()
                    ? 'Switch language using the button in the header to translate this label.'
                    : null),

            Select::make('type')
                ->label(__('tallcms::fields.link_type'))
                ->options([
                    'page' => 'Page',
                    'external' => 'External URL',
                    'custom' => 'Custom URL',
                    'header' => 'Header',
                    'separator' => 'Separator',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('page_id', null))
                ->afterStateUpdated(fn (callable $set) => $set('url', null)),

            Select::make('page_id')
                ->label(__('tallcms::fields.select_page'))
                ->options(function () use ($arguments): array {
                    // Scope to the menu being edited, not the session site — the user
                    // may have navigated here from a site context that doesn't match.
                    $menuId = $arguments['tab'] ?? request()->get('activeTab') ?? $this->activeTab;
                    $menu = $this->menuIsAccessible($menuId)
                        ? TallcmsMenu::withoutGlobalScopes()->find($menuId)
                        : null;

                    // An orphan or inaccessible menu gets no pages rather than another site's.
                    return OwnerSitePicker::publishedPagesOfSite($menu?->site_id);
                })
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('type') === 'page'),

            TextInput::make('url')
                ->label(__('tallcms::fields.url'))
                ->required()
                ->placeholder('https://example.com or /contact')
                ->visible(fn (Get $get): bool => in_array($get('type'), ['external', 'custom'])),

            Toggle::make('is_active')
                ->label(__('tallcms::fields.active'))
                ->default(true),
        ];
    }
}
