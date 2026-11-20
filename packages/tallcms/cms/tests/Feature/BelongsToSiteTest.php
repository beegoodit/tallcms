<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use TallCms\Cms\Filament\Forms\OwnerSitePicker;
use TallCms\Cms\Models\CmsComment;
use TallCms\Cms\Models\CmsPage;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Models\CmsPreviewToken;
use TallCms\Cms\Models\CmsRevision;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Models\TallcmsContactSubmission;
use TallCms\Cms\Tests\Fixtures\User;
use TallCms\Cms\Tests\TestCase;

/**
 * New site-owned content gets its site_id from the SiteContext (core: the
 * Filament tenant, else the default site); children from their parent.
 */
class BelongsToSiteTest extends TestCase
{
    use RefreshDatabase;

    private Site $defaultSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultSite = Site::getDefault();
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    }

    public function test_new_content_without_a_site_gets_the_default_site(): void
    {
        $page = $this->createPage();
        $post = $this->createPost();
        $submission = TallcmsContactSubmission::create(['form_data' => ['message' => 'Hi']]);

        $this->assertSame($this->defaultSite->id, $page->site_id);
        $this->assertSame($this->defaultSite->id, $post->site_id);
        $this->assertSame($this->defaultSite->id, $submission->site_id);
        $this->assertTrue($page->site->is($this->defaultSite));
    }

    public function test_a_site_set_by_the_caller_is_kept(): void
    {
        $otherSite = $this->createSite('other.test');

        $page = $this->createPage(['site_id' => $otherSite->id]);

        $this->assertSame($otherSite->id, $page->site_id);
    }

    public function test_the_filament_tenant_site_wins_over_the_default_site(): void
    {
        $tenantSite = $this->createSite('tenant.test');
        Filament::swap(Mockery::mock(FilamentManager::class, ['getTenant' => $tenantSite]));

        $page = $this->createPage();

        $this->assertSame($tenantSite->id, $page->site_id);
    }

    public function test_unassigned_models_keep_site_id_empty(): void
    {
        config(['tallcms.sites.unassigned_models' => [CmsPost::class]]);

        $post = $this->createPost();

        $this->assertNull($post->site_id);
        $this->assertSame($this->defaultSite->id, $this->createPage()->site_id);
    }

    public function test_children_take_the_site_of_their_parent(): void
    {
        $otherSite = $this->createSite('other.test');
        $page = $this->createPage(['site_id' => $otherSite->id]);
        $post = $this->createPost();
        $post->forceFill(['site_id' => $otherSite->id])->save();

        $comment = CmsComment::create(['post_id' => $post->id, 'content' => 'Nice']);
        $revision = CmsRevision::create(['revisionable_type' => $page->getMorphClass(), 'revisionable_id' => $page->id, 'revision_number' => 99, 'title' => 'Snapshot', 'content' => 'Body']);
        $token = CmsPreviewToken::create(['token' => 'abc', 'tokenable_type' => $post->getMorphClass(), 'tokenable_id' => $post->id, 'expires_at' => now()->addDay(), 'created_by' => auth()->id()]);

        $this->assertSame($otherSite->id, $comment->site_id);
        $this->assertSame($otherSite->id, $revision->site_id);
        $this->assertSame($otherSite->id, $token->site_id);
    }

    public function test_web_requests_cannot_create_site_owned_content_without_a_site(): void
    {
        $this->defaultSite->update(['is_default' => false]);
        $this->runOutsideConsole();

        $this->expectException(RuntimeException::class);
        $this->createPage();
    }

    public function test_children_of_unassigned_parents_may_stay_without_a_site(): void
    {
        config(['tallcms.sites.unassigned_models' => [CmsPost::class]]);
        $post = $this->createPost();
        $this->runOutsideConsole();

        $revision = CmsRevision::create(['revisionable_type' => $post->getMorphClass(), 'revisionable_id' => $post->id, 'revision_number' => 99, 'title' => 'Snapshot', 'content' => 'Body']);

        $this->assertNull($revision->site_id);
    }

    public function test_page_pickers_on_a_new_record_list_the_current_sites_pages(): void
    {
        $ownPage = $this->createPage(['status' => 'published']);
        $this->createPage(['status' => 'published', 'slug' => ['en' => 'elsewhere'], 'site_id' => $this->createSite('other.test')->id]);

        $options = OwnerSitePicker::publishedPages(new \stdClass);

        $this->assertSame([$ownPage->id], array_keys($options));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPage(array $attributes = []): CmsPage
    {
        return CmsPage::create($attributes + ['title' => ['en' => 'About'], 'slug' => ['en' => 'about']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPost(array $attributes = []): CmsPost
    {
        return CmsPost::create($attributes + ['title' => ['en' => 'News'], 'slug' => ['en' => 'news'], 'content' => ['en' => 'Body'], 'author_id' => auth()->id()]);
    }

    private function createSite(string $domain): Site
    {
        return Site::create(['name' => $domain, 'domain' => $domain]);
    }

    private function runOutsideConsole(): void
    {
        (new ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }
}
