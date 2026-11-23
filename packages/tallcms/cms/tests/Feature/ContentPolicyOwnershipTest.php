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
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Models\CmsCategory;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Models\MediaCollection;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Models\TallcmsMedia;
use TallCms\Cms\Policies\CmsCategoryPolicy;
use TallCms\Cms\Policies\CmsPostPolicy;
use TallCms\Cms\Policies\MediaCollectionPolicy;
use TallCms\Cms\Policies\TallcmsMediaPolicy;
use TallCms\Cms\Tests\Fixtures\SiteAccessByList;
use TallCms\Cms\Tests\Fixtures\User;
use TallCms\Cms\Tests\TestCase;

/**
 * Every policy method that takes a record checks the record's site, not only
 * the permission: the API loads a post by its id and then asks the policy, so
 * a permission-only method opens every post to its holder.
 *
 * Who may work in a site is SiteAccess's answer. Who created a record
 * (user_id), who its author is and the user stored on the site grant nothing
 * (805/030).
 */
class ContentPolicyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /** May work in the post's site; neither its author nor its creator. */
    private User $member;

    /** Author and creator of every record, and the user stored on the site; may not work in it. */
    private User $creator;

    private User $superAdmin;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tallcms.api.enabled' => true]);

        // Everyone holds every permission; only the site check is under test.
        Gate::before(fn ($user, string $ability): ?bool => str_contains($ability, ':') ? true : null);

        // The multisite plugin adds user_id (who created the record).
        foreach (['tallcms_posts', 'tallcms_categories', 'tallcms_media', 'tallcms_media_collections'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('user_id')->nullable());
        }

        $this->member = $this->makeUser('member@example.test');
        $this->creator = $this->makeUser('creator@example.test');
        $this->superAdmin = $this->makeUser('admin@example.test', superAdmin: true);

        $this->site = Site::create(['name' => 'Site', 'domain' => 'site.test']);
        $this->site->forceFill(['user_id' => $this->creator->id])->save();

        $this->app->instance(SiteAccess::class, new SiteAccessByList([
            $this->member->id => [$this->site->id],
            $this->superAdmin->id => [$this->site->id],
        ]));
    }

    public function test_a_posts_record_abilities_are_for_users_who_may_work_in_its_site(): void
    {
        $policy = new CmsPostPolicy;
        $draft = $this->makePost('draft');

        foreach (['view', 'update', 'delete', 'replicate', 'submitForReview', 'viewRevisions', 'restoreRevision', 'generatePreviewLink'] as $ability) {
            $this->assertTrue($policy->{$ability}($this->member, $draft), "{$ability} for a user of the site");
            $this->assertTrue($policy->{$ability}($this->superAdmin, $draft), "{$ability} for a super admin");
            $this->assertFalse($policy->{$ability}($this->creator, $draft), "{$ability} for its creator and author without access to the site");
        }
    }

    public function test_a_pending_post_is_approved_by_an_approver_of_its_site(): void
    {
        $pending = $this->makePost('pending');
        $policy = new CmsPostPolicy;

        $this->assertTrue($policy->approve($this->member, $pending), 'review workflow: the approver is not the author');
        $this->assertTrue($policy->approve($this->superAdmin, $pending));
        $this->assertFalse($policy->approve($this->creator, $pending), 'its creator without access to the site');
        $this->assertFalse($policy->approve($this->member, $this->makePost('draft')), 'only pending posts');
    }

    public function test_a_post_without_a_site_is_a_super_admins_only(): void
    {
        $orphan = $this->makePost('draft', siteId: null);
        $policy = new CmsPostPolicy;

        $this->assertTrue($policy->view($this->superAdmin, $orphan));
        $this->assertFalse($policy->view($this->member, $orphan));
        $this->assertFalse($policy->view($this->creator, $orphan), 'its creator and author');
    }

    public function test_categories_media_and_collections_are_for_users_who_may_work_in_their_site(): void
    {
        $stamps = ['site_id' => $this->site->id, 'user_id' => $this->creator->id, 'created_at' => now(), 'updated_at' => now()];
        $category = CmsCategory::findOrFail(DB::table('tallcms_categories')->insertGetId([
            'name' => json_encode(['en' => 'News']), 'slug' => json_encode(['en' => 'news']),
        ] + $stamps));
        $media = TallcmsMedia::findOrFail(DB::table('tallcms_media')->insertGetId([
            'name' => 'a.jpg', 'file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'path' => 'media/a.jpg', 'disk' => 'public', 'size' => 1,
        ] + $stamps));
        $collection = MediaCollection::findOrFail(DB::table('tallcms_media_collections')->insertGetId([
            'name' => 'Photos', 'slug' => 'photos',
        ] + $stamps));

        $this->assertTrue((new CmsCategoryPolicy)->replicate($this->member, $category));
        $this->assertFalse((new CmsCategoryPolicy)->replicate($this->creator, $category));
        $this->assertTrue((new TallcmsMediaPolicy)->replicate($this->member, $media));
        $this->assertFalse((new TallcmsMediaPolicy)->replicate($this->creator, $media));
        $this->assertTrue((new MediaCollectionPolicy)->update($this->member, $collection));
        $this->assertFalse((new MediaCollectionPolicy)->update($this->creator, $collection));
    }

    public function test_the_api_refuses_a_post_of_a_site_the_user_may_not_work_in(): void
    {
        $draft = $this->makePost('draft');
        Sanctum::actingAs($this->creator, ['*']);

        $this->getJson("/api/v1/tallcms/posts/{$draft->id}/revisions")->assertForbidden();
        $this->postJson("/api/v1/tallcms/posts/{$draft->id}/submit-for-review")->assertForbidden();
        $this->postJson("/api/v1/tallcms/posts/{$draft->id}/revisions/1/restore")->assertForbidden();

        Sanctum::actingAs($this->member, ['*']);

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

    private function makePost(string $status, int|null|false $siteId = false): CmsPost
    {
        $id = DB::table('tallcms_posts')->insertGetId([
            'site_id' => $siteId === false ? $this->site->id : $siteId,
            'title' => json_encode(['en' => 'Post']),
            'slug' => json_encode(['en' => 'post-'.uniqid()]),
            'status' => $status,
            'author_id' => $this->creator->id,
            'user_id' => $this->creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CmsPost::findOrFail($id);
    }
}
