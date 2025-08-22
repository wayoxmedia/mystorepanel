<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class User
 * @property mixed $id
 * @property mixed $tenant_id
 * @property mixed $role
 * @property mixed $name
 * @property mixed $email
 * @property mixed $password
 * @property mixed $remember_token
 * @property mixed $email_verified_at
 * @property Tenant|null $tenant
 * @property Role[] $roles
 * @property string $status
 */
class User extends Authenticatable implements JWTSubject
{
  /** @use HasFactory<UserFactory> */
  use HasFactory, Notifiable;

  /** @var array $fillable */
  protected $fillable = [
    'name',
    'email',
    'password',
    'tenant_id',
    'role',
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
    return $this->belongsTo(Tenant::class);
  }

  /**
   * The roles this user has. (multi-tenant per user).
   *
   * @return BelongsToMany
   */
  public function roles(): BelongsToMany
  {
    return $this->belongsToMany(Role::class)->withTimestamps();
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
    return $this->roles->contains(fn ($r) => $r->slug === $slug);
  }

  /**
   * Check if the user has any of the specified roles by slugs.
   *
   * @param  array $slugs
   * @return bool
   */
  public function hasAnyRole(array $slugs): bool
  {
    return $this->roles->contains(fn ($r) => in_array($r->slug, $slugs, true));
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
}
