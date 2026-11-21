<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Support\RelationshipSelectQuery;
use TallCms\Cms\Tests\TestCase;

/**
 * The post form's categories select must not run SELECT DISTINCT over the
 * categories' JSON columns (fails on PostgreSQL). SQLite cannot reproduce
 * the error, so these tests go by the generated SQL.
 */
class RelationshipSelectQueryTest extends TestCase
{
    use RefreshDatabase;

    private const PIVOT = 'tallcms_post_category';

    public function test_filaments_option_query_still_has_the_shape_the_fix_is_written_for(): void
    {
        $sql = $this->filamentOptionQuery()->toSql();

        $this->assertStringContainsString('select distinct', $sql);
        $this->assertStringContainsString('left join "'.self::PIVOT.'"', $sql);
    }

    public function test_the_option_query_loses_the_pivot_join_and_distinct(): void
    {
        $sql = RelationshipSelectQuery::withoutPivotDistinct($this->filamentOptionQuery(), self::PIVOT)->toSql();

        $this->assertStringNotContainsString('distinct', $sql);
        $this->assertStringNotContainsString(self::PIVOT, $sql);
    }

    public function test_a_posts_own_categories_keep_their_pivot_join(): void
    {
        $query = (new CmsPost)->forceFill(['id' => 1])->categories()->getQuery();
        $before = $query->toSql();

        $this->assertSame($before, RelationshipSelectQuery::withoutPivotDistinct($query, self::PIVOT)->toSql());
        $this->assertStringContainsString('inner join "'.self::PIVOT.'"', $before);
    }

    public function test_joins_on_other_tables_stay(): void
    {
        $query = $this->filamentOptionQuery()->leftJoin('tallcms_sites', 'tallcms_sites.id', '=', 'tallcms_categories.site_id');

        $sql = RelationshipSelectQuery::withoutPivotDistinct($query, self::PIVOT)->toSql();

        $this->assertStringContainsString('left join "tallcms_sites"', $sql);
        $this->assertStringNotContainsString(self::PIVOT, $sql);
    }

    private function filamentOptionQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $relationship = Relation::noConstraints(fn () => (new CmsPost)->categories());

        return app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);
    }
}
