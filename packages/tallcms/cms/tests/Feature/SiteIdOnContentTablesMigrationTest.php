<?php

namespace TallCms\Cms\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Tests\TestCase;

/**
 * site_id on site-owned content comes from core (2026_11_21_00000*), with or without multisite.
 */
class SiteIdOnContentTablesMigrationTest extends TestCase
{
    private const CONTENT_TABLES = [
        'tallcms_pages',
        'tallcms_menus',
        'tallcms_posts',
        'tallcms_categories',
        'tallcms_media',
        'tallcms_media_collections',
        'tallcms_comments',
        'tallcms_revisions',
        'tallcms_preview_tokens',
        'tallcms_contact_submissions',
        'personal_access_tokens',
    ];

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('tallcms_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        foreach (['tallcms_pages', 'tallcms_posts', 'tallcms_categories', 'tallcms_media', 'tallcms_media_collections', 'tallcms_contact_submissions'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        Schema::create('tallcms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('location')->unique();
            $table->timestamps();
        });

        Schema::create('tallcms_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->timestamps();
        });

        Schema::create('tallcms_revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->timestamps();
        });

        Schema::create('tallcms_preview_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->timestamps();
        });
    }

    public function test_adds_site_id_to_every_content_table(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');

        foreach (self::CONTENT_TABLES as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'site_id'), "{$table} has no site_id");
        }
    }

    public function test_skips_tables_where_multisite_already_added_site_id(): void
    {
        Schema::table('tallcms_pages', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable();
        });

        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');

        $this->assertTrue(Schema::hasColumn('tallcms_menus', 'site_id'));
        $this->assertSame(1, collect(Schema::getColumns('tallcms_pages'))->where('name', 'site_id')->count());
    }

    public function test_skips_tables_the_host_app_does_not_have(): void
    {
        Schema::drop('personal_access_tokens');

        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');

        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasColumn('tallcms_pages', 'site_id'));
    }

    public function test_single_site_install_gets_every_row_assigned_except_tokens(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $siteId = $this->createSite('example.test', isDefault: true);
        $pageId = DB::table('tallcms_pages')->insertGetId(['created_at' => now()]);
        $postId = DB::table('tallcms_posts')->insertGetId(['created_at' => now()]);
        DB::table('tallcms_media')->insert(['created_at' => now()]);
        DB::table('tallcms_menus')->insert(['location' => 'header']);
        DB::table('tallcms_comments')->insert(['post_id' => $postId]);
        DB::table('tallcms_revisions')->insert(['revisionable_type' => 'App\Models\CmsPage', 'revisionable_id' => $pageId]);
        DB::table('personal_access_tokens')->insert(['tokenable_type' => 'User', 'tokenable_id' => 1, 'name' => 'api', 'token' => 'x']);

        $this->migrate('2026_11_21_000002_backfill_site_id_on_content_tables');

        foreach (['tallcms_pages', 'tallcms_posts', 'tallcms_media', 'tallcms_menus', 'tallcms_comments', 'tallcms_revisions'] as $table) {
            $this->assertSame([$siteId], DB::table($table)->pluck('site_id')->all(), $table);
        }
        $this->assertSame([null], DB::table('personal_access_tokens')->pluck('site_id')->all());
    }

    public function test_multi_site_install_leaves_user_owned_content_to_the_content_migration(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $defaultSiteId = $this->createSite('a.test', isDefault: true);
        $this->createSite('b.test');
        DB::table('tallcms_pages')->insert(['created_at' => now()]);
        DB::table('tallcms_contact_submissions')->insert(['created_at' => now()]);
        DB::table('tallcms_posts')->insert(['created_at' => now()]);
        DB::table('tallcms_categories')->insert(['created_at' => now()]);
        DB::table('tallcms_media')->insert(['created_at' => now()]);
        DB::table('tallcms_media_collections')->insert(['created_at' => now()]);

        $this->migrate('2026_11_21_000002_backfill_site_id_on_content_tables');

        $this->assertSame([$defaultSiteId], DB::table('tallcms_pages')->pluck('site_id')->all());
        $this->assertSame([$defaultSiteId], DB::table('tallcms_contact_submissions')->pluck('site_id')->all());
        foreach (['tallcms_posts', 'tallcms_categories', 'tallcms_media', 'tallcms_media_collections'] as $table) {
            $this->assertSame([null], DB::table($table)->pluck('site_id')->all(), $table);
        }
    }

    public function test_children_take_the_site_of_their_parent(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $this->createSite('a.test', isDefault: true);
        $otherSiteId = $this->createSite('b.test');
        $pageId = DB::table('tallcms_pages')->insertGetId(['site_id' => $otherSiteId]);
        $postId = DB::table('tallcms_posts')->insertGetId(['site_id' => $otherSiteId]);
        DB::table('tallcms_comments')->insert(['post_id' => $postId]);
        DB::table('tallcms_revisions')->insert(['revisionable_type' => 'TallCms\Cms\Models\CmsPost', 'revisionable_id' => $postId]);
        DB::table('tallcms_preview_tokens')->insert(['tokenable_type' => 'TallCms\Cms\Models\CmsPage', 'tokenable_id' => $pageId]);

        $this->migrate('2026_11_21_000002_backfill_site_id_on_content_tables');

        $this->assertSame([$otherSiteId], DB::table('tallcms_comments')->pluck('site_id')->all());
        $this->assertSame([$otherSiteId], DB::table('tallcms_revisions')->pluck('site_id')->all());
        $this->assertSame([$otherSiteId], DB::table('tallcms_preview_tokens')->pluck('site_id')->all());
    }

    public function test_orphan_menu_is_renamed_when_the_default_site_already_uses_its_location(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $this->migrate('2026_11_21_000003_scope_menu_location_unique_to_site');
        $siteId = $this->createSite('a.test', isDefault: true);
        DB::table('tallcms_menus')->insert(['location' => 'header', 'site_id' => $siteId]);
        $orphanId = DB::table('tallcms_menus')->insertGetId(['location' => 'header']);

        $this->migrate('2026_11_21_000002_backfill_site_id_on_content_tables');

        $this->assertDatabaseHas('tallcms_menus', ['id' => $orphanId, 'site_id' => $siteId, 'location' => "header-orphaned-{$orphanId}"]);
    }

    public function test_backfill_does_nothing_without_a_default_site(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $this->createSite('a.test');
        DB::table('tallcms_pages')->insert(['created_at' => now()]);

        $this->migrate('2026_11_21_000002_backfill_site_id_on_content_tables');

        $this->assertSame([null], DB::table('tallcms_pages')->pluck('site_id')->all());
    }

    public function test_menu_location_is_unique_per_site(): void
    {
        $this->migrate('2026_11_21_000001_add_site_id_to_content_tables');
        $siteA = $this->createSite('a.test', isDefault: true);
        $siteB = $this->createSite('b.test');

        $this->migrate('2026_11_21_000003_scope_menu_location_unique_to_site');
        $this->migrate('2026_11_21_000003_scope_menu_location_unique_to_site');

        DB::table('tallcms_menus')->insert(['location' => 'header', 'site_id' => $siteA]);
        DB::table('tallcms_menus')->insert(['location' => 'header', 'site_id' => $siteB]);
        $this->expectException(QueryException::class);
        DB::table('tallcms_menus')->insert(['location' => 'header', 'site_id' => $siteA]);
    }

    private function migrate(string $migration): void
    {
        $instance = require __DIR__."/../../database/migrations/{$migration}.php";
        $instance->up();
    }

    private function createSite(string $domain, bool $isDefault = false): int
    {
        return DB::table('tallcms_sites')->insertGetId(['name' => $domain, 'domain' => $domain, 'is_default' => $isDefault]);
    }
}
