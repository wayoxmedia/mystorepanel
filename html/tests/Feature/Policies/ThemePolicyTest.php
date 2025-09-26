<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Database\Factories\ThemeFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ThemePolicyTest
 *
 * Purpose:
 * - Verify per-tenant authorization logic for ThemePolicy.
 * - Covers Platform SA, tenant_owner/admin (same/different tenant), and non-managers.
 *
 * Assumptions:
 * - User model implements:
 *   - isPlatformSuperAdmin(): bool
 *   - hasAnyRole(array $roles): bool
 *   - tenant_id (int|null) and status ('active'|'suspended'...)
 */
class ThemePolicyTest extends TestCase
{
  use RefreshDatabase;

  public function testPlatformSaHasFullAccess(): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();

    /** @var ThemeFactory $theme */
    $theme = Theme::factory();

    $theme = $theme->forTenant($t1)->create();

    /** @var User $sa */
    $sa = User::factory()
      ->asPlatformSuperAdmin()
      ->create([
        'status'    => 'active',
        'tenant_id' => null
      ]);

    $this->actingAs($sa);

    $this->assertTrue($sa->can('viewAny', Theme::class));
    $this->assertTrue($sa->can('view', $theme));
    $this->assertTrue($sa->can('create', Theme::class));
    $this->assertTrue($sa->can('update', $theme));
    $this->assertTrue($sa->can('delete', $theme));
  }

  /**
   * @return array<string, array{managerRole:string}>
   */
  public static function managerRolesProvider(): array
  {
    return [
      'tenant_owner' => ['managerRole' => 'tenant_owner'],
      'tenant_admin' => ['managerRole' => 'tenant_admin'],
      'invalid_role' => ['managerRole' => 'invalid_role'],
    ];
  }

  #[DataProvider('managerRolesProvider')]
  public function testTenantManagersCanCrudWithinTheirTenant(string $managerRole): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();

    /** @var ThemeFactory $theme */
    $theme = Theme::factory();

    $theme = $theme->forTenant($t1)->create();

    /** @var UserFactory $manager */
    $manager = User::factory();

    switch ($managerRole) {
      case 'tenant_owner':
        $manager = $manager->asTenantOwner();
        break;
      case 'tenant_admin':
        $manager = $manager->asTenantAdmin();
        break;
      default:
        $this->expectException('InvalidArgumentException');
        throw new InvalidArgumentException('Invalid manager role: ' . $managerRole);
    }

    $manager = $manager->create([
      'status'    => 'active',
      'tenant_id' => $t1->id, // manager belongs to t1
    ]);


    $this->actingAs($manager);

    // Pre-chequeo (si tu User requiere persistir el rol, ajusta tu factory/seed)
    $this->assertTrue(
      $manager->hasAnyRole([$managerRole]),
      'Expected user to have role: ' . $managerRole
    );

    $this->assertTrue($manager->can(
      'viewAny',
      Theme::class
    ));
    $this->assertTrue($manager->can(
        'view',
        $theme
      ));
    $this->assertTrue($manager->can(
      'create',
      Theme::class
      ));
    $this->assertTrue($manager->can(
      'update',
      $theme
      ));
    $this->assertTrue($manager->can(
      'delete',
      $theme
    ));
  }

  #[DataProvider('managerRolesProvider')]
  public function testTenantManagersCannotCrudOtherTenantsThemes(string $managerRole): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();
    /** @var Tenant $t2 */
    $t2 = Tenant::factory()->create();

    /** @var ThemeFactory $themeOfOther */
    $themeOfOther = Theme::factory()->forTenant($t2)->create();

    /** @var UserFactory $manager */
    $manager = User::factory();

    switch ($managerRole) {
      case 'tenant_owner':
        $manager = $manager->asTenantOwner();
        break;
      case 'tenant_admin':
        $manager = $manager->asTenantAdmin();
        break;
      default:
        $this->expectException('InvalidArgumentException');
        throw new InvalidArgumentException('Invalid manager role: ' . $managerRole);
    }

    $manager = $manager->create([
      'status'    => 'active',
      'tenant_id' => $t1->id, // manager belongs to t1
    ]);

    $this->actingAs($manager);
    $this->assertTrue(
      $manager->hasAnyRole([$managerRole]),
      'Expected user to have role: ' . $managerRole
    );

    // viewAny: allowed (manager can list),
    // but specific instance from other tenant should be denied
    $this->assertTrue($manager->can(
      'viewAny',
      Theme::class
    ));
    $this->assertFalse($manager->can(
      'view',
      $themeOfOther
    ));
    $this->assertFalse($manager->can(
      'update',
      $themeOfOther
    ));
    $this->assertFalse($manager->can(
      'delete',
      $themeOfOther
    ));
  }

  /**
   * Data provider for non-manager roles.
   * @return array<string, array{role:string}>
   */
  public static function nonManagersRolesProvider(): array
  {
    return [
      'tenant_editor' => ['role' => 'tenant_editor'],
      'tenant_viewer' => ['role' => 'tenant_viewer'],
      'invalid_role' => ['role' => 'invalid_role'],
    ];
  }

  /**
   * Test that non-manager roles cannot perform CRUD operations on themes.
   * @param string $role
   * @return void
   */
  #[DataProvider('nonManagersRolesProvider')]
  public function testNonManagersAreDeniedCrud($role): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();

    /** @var Theme $theme */
    $theme = Theme::factory()->forTenant($t1)->create();

    /** @var UserFactory $user */
    $user = User::factory();

    switch ($role) {
      case 'tenant_editor':
        $user = $user->asTenantEditor();
        break;
      case 'tenant_viewer':
        $user = $user->asTenantViewer();
        break;
      default:
        $this->expectException('InvalidArgumentException');
        throw new InvalidArgumentException('Invalid role: ' . $role);
    }

    $user = $user->create([
      'status'    => 'active',
      'tenant_id' => $t1->id,
    ]);

    $this->actingAs($user);

    $this->assertFalse($user->can(
      'create',
      Theme::class
    ));
    $this->assertFalse($user->can(
      'update',
      $theme
    ));
    $this->assertFalse($user->can(
      'delete',
      $theme
    ));
  }
}
