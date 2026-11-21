<?php

declare(strict_types=1);

namespace TallCms\Cms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use TallCms\Cms\Models\SiteSetting;
use TallCms\Cms\Models\Theme;
use TallCms\Cms\Services\ThemeManager as ThemeManagerService;

/**
 * A site's own theme and daisyUI preset, for the site passed in: the site's
 * `theme` column and its `theme_default_preset` override. The installation's
 * theme (config/theme.php) and the global preset stay untouched.
 *
 * Used by ThemeManager in /admin (the site picked in its switcher) and by
 * SiteTheme in the site panel (the tenant).
 */
class SiteThemeActivator
{
    public const PRESET_KEY = 'theme_default_preset';

    public function __construct(
        protected ThemeManagerService $themes,
        protected SiteSettingsService $settings,
    ) {}

    /**
     * Make the theme the site's theme: the site column, the theme's public
     * assets, no stale compiled views, and the preset back to the theme's
     * default.
     */
    public function activate(int $siteId, Theme $theme): void
    {
        DB::table('tallcms_sites')
            ->where('id', $siteId)
            ->update(['theme' => $theme->slug, 'updated_at' => now()]);

        $this->themes->publishThemeAssets($theme);

        $compiledViewPath = config('view.compiled');
        if ($compiledViewPath && File::isDirectory($compiledViewPath)) {
            foreach (File::glob($compiledViewPath.'/*.php') as $view) {
                File::delete($view);
            }
        }

        $this->choosePreset($siteId, '');
    }

    /**
     * The site's default daisyUI preset; an empty string means the theme's
     * own default.
     */
    public function choosePreset(int $siteId, string $preset): void
    {
        $this->settings->setForSite($siteId, self::PRESET_KEY, $preset, 'text');

        SiteSetting::clearCache();
    }
}
