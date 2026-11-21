<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Forms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Models\CmsPage;

/**
 * Helper for Filament pickers that need to list pages belonging to the
 * same site as the record currently being edited.
 *
 * After the multisite refactor removed the ambient session-based scope,
 * raw `CmsPage::where(...)->pluck(...)` calls inside block forms and the
 * page form would list pages across all sites. These helpers thread the
 * owning site's id through explicitly.
 *
 * Fallback rule: if no owner site can be resolved from the Livewire
 * context (e.g. console, unexpected component state), return an empty
 * option set rather than leaking cross-site data. Callers can surface
 * that as an empty dropdown with an explanatory helper text.
 */
class OwnerSitePicker
{
    /**
     * Resolve the site_id owning the record currently being edited.
     *
     * Handles both edit and create flows:
     *  - Edit: `$livewire->record` is set; read its site_id.
     *  - Create: `$livewire->ownerSiteId` is set by the create page's
     *    mount() (see CreateCmsPage), captured from the ?site=<id> URL.
     *
     *  - Otherwise: the current site (SiteContext), which a new record
     *    also gets on create.
     *
     * Returns null when none of these is available (e.g. the multisite
     * plugin's untenanted admin without ?site=).
     */
    public static function resolveOwnerSiteId(mixed $livewire): ?int
    {
        // In a site panel the tenant is the site; nothing on the form overrides it.
        $tenantSiteId = app(SiteContext::class)->tenantSiteId();

        if ($tenantSiteId !== null) {
            return $tenantSiteId;
        }

        if ($livewire === null) {
            return null;
        }

        $ownerSiteId = data_get($livewire, 'ownerSiteId');
        if (is_numeric($ownerSiteId)) {
            return (int) $ownerSiteId;
        }

        $record = data_get($livewire, 'record');
        if ($record && isset($record->site_id) && is_numeric($record->site_id)) {
            return (int) $record->site_id;
        }

        // A new record gets its site from the context on create, so its
        // pickers list that site's pages (one site, or the site panel's tenant).
        return app(SiteContext::class)->currentSiteId();
    }

    /**
     * Build `[id => title]` options for a page picker scoped to the
     * owning site. Filters to `status = 'published'` and resolves
     * translatable titles through the model accessor so JSON columns
     * don't render as `[object Object]`.
     *
     * If multisite isn't active (no `site_id` column on the pages
     * table), returns all published pages — single-site behaviour is
     * unchanged.
     */
    public static function publishedPages(mixed $livewire): array
    {
        if (! Schema::hasColumn('tallcms_pages', 'site_id')) {
            return static::publishedPagesOfSite(null);
        }

        $ownerSiteId = static::resolveOwnerSiteId($livewire);

        if ($ownerSiteId === null) {
            // Safe fallback — no context resolved, no options shown.
            // Preferable to a default-site fallback that would leak
            // cross-site pages into the picker.
            return [];
        }

        return static::publishedPagesOfSite($ownerSiteId);
    }

    /**
     * `[id => title]` of the published pages of one site, for pickers that
     * know their site (e.g. a menu item: the menu's site). Without a
     * `site_id` column (single-site), all published pages; with one, a null
     * site gives no options rather than another site's pages.
     *
     * @return array<int, string>
     */
    public static function publishedPagesOfSite(?int $siteId): array
    {
        $query = static::untrashedPages()->where('status', 'published');

        if (Schema::hasColumn('tallcms_pages', 'site_id')) {
            $query->where('site_id', $siteId ?? 0);
        }

        return $query->get()
            ->mapWithKeys(fn (CmsPage $page) => [
                $page->id => (string) ($page->title ?: __('Untitled')),
            ])
            ->all();
    }

    /**
     * Build `[id => title]` options for the "Parent Page" picker on the
     * page form. Scoped to the same site, limited to top-level pages, and
     * excludes the current record (a page can't be its own parent).
     */
    public static function parentPageOptions(mixed $livewire): array
    {
        $query = static::untrashedPages()->whereNull('parent_id');

        if (Schema::hasColumn('tallcms_pages', 'site_id')) {
            $ownerSiteId = static::resolveOwnerSiteId($livewire);

            if ($ownerSiteId === null) {
                return [];
            }

            $query->where('site_id', $ownerSiteId);
        }

        $currentRecord = data_get($livewire, 'record');
        if ($currentRecord && isset($currentRecord->id)) {
            $query->where('id', '!=', $currentRecord->id);
        }

        return $query->get()
            ->mapWithKeys(fn (CmsPage $page) => [
                $page->id => (string) ($page->title ?: __('Untitled')),
            ])
            ->all();
    }

    /**
     * Pages without the ambient site scope (the pickers filter by the owner
     * site themselves), but still without soft-deleted pages:
     * withoutGlobalScopes() also drops the SoftDeletingScope.
     */
    protected static function untrashedPages(): Builder
    {
        return CmsPage::withoutGlobalScopes()->whereNull((new CmsPage)->getQualifiedDeletedAtColumn());
    }
}
