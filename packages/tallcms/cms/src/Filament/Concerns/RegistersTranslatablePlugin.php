<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Concerns;

use Filament\Panel;
use Illuminate\Support\Facades\Log;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;
use TallCms\Cms\Services\LocaleRegistry;

/**
 * Shared by every panel plugin that registers TallCMS resources with
 * Translatable pages (TallCmsPlugin, SitePanelPlugin).
 */
trait RegistersTranslatablePlugin
{
    /**
     * Register Lara Zeus Spatie Translatable Plugin.
     *
     * The plugin provides a locale switcher in the admin panel header,
     * allowing content editors to switch between languages when editing.
     *
     * Note: The plugin is ALWAYS registered because the Translatable trait
     * on Resources requires it. When i18n is disabled, only the default
     * locale is shown (no switcher visible).
     */
    protected function registerTranslatablePlugin(Panel $panel): void
    {
        try {
            // When i18n is enabled, get all locales from registry
            // When disabled, just use the app's default locale
            if (tallcms_i18n_enabled()) {
                $registry = app(LocaleRegistry::class);
                $locales = $registry->getLocaleCodes();
            } else {
                $locales = [config('app.locale', 'en')];
            }

            $panel->plugin(
                SpatieTranslatablePlugin::make()
                    ->defaultLocales($locales)
                    ->persist() // Remember user's selected language in session
                    ->getLocaleLabelUsing(function (string $locale): ?string {
                        // Use native labels from LocaleRegistry (e.g., "简体中文" instead of "Chinese (China)")
                        if (! tallcms_i18n_enabled()) {
                            return null; // Fall back to default behavior
                        }

                        $registry = app(LocaleRegistry::class);
                        $locales = $registry->getLocales();

                        if ($locales->has($locale)) {
                            $config = $locales->get($locale);

                            return $config['native'] ?? $config['label'] ?? null;
                        }

                        return null; // Fall back to default behavior
                    })
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to register Spatie Translatable Plugin', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
