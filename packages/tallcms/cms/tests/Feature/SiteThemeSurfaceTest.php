<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use ReflectionClass;
use ReflectionMethod;
use TallCms\Cms\Filament\Pages\SiteTheme;
use TallCms\Cms\Filament\Pages\ThemeManager;
use TallCms\Cms\Tests\TestCase;

/**
 * SiteTheme is ThemeManager with the installation's features switched off
 * one by one. Every public method of ThemeManager is callable from the
 * browser (Livewire), so a method added there is open in the site panel
 * unless SiteTheme closes it. This test makes that a decision: each public
 * method of ThemeManager must be listed here as open or closed.
 */
class SiteThemeSurfaceTest extends TestCase
{
    /**
     * Work on the tenant only (through the site context), or are reads and
     * view state without installation data.
     */
    private const OPEN_IN_THE_SITE_PANEL = [
        'activateTheme', 'activeTheme', 'canChangeTheme', 'changeDefaultPreset', 'closeThemeDetails',
        'filteredThemes', 'getActiveThemeSlug', 'getNavigationGroup', 'getNavigationIcon', 'getNavigationSort',
        'getRollbackSlug', 'getSubheading', 'goToThemePage', 'mount', 'paginatedThemes', 'previewTheme',
        'themePageCount', 'themes', 'updatedFilterAnimations', 'updatedFilterDarkMode', 'updatedFilterResponsive',
        'updatedFilterThemeController', 'updatedSearch', 'updatedShowLanguageDropdown', 'updatedShowSearch',
        'updatedShowThemeSwitcher',
    ];

    /**
     * Concern the installation, another site, or differ in the site panel:
     * SiteTheme must override each of them.
     */
    private const OVERRIDDEN_BY_SITE_THEME = [
        'activateLicenseAction', 'availableMarketplaceThemes', 'canRollback', 'deactivateLicenseAction', 'deleteAction',
        'getNavigationLabel', 'getTitle', 'manageableSites', 'managesInstallation', 'refreshLicenseStatus',
        'refreshThemes', 'rollbackTheme', 'showThemeDetails', 'switchSite', 'visitSiteUrl',
    ];

    public function test_every_public_method_of_the_theme_manager_is_classified_for_the_site_panel(): void
    {
        $unclassified = array_diff(
            $this->publicMethodsDeclaredIn(ThemeManager::class),
            self::OPEN_IN_THE_SITE_PANEL,
            self::OVERRIDDEN_BY_SITE_THEME,
        );

        $this->assertSame([], array_values($unclassified), 'New public ThemeManager methods: decide whether the site panel may call them, then list them in this test (and override them in SiteTheme if not).');
    }

    public function test_site_theme_overrides_everything_that_is_not_open(): void
    {
        $missing = array_diff(self::OVERRIDDEN_BY_SITE_THEME, $this->publicMethodsDeclaredIn(SiteTheme::class));

        $this->assertSame([], array_values($missing));
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function publicMethodsDeclaredIn(string $class): array
    {
        $reflection = new ReflectionClass($class);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getFileName() === $reflection->getFileName())
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();
    }
}
