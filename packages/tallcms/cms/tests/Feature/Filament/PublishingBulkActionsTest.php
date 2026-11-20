<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature\Filament;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TallCms\Cms\Enums\ContentStatus;
use TallCms\Cms\Filament\Tables\PublishingBulkActions;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Tests\Fixtures\User;
use TallCms\Cms\Tests\TestCase;

class PublishingBulkActionsTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('tallcms_posts', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->text('search_content')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tallcms_revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->text('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('featured_image')->nullable();
            $table->json('additional_data')->nullable();
            $table->unsignedInteger('revision_number');
            $table->text('notes')->nullable();
            $table->string('content_hash')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
        });
    }

    public function test_publish_records_publishes_drafts_and_skips_others(): void
    {
        $draftA = $this->makePost(['status' => ContentStatus::Draft->value]);
        $draftB = $this->makePost(['status' => ContentStatus::Draft->value, 'slug' => ['en' => 'draft-b']]);
        $pending = $this->makePost([
            'status' => ContentStatus::Pending->value,
            'slug' => ['en' => 'pending'],
            'submitted_at' => now(),
        ]);
        $published = $this->makePost([
            'status' => ContentStatus::Published->value,
            'slug' => ['en' => 'published'],
            'published_at' => now()->subDay(),
        ]);

        $result = PublishingBulkActions::publishRecords(
            Collection::make([$draftA, $draftB, $pending, $published]),
            canUpdate: fn (): bool => true,
        );

        $this->assertSame(2, $result['done']);
        $this->assertSame(2, $result['skipped']);

        $this->assertSame(ContentStatus::Published->value, $draftA->fresh()->status);
        $this->assertNotNull($draftA->fresh()->published_at);
        $this->assertSame(ContentStatus::Published->value, $draftB->fresh()->status);
        $this->assertSame(ContentStatus::Pending->value, $pending->fresh()->status);
        $this->assertSame(ContentStatus::Published->value, $published->fresh()->status);
    }

    public function test_publish_records_skips_when_can_update_denies(): void
    {
        $draft = $this->makePost(['status' => ContentStatus::Draft->value]);

        $result = PublishingBulkActions::publishRecords(
            Collection::make([$draft]),
            canUpdate: fn (): bool => false,
        );

        $this->assertSame(0, $result['done']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(ContentStatus::Draft->value, $draft->fresh()->status);
    }

    public function test_unpublish_records_matches_api_semantics(): void
    {
        $published = $this->makePost([
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subHour(),
            'approved_by' => 1,
            'approved_at' => now()->subHour(),
        ]);
        $draft = $this->makePost([
            'status' => ContentStatus::Draft->value,
            'slug' => ['en' => 'still-draft'],
        ]);

        $result = PublishingBulkActions::unpublishRecords(
            Collection::make([$published, $draft]),
            canUpdate: fn (): bool => true,
        );

        $this->assertSame(1, $result['done']);
        $this->assertSame(1, $result['skipped']);

        $published->refresh();
        $this->assertSame(ContentStatus::Draft->value, $published->status);
        $this->assertNull($published->published_at);
        $this->assertSame(1, $published->approved_by);
        $this->assertNotNull($published->approved_at);
    }

    public function test_approve_records_uses_workflow_service(): void
    {
        config(['tallcms.publishing.notifications_enabled' => false]);

        $user = User::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->actingAs($user);

        $pending = $this->makePost([
            'status' => ContentStatus::Pending->value,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $draft = $this->makePost([
            'status' => ContentStatus::Draft->value,
            'slug' => ['en' => 'draft-skip'],
        ]);

        $result = PublishingBulkActions::approveRecords(
            Collection::make([$pending, $draft]),
            canApprove: fn (): bool => true,
        );

        $this->assertSame(1, $result['done']);
        $this->assertSame(1, $result['skipped']);

        $pending->refresh();
        $this->assertSame(ContentStatus::Published->value, $pending->status);
        $this->assertNotNull($pending->published_at);
        $this->assertSame($user->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);
    }

    public function test_make_returns_three_named_bulk_actions(): void
    {
        $actions = PublishingBulkActions::make();

        $this->assertCount(3, $actions);
        $this->assertSame('publishSelected', $actions[0]->getName());
        $this->assertSame('approveSelected', $actions[1]->getName());
        $this->assertSame('unpublishSelected', $actions[2]->getName());
    }

    public function test_posts_and_pages_tables_wire_publishing_bulk_actions(): void
    {
        $base = dirname(__DIR__, 3).'/src/Filament/Resources';

        foreach ([
            'CmsPosts/Tables/CmsPostsTable.php',
            'CmsPages/Tables/CmsPagesTable.php',
            'SiteResource/RelationManagers/PagesRelationManager.php',
        ] as $relative) {
            $source = file_get_contents($base.'/'.$relative);
            $this->assertStringContainsString(
                'PublishingBulkActions::make()',
                $source,
                $relative.' must wire PublishingBulkActions::make()',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePost(array $overrides = []): CmsPost
    {
        return CmsPost::query()->create(array_merge([
            'title' => ['en' => 'Post'],
            'slug' => ['en' => 'post-'.uniqid()],
            'status' => ContentStatus::Draft->value,
            'author_id' => 1,
        ], $overrides));
    }
}
