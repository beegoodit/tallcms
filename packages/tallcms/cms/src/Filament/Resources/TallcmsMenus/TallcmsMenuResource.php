<?php

namespace TallCms\Cms\Filament\Resources\TallcmsMenus;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TallCms\Cms\Filament\Resources\Concerns\ScopesQueryToOwnedSites;
use TallCms\Cms\Filament\Resources\TallcmsMenus\Pages\CreateTallcmsMenu;
use TallCms\Cms\Filament\Resources\TallcmsMenus\Pages\EditTallcmsMenu;
use TallCms\Cms\Filament\Resources\TallcmsMenus\Pages\ListTallcmsMenus;
use TallCms\Cms\Filament\Resources\TallcmsMenus\Schemas\TallcmsMenuForm;
use TallCms\Cms\Filament\Resources\TallcmsMenus\Tables\TallcmsMenusTable;
use TallCms\Cms\Models\TallcmsMenu;

class TallcmsMenuResource extends Resource
{
    use ScopesQueryToOwnedSites;

    protected static ?string $model = TallcmsMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    public static function getNavigationLabel(): string
    {
        return tallcms_label('menus', 'navigation');
    }

    public static function getModelLabel(): string
    {
        return tallcms_label('menus', 'singular');
    }

    public static function getPluralModelLabel(): string
    {
        return tallcms_label('menus', 'plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        // In multisite mode, Menus are accessed through the Site resource in
        // the untenanted admin panel. The resource stays registered (URLs
        // work) but nav is hidden there. The site panel lists them directly.
        if (tallcms_multisite_active() && ! Filament::hasTenancy()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function getNavigationGroup(): ?string
    {
        return tallcms_nav_group('content');
    }

    public static function getNavigationSort(): ?int
    {
        return 9;
    }

    public static function form(Schema $schema): Schema
    {
        return TallcmsMenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TallcmsMenusTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Removed MenuItemsRelationManager for cleaner UX
            // Menu items are managed via integrated actions
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTallcmsMenus::route('/'),
            'create' => CreateTallcmsMenu::route('/create'),
            'edit' => EditTallcmsMenu::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return (string) static::getEloquentQuery()->count();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeQueryToOwnedSites(parent::getEloquentQuery());
    }
}
