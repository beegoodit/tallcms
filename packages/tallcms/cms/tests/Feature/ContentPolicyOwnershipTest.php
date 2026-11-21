<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use TallCms\Cms\Models\CmsCategory;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Models\TallcmsMedia;
use TallCms\Cms\Policies\CmsCategoryPolicy;
use TallCms\Cms\Policies\CmsPostPolicy;
use TallCms\Cms\Policies\TallcmsMediaPolicy;
use TallCms\Cms\Tests\Fixtures\User;
use TallCms\Cms\Tests\TestCase;

/**
 * Every policy method that takes a record checks who the record belongs to,
 * not only the permission: the API loads a post by its id and then asks the
 * policy, so a permission-only method opens every post to its holder.
 */
class ContentPolicyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tallcms.api.enabled' => true]);

        // Everyone holds every permission; only ownership is under test.
        Gate::before(fn ($user, string $ability): ?bool => str_contains($ability, ':') ? true : null);

        $this->owner = $this->makeUser('owner@example.test');
        $this->other = $this->makeUser('other@example.test');
        $this->superAdmin = $this->makeUser('admin@example.test', superAdmin: true);
    }

    public function test_a_posts_record_abilities_are_for_its_author_and_super_admins(): void
    {
        $policy = new CmsPostPolicy;
        $draft = $this->makePost('draft');

        foreach (['replicate', 'submitForReview', 'viewRevisions', 'restoreRevision'] as $ability) {
            $this->assertTrue($policy->{$ability}($this->owner, $draft), "{$ability} for the author");
            $this->assertTrue($policy->{$ability}($this->superAdmin, $draft), "{$ability} for a super admin");
            $this->assertFalse($policy->{$ability}($this->other, $draft), "{$ability} for someone else");
        }
    }

    public function test_without_an_owner_column_any_approver_approves_a_pending_post(): void
    {
        $pending = $this->makePost('pending');

        $this->assertTrue((new CmsPostPolicy)->approve($this->other, $pending), 'review workflow: the approver is not the author');
        $this->assertFalse((new CmsPostPolicy)->approve($this->other, $this->makePost('draft')), 'only pending posts');
    }

    public function test_a_post_with_an_owner_is_approved_by_that_owner_or_a_super_admin_only(): void
    {
        $this->addOwnerColumn('tallcms_posts');
        $pending = $this->makePost('pending', ownerId: $this->owner->id);
        $policy = new CmsPostPolicy;

        $this->assertTrue($policy->approve($this->owner, $pending));
        $this->assertTrue($policy->approve($this->superAdmin, $pending));
        $this->assertFalse($policy->approve($this->other, $pending));
    }

    public function test_categories_and_media_with_an_owner_are_replicated_by_that_owner_only(): void
    {
        $this->addOwnerColumn('tallcms_categories');
        $this->addOwnerColumn('tallcms_media');
        $category = CmsCategory::findOrFail(DB::table('tallcms_categories')->insertGetId([
            'name' => json_encode(['en' => 'News']), 'slug' => json_encode(['en' => 'news']), 'user_id' => $this->owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]));
        $media = TallcmsMedia::findOrFail(DB::table('tallcms_media')->insertGetId([
            'name' => 'a.jpg', 'file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'path' => 'media/a.jpg', 'disk' => 'public', 'size' => 1,
            'user_id' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->assertTrue((new CmsCategoryPolicy)->replicate($this->owner, $category));
        $this->assertFalse((new CmsCategoryPolicy)->replicate($this->other, $category));
        $this->assertTrue((new TallcmsMediaPolicy)->replicate($this->owner, $media));
        $this->assertFalse((new TallcmsMediaPolicy)->replicate($this->other, $media));
    }

    public function test_the_api_refuses_another_users_post_for_revisions_review_and_restore(): void
    {
        $draft = $this->makePost('draft');
        Sanctum::actingAs($this->other, ['*']);

        $this->getJson("/api/v1/tallcms/posts/{$draft->id}/revisions")->assertForbidden();
        $this->postJson("/api/v1/tallcms/posts/{$draft->id}/submit-for-review")->assertForbidden();
        $this->postJson("/api/v1/tallcms/posts/{$draft->id}/revisions/1/restore")->assertForbidden();

        Sanctum::actingAs($this->owner, ['*']);

        $this->getJson("/api/v1/tallcms/posts/{$draft->id}/revisions")->assertSuccessful();
    }

    private function makeUser(string $email, bool $superAdmin = false): User
    {
        $user = new class extends User
        {
            protected $table = 'users';

            public bool $isSuperAdmin = false;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->isSuperAdmin && in_array('super_admin', (array) $roles, true);
            }
        };
        $user->forceFill(['name' => $email, 'email' => $email, 'password' => Hash::make('password')])->save();
        $user->isSuperAdmin = $superAdmin;

        return $user;
    }

    private function makePost(string $status, ?int $ownerId = null): CmsPost
    {
        $id = DB::table('tallcms_posts')->insertGetId(array_filter([
            'title' => json_encode(['en' => 'Post']),
            'slug' => json_encode(['en' => 'post-'.uniqid()]),
            'status' => $status,
            'author_id' => $this->owner->id,
            'user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ], fn ($value) => $value !== null));

        return CmsPost::findOrFail($id);
    }

    /**
     * The multisite plugin adds user_id (ownership) to these tables.
     */
    private function addOwnerColumn(string $table): void
    {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('user_id')->nullable());
    }
}
