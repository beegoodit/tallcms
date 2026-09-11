<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Unit;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Filament\Widgets\ContentHealthWidget;
use TallCms\Cms\Tests\TestCase;

class ContentHealthWidgetProbe extends ContentHealthWidget
{
    public function exposeStats(): array
    {
        return $this->getStats();
    }

    public function exposeConstrainEmptyJsonColumn(Builder $query, string $column): void
    {
        $this->constrainEmptyJsonColumn($query, $column);
    }
}

class ContentHealthWidgetTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('tallcms_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('status')->default('draft');
            $table->json('meta_description')->nullable();
            $table->string('featured_image')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_missing_meta_count_runs_against_json_column(): void
    {
        session(['multisite_admin_site_id' => '__all_sites__']);

        DB::table('tallcms_posts')->insert([
            [
                'status' => 'published',
                'meta_description' => null,
                'featured_image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => 'published',
                'meta_description' => '{}',
                'featured_image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => 'published',
                'meta_description' => json_encode(['en' => 'A description']),
                'featured_image' => 'hero.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => 'draft',
                'meta_description' => null,
                'featured_image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = (new ContentHealthWidgetProbe)->exposeStats();

        $this->assertCount(3, $stats);
        $this->assertSame('3', (string) $stats[0]->getValue());
        $this->assertSame('2', (string) $stats[2]->getValue());
    }

    public function test_postgres_empty_json_constraint_casts_to_text(): void
    {
        $builder = DB::table('tallcms_posts');
        $mock = \Mockery::mock($builder->getConnection())->makePartial();
        $mock->shouldReceive('getDriverName')->andReturn('pgsql');

        (new \ReflectionProperty(Builder::class, 'connection'))->setValue($builder, $mock);

        (new ContentHealthWidgetProbe)->exposeConstrainEmptyJsonColumn($builder, 'meta_description');

        $this->assertStringContainsString('::text IN', $builder->toSql());
    }
}
