<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use TallCms\Cms\Models\Site;
use TallCms\Cms\Policies\Concerns\ChecksSiteOwnership;
use TallCms\Cms\Tests\TestCase;

/**
 * Content checks in a site panel go by the tenant, outside by sites.user_id (805/012).
 */
class SitePanelOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private GenericUser $editor;

    private Site $tenantSite;

    private Site $otherSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = new GenericUser(['id' => 42]);
        $this->tenantSite = Site::create(['name' => 'Tenant', 'domain' => 'tenant.test']);
        $this->otherSite = Site::create(['name' => 'Other', 'domain' => 'other.test']);
    }

    public function test_in_a_site_panel_content_of_the_tenant_is_accessible_whoever_owns_the_site(): void
    {
        $this->inSitePanelOf($this->tenantSite);

        $this->assertTrue($this->ownership()->owns($this->editor, $this->tenantSite->id));
    }

    public function test_in_a_site_panel_content_of_another_site_is_refused(): void
    {
        $this->tenantSite->forceFill(['user_id' => $this->editor->getAuthIdentifier()])->save();
        $this->otherSite->forceFill(['user_id' => $this->editor->getAuthIdentifier()])->save();
        $this->inSitePanelOf($this->tenantSite);

        $this->assertFalse($this->ownership()->owns($this->editor, $this->otherSite->id));
    }

    public function test_outside_a_site_panel_the_site_owner_decides(): void
    {
        $this->otherSite->forceFill(['user_id' => $this->editor->getAuthIdentifier()])->save();

        $this->assertFalse($this->ownership()->owns($this->editor, $this->tenantSite->id));
        $this->assertTrue($this->ownership()->owns($this->editor, $this->otherSite->id));
    }

    private function inSitePanelOf(Site $site): void
    {
        Filament::swap(Mockery::mock(FilamentManager::class, ['getTenant' => $site]));
    }

    private function ownership(): object
    {
        return new class
        {
            use ChecksSiteOwnership;

            public function owns(Authenticatable $user, ?int $siteId): bool
            {
                return $this->userOwnsContentSite($user, $siteId);
            }
        };
    }
}
