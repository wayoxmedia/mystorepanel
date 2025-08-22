<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class User
 * @property mixed $tenant_id
 * @property mixed|string $role
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
   */
  public function tenant(): BelongsTo
  {
    return $this->belongsTo(Tenant::class);
  }
}
