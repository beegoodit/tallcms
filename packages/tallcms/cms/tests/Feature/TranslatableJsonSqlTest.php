<?php

namespace TallCms\Cms\Tests\Feature;

use Filament\Forms\Components\Select;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Filament\Resources\CmsPosts\Schemas\CmsPostForm;
use TallCms\Cms\Filament\Resources\CmsPosts\Tables\CmsPostsTable;
use TallCms\Cms\Models\CmsCategory;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Services\SitemapService;
use TallCms\Cms\Tests\TestCase;

class TranslatableJsonSqlTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('tallcms_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('description')->nullable();
            $table->string('color')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tallcms_posts', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tallcms_post_category', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('category_id');
        });
    }

    public function test_with_slug_finds_the_current_locale_inside_json(): void
    {
        $this->category('News', 'Nachrichten', 'news', 'nachrichten', 2);

        $found = CmsCategory::query()->withSlug('news')->first();

        $this->assertNotNull($found);
        $this->assertSame('News', (string) $found->name);
        $this->assertNull(CmsCategory::query()->withSlug('nachrichten')->first());

        app()->setLocale('de');

        $german = CmsCategory::query()->withSlug('nachrichten')->first();

        $this->assertNotNull($german);
        $this->assertSame($found->getKey(), $german->getKey());
        $this->assertNull(CmsCategory::query()->withSlug('news')->first());
    }

    public function test_public_category_slug_uses_the_runtime_locale_and_falls_back_when_i18n_is_on(): void
    {
        $category = $this->category('News', 'Nachrichten', 'news', 'nachrichten', 1);
        $postId = DB::table('tallcms_posts')->insertGetId([
            'title' => json_encode(['en' => 'Hello', 'de' => 'Hallo']),
            'slug' => json_encode(['en' => 'hello', 'de' => 'hallo']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tallcms_post_category')->insert([
            'post_id' => $postId,
            'category_id' => $category->getKey(),
        ]);

        app()->setLocale('de');
        config(['tallcms.i18n.enabled' => true]);

        $this->assertSame($category->getKey(), CmsCategory::query()->forPublicSlug('nachrichten')->value('id'));
        $this->assertSame($category->getKey(), CmsCategory::query()->forPublicSlug('news')->value('id'));
        $this->assertSame(1, CmsPost::inCategory('nachrichten')->count());
        $this->assertSame(1, CmsPost::inCategory('news')->count());
    }

    public function test_translatable_search_matches_the_locale_text_not_the_json_keys(): void
    {
        $this->category('News', 'Nachrichten', 'news', 'nachrichten', 1);

        app()->setLocale('en');

        $sql = CmsCategory::query()->whereTranslatableContains('name', 'News')->toSql();

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
        $this->assertStringNotContainsString('::jsonb', $sql);
        $this->assertSame(1, CmsCategory::query()->whereTranslatableContains('name', 'News')->count());
        $this->assertSame(0, CmsCategory::query()->whereTranslatableContains('name', 'Nachrichten')->count());
        $this->assertSame(0, CmsCategory::query()->whereTranslatableContains('name', 'en')->count());
    }

    public function test_sitemap_categories_order_by_sort_order(): void
    {
        $this->category('News', 'Nachrichten', 'news', 'nachrichten', 1);

        $sql = '';
        DB::listen(function ($query) use (&$sql): void {
            if (str_contains($query->sql, 'tallcms_categories')) {
                $sql .= ' '.$query->sql;
            }
        });

        Cache::flush();
        SitemapService::getCategories();

        $this->assertStringContainsString('sort_order', $sql);
        $this->assertDoesNotMatchRegularExpression('/order by ["`]?name["`]?/i', $sql);
    }

    public function test_category_selects_search_and_label_without_distinct_json_rows(): void
    {
        $category = $this->category('News', 'Nachrichten', 'news', 'nachrichten', 2);
        $postId = DB::table('tallcms_posts')->insertGetId([
            'title' => json_encode(['en' => 'Hello']),
            'slug' => json_encode(['en' => 'hello']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tallcms_post_category')->insert([
            'post_id' => $postId,
            'category_id' => $category->getKey(),
        ]);

        $field = CmsPostsTable::categoryFilter()->getFormField();

        $searchSql = $this->captureSql(function () use ($field, $category): void {
            $results = $this->invokeSelectCallback($field, 'getSearchResultsUsing', ['News']);

            $this->assertSame('News', $this->optionLabel($results, $category));
        });
        $this->assertStringNotContainsString('distinct', strtolower($searchSql));

        $labelSql = $this->captureSql(function () use ($field, $category): void {
            $labels = $this->invokeSelectCallback($field, 'getOptionLabelsUsing', [[$category->getKey()]]);

            $this->assertSame('News', $this->optionLabel($labels, $category));
        });
        $this->assertStringNotContainsString('distinct', strtolower($labelSql));

        $filter = CmsPostsTable::categoryFilter();
        $filterSql = $this->captureSql(function () use ($filter, $category, $postId): void {
            $matches = $filter->apply(CmsPost::query(), [
                'values' => [$category->getKey()],
            ])->pluck('id');

            $this->assertCount(1, $matches);
            $this->assertTrue($matches->contains($postId));
        });
        $this->assertStringNotContainsString('distinct', strtolower($filterSql));

        $select = CmsPostForm::categorySelect();

        $formSql = $this->captureSql(function () use ($select, $category): void {
            $labels = $this->invokeSelectCallback($select, 'getOptionLabelsUsing', [[$category->getKey()]]);
            $results = $this->invokeSelectCallback($select, 'getSearchResultsUsing', ['news']);

            $this->assertSame('News', $this->optionLabel($labels, $category));
            $this->assertSame('News', $this->optionLabel($results, $category));
        });
        $this->assertStringNotContainsString('distinct', strtolower($formSql));
    }

    public function test_call_sites_do_not_order_or_pluck_translatable_json(): void
    {
        $root = dirname(__DIR__, 2);

        $postsTable = file_get_contents($root.'/src/Filament/Resources/CmsPosts/Tables/CmsPostsTable.php');
        $this->assertStringContainsString('translatedSelectOptions', $postsTable);
        $this->assertStringContainsString('whereHas(', $postsTable);
        $this->assertStringNotContainsString("relationship(\n                        'categories'", $postsTable);
        $categories = file_get_contents($root.'/src/Models/CmsCategory.php');
        $this->assertStringContainsString("orderBy('sort_order')", $categories);
        $this->assertStringNotContainsString('->sortable()', $this->columnBlock($postsTable, 'title'));

        $pagesTable = file_get_contents($root.'/src/Filament/Resources/CmsPages/Tables/CmsPagesTable.php');
        $this->assertStringNotContainsString('->sortable()', $this->columnBlock($pagesTable, 'title'));

        $categoriesTable = file_get_contents($root.'/src/Filament/Resources/CmsCategories/Tables/CmsCategoriesTable.php');
        $this->assertStringNotContainsString('->sortable()', $this->columnBlock($categoriesTable, 'name'));

        $comments = file_get_contents($root.'/src/Filament/Resources/CmsComments/Tables/CmsCommentsTable.php');
        $this->assertStringNotContainsString('->sortable()', $this->columnBlock($comments, 'post.title'));

        $widget = file_get_contents($root.'/resources/views/components/widgets/categories.blade.php');
        $this->assertStringContainsString("orderBy('sort_order')", $widget);
        $this->assertStringNotContainsString("orderBy('name')", $widget);

        $sitemap = file_get_contents($root.'/src/Services/SitemapService.php');
        $this->assertStringNotContainsString("orderBy('name')", $sitemap);

        foreach ([
            $root.'/src/Http/Controllers/CategoryArchiveController.php',
            $root.'/src/Http/Controllers/RssFeedController.php',
        ] as $controller) {
            $source = file_get_contents($controller);
            $this->assertStringContainsString('forPublicSlug(', $source);
            $this->assertStringNotContainsString("where('slug'", $source);
        }

        foreach ([
            $root.'/src/Filament/Resources/CmsPosts/Schemas/CmsPostForm.php',
            $root.'/src/Filament/Resources/CmsCategories/Schemas/CmsCategoryForm.php',
            $root.'/src/Filament/Blocks/PostsBlock.php',
        ] as $form) {
            $this->assertStringNotContainsString("pluck('name'", file_get_contents($form));
            $this->assertStringNotContainsString("pluck('title'", file_get_contents($form));
        }

        $relation = file_get_contents($root.'/src/Filament/Resources/SiteResource/RelationManagers/PagesRelationManager.php');
        $this->assertStringContainsString("whereTranslatableContains('title'", $relation);
        $this->assertStringContainsString("whereTranslatableContains('slug'", $relation);
        $this->assertStringNotContainsString('->sortable()', $this->columnBlock($relation, 'title'));
    }

    /**
     * @param  array<int|string, string>  $options
     */
    private function optionLabel(array $options, CmsCategory $category): ?string
    {
        return $options[$category->getKey()] ?? $options[(string) $category->getKey()] ?? null;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokeSelectCallback(Select $select, string $property, array $arguments): mixed
    {
        $callback = (fn () => $this->{$property})->call($select);

        $this->assertInstanceOf(\Closure::class, $callback);

        return $callback(...$arguments);
    }

    private function captureSql(callable $callback): string
    {
        $sql = '';
        DB::listen(function ($query) use (&$sql): void {
            $sql .= ' '.$query->sql;
        });

        $callback();

        return $sql;
    }

    private function category(string $enName, string $deName, string $enSlug, string $deSlug, int $sortOrder): CmsCategory
    {
        $category = new CmsCategory;
        $category->setTranslation('name', 'en', $enName);
        $category->setTranslation('name', 'de', $deName);
        $category->setTranslation('slug', 'en', $enSlug);
        $category->setTranslation('slug', 'de', $deSlug);
        $category->sort_order = $sortOrder;
        $category->save();

        return $category;
    }

    private function columnBlock(string $source, string $column): string
    {
        $needle = "TextColumn::make('{$column}')";
        $start = strpos($source, $needle);
        $this->assertNotFalse($start, "Missing column {$column}");

        $next = strpos($source, 'TextColumn::make(', $start + strlen($needle));

        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }
}
