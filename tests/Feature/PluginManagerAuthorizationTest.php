<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use TallCms\Cms\Filament\Pages\PluginManager;
use Tests\TestCase;

/**
 * Regression test for issue #122: PluginManager's plugin install/update
 * actions (extract a ZIP or fetch a vendor update, PSR-4 autoload it, boot
 * its service provider) must be restricted to super_admin, not just the
 * View:PluginManager page permission. Mirrors ThemeManager's identical
 * action-gating pattern (visible() + authorize() + an in-closure guard).
 */
class PluginManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Don't let this authorization test depend on today's (insecure-by-default)
        // config value — assert the role gate independent of the upload flag.
        config(['tallcms.plugins.allow_uploads' => true]);
    }

    public function test_non_super_admin_with_page_permission_cannot_see_install_action(): void
    {
        $user = $this->userWithPluginManagerViewPermission();
        $this->actingAs($user);

        Livewire::test(PluginManager::class)
            ->assertActionHidden('install');
    }

    public function test_non_super_admin_cannot_mount_install_action_directly(): void
    {
        $user = $this->userWithPluginManagerViewPermission();
        $this->actingAs($user);

        Livewire::test(PluginManager::class)
            ->mountAction('install')
            ->assertActionNotMounted();
    }

    public function test_non_super_admin_with_page_permission_cannot_see_apply_update_action(): void
    {
        $user = $this->userWithPluginManagerViewPermission();
        $this->actingAs($user);

        Livewire::test(PluginManager::class)
            ->assertActionHidden('applyUpdate');
    }

    public function test_non_super_admin_cannot_mount_apply_update_action_directly(): void
    {
        $user = $this->userWithPluginManagerViewPermission();
        $this->actingAs($user);

        Livewire::test(PluginManager::class)
            ->mountAction('applyUpdate', arguments: ['vendor' => 'acme', 'slug' => 'widgets', 'name' => 'Widgets', 'latest_version' => '2.0.0'])
            ->assertActionNotMounted();
    }

    public function test_non_super_admin_cannot_invoke_one_click_update_directly(): void
    {
        $user = $this->userWithPluginManagerViewPermission();
        $this->actingAs($user);

        // Bypasses the applyUpdate Action wrapper entirely by calling the
        // public Livewire component method directly — proves the in-method
        // guard (not just the Action's authorize()) stops this path.
        Livewire::test(PluginManager::class)
            ->call('oneClickUpdate', 'acme', 'widgets')
            ->assertNotified(__('tallcms::ui.t_not_authorized'));
    }

    public function test_super_admin_can_see_install_and_apply_update_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Livewire::test(PluginManager::class)
            ->assertActionVisible('install')
            ->assertActionVisible('applyUpdate');
    }

    protected function userWithPluginManagerViewPermission(): User
    {
        $user = User::factory()->create();

        $role = Role::firstOrCreate(['name' => 'plugin_viewer', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'View:PluginManager', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole('plugin_viewer');

        return $user;
    }
}
