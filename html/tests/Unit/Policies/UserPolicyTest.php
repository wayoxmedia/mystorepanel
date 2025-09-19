<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
  use RefreshDatabase;

  private UserPolicy $policy;

  protected function setUp(): void
  {
    parent::setUp();
    $this->policy = new UserPolicy();
  }

  public function testTenantOwnerCanCreateUserInSameTenant(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $owner  = User::factory()->verified()->active()->forTenant($tenant)->asTenantOwner()->create();
    $otherT = Tenant::factory()->active()->create();

    $this->assertTrue($this->policy->create($owner, $tenant));
    $this->assertFalse($this->policy->create($owner, $otherT));
  }

  public function testTenantAdminCanCreateUserInSameTenant(): void
  {
    $tenant = Tenant::factory()
      ->active()
      ->create();

    $admin  = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();
    $otherT = Tenant::factory()
      ->active()
      ->create();

    // Same-tenant creation allowed
    $this->assertTrue($this->policy->create($admin, $tenant));

    // Cross-tenant creation denied (admin belongs to $tenant, not $otherT)
    $this->assertFalse($this->policy->create($admin, $otherT));
  }

  public function testTenantEditorCannotCreateUser(): void
  {
    $tenant = Tenant::factory()
      ->active()
      ->create();
    $editor = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantEditor()
      ->create();

    $this->assertFalse($this->policy->create($editor, $tenant));
  }

  /** -----------------------------
   *  Update role / status
   * ------------------------------*/

  public function testTenantAdminCanUpdateRoleAndStatusForLowerPrivilegedUsers(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $admin   = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();
    $viewer  = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantViewer()
      ->create();
    $editor  = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantEditor()
      ->create();
    $owner   = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantOwner()
      ->create();

    // Allowed: viewer, editor
    $this->assertTrue($this->policy->updateRole($admin, $viewer));
    $this->assertTrue($this->policy->updateStatus($admin, $viewer));
    $this->assertTrue($this->policy->updateRole($admin, $editor));
    $this->assertTrue($this->policy->updateStatus($admin, $editor));

    // Not allowed: owner (and platform admin handled in another test)
    $this->assertFalse($this->policy->updateRole($admin, $owner));
    $this->assertFalse($this->policy->updateStatus($admin, $owner));
  }

  public function testTenantAdminCanUpdateRoleAndStatusWithinSameTenant(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $owner  = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantOwner()
      ->create();
    $admin  = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();
    $viewer = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantViewer()
      ->create();

    $this->assertTrue($this->policy->updateRole($owner, $admin));
    $this->assertTrue($this->policy->updateStatus($owner, $admin));
    $this->assertTrue($this->policy->updateRole($owner, $viewer));
    $this->assertTrue($this->policy->updateStatus($owner, $viewer));
  }

  public function testTenantAdminCannotManageUsersFromOtherTenant(): void
  {
    $t1 = Tenant::factory()->active()->create();
    $t2 = Tenant::factory()->active()->create();

    $admin  = User::factory()
      ->verified()
      ->active()
      ->forTenant($t1)
      ->asTenantAdmin()
      ->create();
    $owner  = User::factory()
      ->verified()
      ->active()
      ->forTenant($t1)
      ->asTenantOwner()
      ->create();
    $target = User::factory()
      ->verified()
      ->active()
      ->forTenant($t2)
      ->asTenantViewer()
      ->create();

    $this->assertFalse($this->policy->updateRole($admin, $target));
    $this->assertFalse($this->policy->updateStatus($admin, $target));
    $this->assertFalse($this->policy->updateRole($owner, $target));
    $this->assertFalse($this->policy->updateStatus($owner, $target));
  }

  public function testTenantAdminCannotManagePlatformAdmins(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $admin = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();
    $owner = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantOwner()
      ->create();
    $platRoot = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asPlatformSuperAdmin()
      ->create();

    $this->assertFalse($this->policy->updateRole($admin, $platRoot));
    $this->assertFalse($this->policy->updateStatus($admin, $platRoot));

    $this->assertFalse($this->policy->updateRole($owner, $platRoot));
    $this->assertFalse($this->policy->updateStatus($owner, $platRoot));
  }

  public function testImpersonationRules(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $owner = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantOwner()
      ->create();
    $admin = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();
    $viewer = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asTenantViewer()
      ->create();
    $root = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->asPlatformSuperAdmin()
      ->create();

    // Owner can impersonate same-tenant users (except platform admin and self)
    $this->assertTrue($this->policy->impersonate($owner, $admin));
    $this->assertTrue($this->policy->impersonate($owner, $viewer));
    $this->assertFalse($this->policy->impersonate($owner, $root));
    $this->assertFalse($this->policy->impersonate($owner, $owner));

    // Admin can impersonate lower-privileged users
    $this->assertTrue($this->policy->impersonate($admin, $viewer));
    $this->assertFalse($this->policy->impersonate($admin, $owner));
    $this->assertFalse($this->policy->impersonate($admin, $root));
    $this->assertFalse($this->policy->impersonate($admin, $admin));
  }
}
