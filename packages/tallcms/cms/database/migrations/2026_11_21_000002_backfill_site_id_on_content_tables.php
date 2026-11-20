<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills site_id where the answer is unambiguous. Only touches rows with site_id null.
 *
 * - Pages, menus, contact submissions: the default site (what the multisite
 *   plugin's own backfill and orphan repair already do).
 * - Comments, revisions, preview tokens: the parent's site.
 * - Posts, categories, media, media collections: the default site only when
 *   the installation has exactly one site. With several sites these are
 *   user-owned in the multisite plugin and get assigned by its content
 *   migration, so nothing is guessed here.
 * - Personal access tokens: never; site_id null means an installation-wide token.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const SITE_OWNED = ['tallcms_pages', 'tallcms_contact_submissions'];

    /**
     * @var list<string>
     */
    private const USER_OWNED_IN_MULTISITE = [
        'tallcms_posts',
        'tallcms_categories',
        'tallcms_media',
        'tallcms_media_collections',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tallcms_sites')) {
            return;
        }

        $defaultSiteId = DB::table('tallcms_sites')->where('is_default', true)->value('id');

        if ($defaultSiteId === null) {
            return;
        }

        $isSingleSite = DB::table('tallcms_sites')->count() === 1;

        foreach (self::SITE_OWNED as $table) {
            $this->fillWithSite($table, $defaultSiteId);
        }

        $this->fillMenus($defaultSiteId);

        if ($isSingleSite) {
            foreach (self::USER_OWNED_IN_MULTISITE as $table) {
                $this->fillWithSite($table, $defaultSiteId);
            }
        }

        $this->deriveFromParent('tallcms_comments', 'post_id', 'tallcms_posts');
        $this->deriveFromMorphParent('tallcms_revisions', 'revisionable');
        $this->deriveFromMorphParent('tallcms_preview_tokens', 'tokenable');
    }

    public function down(): void {}

    private function hasSiteId(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'site_id');
    }

    private function fillWithSite(string $table, int $siteId): void
    {
        if (! $this->hasSiteId($table)) {
            return;
        }

        DB::table($table)->whereNull('site_id')->update(['site_id' => $siteId]);
    }

    /**
     * Menu locations are unique per site; an orphan whose location the default
     * site already uses is renamed, as the multisite plugin's orphan repair does.
     */
    private function fillMenus(int $siteId): void
    {
        if (! $this->hasSiteId('tallcms_menus')) {
            return;
        }

        foreach (DB::table('tallcms_menus')->whereNull('site_id')->get(['id', 'location']) as $menu) {
            $isTaken = DB::table('tallcms_menus')
                ->where('site_id', $siteId)
                ->where('location', $menu->location)
                ->exists();

            DB::table('tallcms_menus')->where('id', $menu->id)->update([
                'site_id' => $siteId,
                'location' => $isTaken ? "{$menu->location}-orphaned-{$menu->id}" : $menu->location,
            ]);
        }
    }

    private function deriveFromParent(string $table, string $foreignKey, string $parentTable): void
    {
        if (! $this->hasSiteId($table) || ! $this->hasSiteId($parentTable)) {
            return;
        }

        DB::statement("
            UPDATE {$table}
            SET site_id = (SELECT {$parentTable}.site_id FROM {$parentTable} WHERE {$parentTable}.id = {$table}.{$foreignKey})
            WHERE {$table}.site_id IS NULL
        ");
    }

    /**
     * Pages and posts are the only revisionable / previewable models in core.
     * Types are stored as class names, possibly app wrappers (App\Models\CmsPage).
     */
    private function deriveFromMorphParent(string $table, string $morph): void
    {
        if (! $this->hasSiteId($table)) {
            return;
        }

        foreach (['tallcms_pages' => 'CmsPage', 'tallcms_posts' => 'CmsPost'] as $parentTable => $classBaseName) {
            if (! $this->hasSiteId($parentTable)) {
                continue;
            }

            DB::statement("
                UPDATE {$table}
                SET site_id = (SELECT {$parentTable}.site_id FROM {$parentTable} WHERE {$parentTable}.id = {$table}.{$morph}_id)
                WHERE {$table}.site_id IS NULL
                AND {$table}.{$morph}_type LIKE ?
            ", ['%'.$classBaseName]);
        }
    }
};
