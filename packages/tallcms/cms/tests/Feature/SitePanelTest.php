<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Filament\Concerns\HasSiteTenants;
use TallCms\Cms\Filament\Pages\SiteDashboard;
use TallCms\Cms\Models\Site;
use TallCms\Cms\SitePanelPlugin;
use TallCms\Cms\Tests\Fixtures\User;
use TallCms\Cms\Tests\TestCase;

/**
 * The site panel: site as tenant (by uuid), and which sites a user may open.
 */
class SitePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plugin_makes_the_configured_site_model_the_tenant_by_uuid(): void
    {
        config(['tallcms.sites.model' => SiteSubclass::class]);
        $panel = Panel::make()->id('site');

        SitePanelPlugin::make()->register($panel);

        $this->assertSame(SiteSubclass::class, $panel->getTenantModel());
        $this->assertSame('uuid', $panel->getTenantSlugAttribute());
        $this->assertContains(SiteDashboard::class, $panel->getPages());
    }

    public function test_by_default_a_user_may_open_every_site_ordered_by_name(): void
    {
        $default = Site::getDefault();
        $zoo = Site::create(['name' => 'Zoo', 'domain' => 'zoo.test']);
        $alpha = Site::create(['name' => 'Alpha', 'domain' => 'alpha.test', 'is_active' => false]);
        $user = $this->createUser();

        $names = $user->getSiteTenants()->pluck('name')->all();

        $this->assertSame(collect([$default, $zoo, $alpha])->pluck('name')->sort()->values()->all(), $names);
        $this->assertTrue($user->canAccessSiteTenant($alpha));
    }

    public function test_the_user_trait_asks_the_bound_site_access(): void
    {
        $allowed = Site::create(['name' => 'Allowed', 'domain' => 'allowed.test']);
        $refused = Site::create(['name' => 'Refused', 'domain' => 'refused.test']);
        $this->app->instance(SiteAccess::class, new class($allowed) implements SiteAccess
        {
            public function __construct(private Site $allowed) {}

            public function accessibleSites(Authenticatable $user): Collection
            {
                return collect([$this->allowed]);
            }

            public function canAccess(Authenticatable $user, Site $site): bool
            {
                return $site->is($this->allowed);
            }
        });
        $user = $this->createUser();

        $this->assertSame([$allowed->id], $user->getSiteTenants()->pluck('id')->all());
        $this->assertTrue($user->canAccessSiteTenant($allowed));
        $this->assertFalse($user->canAccessSiteTenant($refused));
    }

    private function createUser(): UserWithSiteTenants
    {
        return UserWithSiteTenants::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']);
    }
}

class UserWithSiteTenants extends User
{
    use HasSiteTenants;

    protected $table = 'users';
}

class SiteSubclass extends Site {}
