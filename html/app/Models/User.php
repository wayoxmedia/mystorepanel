<?php

namespace App\Models;

use App\Models\Concerns\HasTenantRoles;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class User
 * @property mixed $id
 * @property mixed $tenant_id
 * @property mixed $role_id
 * @property mixed $name
 * @property mixed $email
 * @property mixed $password
 * @property mixed $remember_token
 * @property mixed $email_verified_at
 * @property Tenant|null $tenant
 * @property Role $role
 * @property string $status
 */
class User extends Authenticatable implements JWTSubject, MustVerifyEmailContract
{
  use MustVerifyEmail, HasTenantRoles;

  /** @use HasFactory<UserFactory> */
  use HasFactory, Notifiable;

  /** @var array $fillable */
  protected $fillable = [
    'name',
    'email',
    'password',
    'tenant_id',
    'status',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var list<string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  /**
   * The attributes that should be appended to the model's array form.
   *
   * @return int
   */
  public function getJWTIdentifier(): int
  {
    return $this->getKey();
  }

  /**
   * Get the custom claims for the JWT.
   *
   * @return array<string, mixed>
   */
  public function getJWTCustomClaims(): array
  {
    return [];
  }

  /**
   * The tenant this user belongs to (single-tenant per user).
   * @return BelongsTo<Tenant>
   * @throws ModelNotFoundException
   */
  public function tenant(): BelongsTo
  {
    return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
  }

  /**
   * The role this user has.
   *
   * @return BelongsTo
   */
  public function role(): BelongsTo
  {
    return $this->belongsTo(Role::class, 'role_id', 'id');
  }

  /**
   * Role helpers.
   *
   * Check if the user has a specific role by slug.
   * @param  string $slug
   * @return bool
   * @throws ModelNotFoundException
   */
  public function hasRole(string $slug): bool
  {
    $role = $this->getRoleSlug();
    return $role === $slug;
  }

  /**
   * Check if the user has any of the specified roles by slugs.
   *
   * @param  array $slugs
   * @return bool
   */
  public function hasAnyRole(array $slugs): bool
  {
    $role = $this->getRoleSlug();
    return in_array($role, $slugs, true);
  }

  /**
   * Get the role slug for the user.
   * @return string
   */
  private function getRoleSlug(): string
  {
    return $this->role()->value('slug') ?? '';
  }

  /**
   * Get the role slug for the user.
   * @return string
   */
  private function getRoleName(): string
  {
    return $this->role()->value('name') ?? '';
  }

  /**
   * Check if the user is a Platform Super Admin.
   *
   * @return bool
   */
  public function isPlatformSuperAdmin(): bool
  {
    return $this->hasRole('platform_super_admin');
  }

  /**
   * Tenant scope helper.
   *
   * @param  $query
   * @param  int|null $tenantId
   * @return Builder
   * @throws ModelNotFoundException
   */
  public function scopeForTenant($query, ?int $tenantId): Builder
  {
    return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
  }

  /**
   * Boot method to handle model events.
   *
   * Never allow setting tenant_id for platform_super_admin users.
   * @return void
   */
  protected static function booted(): void
  {
    static::saving(function (User $user) {
      // asume que tu trait HasTenantRoles ya está en el modelo
      if ($user->getRoleSlug() === 'platform_super_admin') {
        $user->tenant_id = null;
      }
    });
  }
}
