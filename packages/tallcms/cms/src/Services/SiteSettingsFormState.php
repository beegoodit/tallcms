<?php

declare(strict_types=1);

namespace TallCms\Cms\Services;

use TallCms\Cms\Filament\Resources\SiteResource\SiteForm;
use TallCms\Cms\Models\SiteSetting;

/**
 * Form state of a site's settings: what the site edit forms show (the site's
 * value, else the global one) and how a submitted form becomes overrides.
 *
 * Shared by core's EditSite (single site), multisite's EditSite (/admin) and
 * the site panel's SiteSettings page.
 */
class SiteSettingsFormState
{
    /**
     * Site-scoped setting keys (stored as overrides) and their types.
     *
     * @var array<string, 'text'|'file'|'boolean'>
     */
    public const KEYS = [
        // General
        'site_tagline' => 'text',
        'site_description' => 'text',
        'site_type' => 'text',
        // Contact
        'contact_email' => 'text',
        'contact_phone' => 'text',
        'company_name' => 'text',
        'company_address' => 'text',
        // Social
        'social_facebook' => 'text',
        'social_twitter' => 'text',
        'social_linkedin' => 'text',
        'social_instagram' => 'text',
        'social_youtube' => 'text',
        'social_tiktok' => 'text',
        'newsletter_signup_url' => 'text',
        // Branding
        'logo' => 'file',
        'favicon' => 'file',
        'show_powered_by' => 'boolean',
        'show_theme_switcher' => 'boolean',
        'show_search' => 'boolean',
        'show_language_dropdown' => 'boolean',
        // Publishing
        'review_workflow_enabled' => 'boolean',
        // Maintenance
        'maintenance_mode' => 'boolean',
        'maintenance_message' => 'text',
        // Languages
        'redirect_root_to_locale' => 'boolean',
        // Embed code
        'code_head' => 'text',
        'code_body_start' => 'text',
        'code_body_end' => 'text',
    ];

    public function __construct(protected SiteSettingsService $settings) {}

    /**
     * Form values for every key: the site's override, else the global value,
     * else the key's default.
     *
     * @return array<string, mixed>
     */
    public function load(int $siteId): array
    {
        $values = [];

        foreach (array_keys(self::KEYS) as $key) {
            $values[$key] = $this->settings->getForSite($siteId, $key, $this->defaultFor($key));
        }

        $values['redirect_root_to_locale'] = SiteForm::normalizeRedirectRootToLocaleFormValue($values['redirect_root_to_locale']);

        return $values;
    }

    /**
     * Store the submitted keys as the site's overrides; keys missing from
     * $data (fields the form doesn't have) stay untouched.
     *
     * - No override + matches global → skip (preserve inheritance)
     * - No override + differs from global → create override
     * - Has override + matches global → delete override (restore inheritance)
     * - Has override + differs from global → update override
     *
     * @param  array<string, mixed>  $data
     */
    public function save(int $siteId, array $data): void
    {
        foreach (self::KEYS as $key => $type) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            // File fields: null could mean "unchanged" or "deleted".
            // Re-read the stored value to distinguish.
            if ($type === 'file' && $value === null) {
                if (! $this->settings->getForSite($siteId, $key)) {
                    continue;
                }

                // Had a value, now null — the user deleted it.
                $value = '';
            }

            if ($this->valuesMatch($value, $this->settings->getGlobal($key), $type)) {
                if ($this->settings->hasOverride($siteId, $key)) {
                    $this->settings->resetForSite($siteId, $key);
                }

                continue;
            }

            $this->settings->setForSite($siteId, $key, $value, $type);
        }

        // The redirect toggle is not dehydrated while it doesn't apply, so clear any stale override explicitly.
        if (! SiteForm::isRedirectRootToLocaleApplicable()
            && $this->settings->hasOverride($siteId, 'redirect_root_to_locale')) {
            $this->settings->resetForSite($siteId, 'redirect_root_to_locale');
        }

        SiteSetting::clearCache();
    }

    protected function defaultFor(string $key): mixed
    {
        return match ($key) {
            'show_powered_by',
            'show_theme_switcher',
            'show_search',
            'show_language_dropdown' => true,
            'site_type' => 'multi-page',
            'maintenance_message' => "We're currently performing scheduled maintenance. Please check back soon!",
            default => null,
        };
    }

    protected function valuesMatch(mixed $submitted, mixed $global, string $type): bool
    {
        if ($type === 'boolean') {
            return (bool) $submitted === (bool) $global;
        }

        return (string) ($submitted ?? '') === (string) ($global ?? '');
    }
}
