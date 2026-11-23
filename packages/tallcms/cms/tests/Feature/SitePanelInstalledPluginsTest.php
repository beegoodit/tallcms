<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use TallCms\Cms\Filament\Pages\GlobalDefaults;
use TallCms\Cms\Filament\Pages\MenuItemsManager;
use TallCms\Cms\Filament\Resources\CmsPages\CmsPageResource;
use TallCms\Cms\Filament\Resources\SiteResource\SiteResource;
use TallCms\Cms\Filament\Resources\TallcmsMedia\TallcmsMediaResource;
use TallCms\Cms\Filament\Resources\Users\UserResource;
use TallCms\Cms\Filament\Widgets\ContentHealthWidget;
use TallCms\Cms\Filament\Widgets\DashboardSitePicker;
use TallCms\Cms\Services\PluginManager;
use TallCms\Cms\SitePanelPlugin;
use TallCms\Cms\TallCmsPlugin;
use TallCms\Cms\Tests\TestCase;

/**
 * Which installed plugins the site panel offers: those whose plugin.json says
 * "site_panel": true. The admin panel's list (every installed plugin) is not
 * affected.
 */
class SitePanelInstalledPluginsTest extends TestCase
{
    private string $pluginsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginsPath = sys_get_temp_dir().'/tallcms-plugins-'.bin2hex(random_bytes(6));

        $this->installPlugin('per-site', ['filament_plugin' => PerSiteFilamentPlugin::class, 'site_panel' => true]);
        $this->installPlugin('installation-wide', ['filament_plugin' => InstallationWideFilamentPlugin::class]);
        $this->installPlugin('not-declared-as-boolean', ['filament_plugin' => InstallationWideFilamentPlugin::class, 'site_panel' => 'site']);
        $this->installPlugin('without-panel-class', ['site_panel' => true]);

        config([
            'tallcms.plugins.path' => $this->pluginsPath,
            'tallcms.plugins.cache_enabled' => false,
            'tallcms.plugin_mode.plugins_enabled' => true,
        ]);
        $this->app->instance(PluginManager::class, new PluginManager);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginsPath);

        parent::tearDown();
    }

    public function test_only_plugins_that_declare_it_are_offered_to_the_site_panel(): void
    {
        $offered = app(PluginManager::class)->getSitePanelFilamentPlugins();

        $this->assertCount(1, $offered);
        $this->assertInstanceOf(PerSiteFilamentPlugin::class, $offered[0]);
    }

    public function test_the_admin_panel_still_gets_every_installed_plugin(): void
    {
        $classes = array_map(fn (FilamentPlugin $plugin): string => $plugin::class, app(PluginManager::class)->getFilamentPlugins());

        $this->assertContains(PerSiteFilamentPlugin::class, $classes);
        $this->assertContains(InstallationWideFilamentPlugin::class, $classes);
    }

    public function test_a_panel_without_site_content_gets_the_plugins_that_are_not_per_site(): void
    {
        $classes = array_map(fn (FilamentPlugin $plugin): string => $plugin::class, app(PluginManager::class)->getInstallationFilamentPlugins());

        $this->assertContains(InstallationWideFilamentPlugin::class, $classes);
        $this->assertNotContains(PerSiteFilamentPlugin::class, $classes);
    }

    public function test_the_cms_plugin_without_site_content_registers_no_content_and_no_per_site_plugin(): void
    {
        $panel = Panel::make()->id('core');
        $plugin = TallCmsPlugin::make()->withoutSiteContent()->withoutShieldPlugin();

        $plugin->register($panel);

        $this->assertNotContains(CmsPageResource::class, $panel->getResources());
        $this->assertNotContains(TallcmsMediaResource::class, $panel->getResources());
        $this->assertNotContains(SiteResource::class, $panel->getResources());
        $this->assertNotContains(MenuItemsManager::class, $panel->getPages());
        $this->assertNotContains(DashboardSitePicker::class, $panel->getWidgets());
        $this->assertNotContains(ContentHealthWidget::class, $panel->getWidgets());
        $this->assertContains(GlobalDefaults::class, $panel->getPages());
        $this->assertContains(UserResource::class, $panel->getResources());
        $this->assertTrue($panel->hasPlugin(InstallationWideFilamentPlugin::ID));
        $this->assertFalse($panel->hasPlugin(PerSiteFilamentPlugin::ID));
    }

    public function test_the_cms_plugin_as_it_was_still_registers_content_and_every_plugin(): void
    {
        $panel = Panel::make()->id('admin');

        TallCmsPlugin::make()->withoutShieldPlugin()->register($panel);

        $this->assertContains(CmsPageResource::class, $panel->getResources());
        $this->assertContains(DashboardSitePicker::class, $panel->getWidgets());
        $this->assertTrue($panel->hasPlugin(InstallationWideFilamentPlugin::ID));
        $this->assertTrue($panel->hasPlugin(PerSiteFilamentPlugin::ID));
    }

    public function test_a_per_site_plugin_whose_class_is_missing_is_logged_and_left_out(): void
    {
        $this->installPlugin('broken', ['filament_plugin' => 'Acme\\Missing\\FilamentPlugin', 'site_panel' => true]);
        $this->app->instance(PluginManager::class, new PluginManager);
        Log::spy();

        $offered = app(PluginManager::class)->getSitePanelFilamentPlugins();

        $this->assertCount(1, $offered);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context === ['plugin' => 'acme/broken']);
    }

    public function test_the_site_panel_adds_the_per_site_plugins(): void
    {
        $panel = Panel::make()->id('site');

        SitePanelPlugin::make()->register($panel);

        $this->assertTrue($panel->hasPlugin(PerSiteFilamentPlugin::ID));
        $this->assertFalse($panel->hasPlugin(InstallationWideFilamentPlugin::ID));
    }

    public function test_a_host_that_lists_plugins_itself_switches_the_installed_ones_off(): void
    {
        $panel = Panel::make()->id('site');

        SitePanelPlugin::make()->withoutInstalledPlugins()->register($panel);

        $this->assertFalse($panel->hasPlugin(PerSiteFilamentPlugin::ID));
    }

    public function test_no_plugin_is_added_when_the_plugin_system_is_off(): void
    {
        config(['tallcms.plugin_mode.plugins_enabled' => false]);
        $panel = Panel::make()->id('site');

        SitePanelPlugin::make()->register($panel);

        $this->assertFalse($panel->hasPlugin(PerSiteFilamentPlugin::ID));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function installPlugin(string $slug, array $manifest): void
    {
        $path = $this->pluginsPath.'/acme/'.$slug;
        File::makeDirectory($path, recursive: true);
        File::put($path.'/plugin.json', json_encode(['name' => $slug, 'slug' => $slug, 'vendor' => 'acme'] + $manifest));
    }
}

class PerSiteFilamentPlugin implements FilamentPlugin
{
    public const ID = 'fixture-per-site';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}

class InstallationWideFilamentPlugin implements FilamentPlugin
{
    public const ID = 'fixture-installation-wide';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
