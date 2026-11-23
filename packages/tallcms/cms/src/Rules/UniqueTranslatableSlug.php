<?php

declare(strict_types=1);

namespace TallCms\Cms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use TallCms\Cms\Services\LocaleRegistry;
use TallCms\Cms\Support\TranslatableJson;

/**
 * Validation rule for per-locale slug uniqueness.
 *
 * Since Spatie stores all locale values in a single JSON column,
 * traditional unique constraints can't enforce per-locale uniqueness.
 * This rule queries the JSON column for the specific locale.
 */
class UniqueTranslatableSlug implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * $siteId (optional): when the caller already knows the owning site — e.g.
     * the form passes `$record?->site_id` for an edit or `$livewire->ownerSiteId`
     * for a create — pass it here. This is authoritative and bypasses the
     * session/resolver fallback, which doesn't work for site_owners who
     * haven't used the site switcher. Without an explicit siteId, the rule
     * falls back to session/resolver resolution.
     */
    public function __construct(
        protected string $table,
        protected string $column,
        protected string $locale,
        protected ?int $ignoreId = null,
        protected ?int $siteId = null,
        protected ?int $parentId = null,
        protected bool $parentIdSet = false,
    ) {
        // Normalize locale to lowercase
        $this->locale = strtolower($this->locale);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // The language can come from the browser (the editor's active locale).
        if (! $this->isAvailableLocale()) {
            $fail('This language is not available.');

            return;
        }

        $query = TranslatableJson::whereLocale(DB::table($this->table), $this->column, $this->locale, $value);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        // Exclude soft-deleted records
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        // Scope uniqueness based on ownership model:
        // - User-owned tables (posts, categories): scope by user_id
        // - Site-owned tables (pages): scope by site_id
        $this->applyScopeFilter($query);

        // For hierarchical tables (pages): scope slug uniqueness within the same parent.
        // Siblings must have unique slugs, but the same slug is allowed under different parents.
        if ($this->parentIdSet) {
            if ($this->parentId !== null) {
                $query->where('parent_id', $this->parentId);
            } else {
                $query->whereNull('parent_id');
            }
        }

        if ($query->exists()) {
            $fail("This slug is already used by another item in {$this->locale}.");
        }
    }

    /**
     * A configured language, or the application's own (installs without
     * i18n store their content under it).
     */
    protected function isAvailableLocale(): bool
    {
        if (! TranslatableJson::isLocaleCode($this->locale)) {
            return false;
        }

        if (app(LocaleRegistry::class)->isValidLocale($this->locale)) {
            return true;
        }

        $own = array_map(
            fn (?string $locale): string => LocaleRegistry::normalizeLocaleCode((string) $locale),
            [app()->getLocale(), config('app.locale'), config('app.fallback_locale')],
        );

        return in_array(LocaleRegistry::normalizeLocaleCode($this->locale), $own, true);
    }

    /**
     * Apply the appropriate ownership filter based on the table type.
     *
     * Pages, posts and categories are a site's: a slug is unique within
     * the site.
     */
    protected function applyScopeFilter($query): void
    {
        // Site panel: the tenant's records, whoever created them.
        $tenantSiteId = app(\TallCms\Cms\Contracts\SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null && \Illuminate\Support\Facades\Schema::hasColumn($this->table, 'site_id')) {
            $query->where('site_id', $tenantSiteId);

            return;
        }

        // Everything else is unique per site too (posts and categories are a
        // site's like pages): scope by site_id. Prefer an explicit siteId passed into
        // the rule — authoritative when the form knows which site the record
        // belongs to (existing record's site_id, or create form's ownerSiteId).
        $siteId = $this->siteId;

        if (! $siteId) {
            $sessionValue = session('multisite_admin_site_id');
            if ($sessionValue && $sessionValue !== '__all_sites__' && is_numeric($sessionValue)) {
                $siteId = (int) $sessionValue;
            }
        }

        if (! $siteId && app()->bound('tallcms.multisite.resolver')) {
            try {
                $resolver = app('tallcms.multisite.resolver');
                if ($resolver->isResolved() && $resolver->id()) {
                    $siteId = $resolver->id();
                }
            } catch (\Throwable) {
            }
        }

        if ($siteId && \Illuminate\Support\Facades\Schema::hasColumn($this->table, 'site_id')) {
            $query->where('site_id', $siteId);
        }
    }
}
