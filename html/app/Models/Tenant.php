<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Tenant.
 * @property mixed $id
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $template_slug
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $primary_domain
 * @property mixed $allowed_origins
 * @property mixed $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @method static TenantFactory factory($count = null, $state = [])
 */
class Tenant extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'slug',
    'template_slug',
    'email',
    'phone',
    'primary_domain',
    'allowed_origins',
    'status',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'allowed_origins' => 'array',
  ];

  /**
   * Sites that belong to this tenant.
   *
   * @return HasMany
   */
  public function sites(): HasMany
  {
    return $this->hasMany(Site::class);
  }

  /**
   * Pages that belong to this tenant.
   *
   * @return HasMany
   */
  public function pages(): HasMany
  {
    return $this->hasMany(Page::class);
  }

  /**
   * Users that belong to this tenant.
   *
   * @return HasMany
   */
  public function users(): HasMany
  {
    return $this->hasMany(User::class);
  }

  /**
   * Get the user seat limit for the tenant.
   *
   * @return int
   */
  public function seatsLimit(): int
  {
    return (int) ($this->user_seat_limit ?? 2);
  }

  /**
   * Get the number of user seats currently used by the tenant.
   *
   * @return int
   */
  public function seatsUsed(): int
  {
    // Users that count towards the seat limit
    $usersCount = User::query()
      ->where('tenant_id', $this->id)
      ->whereIn('status', ['active', 'locked', 'suspended']) // count towards seat limit
      ->count();

    // Pending invitations that count towards the seat limit
    $pendingInvites = Invitation::query()
      ->where('tenant_id', $this->id)
      ->where('status', 'pending')
      ->count();

    return $usersCount + $pendingInvites;
  }

  /**
   * Check if the tenant has available user seats.
   *
   * @return bool
   */
  public function hasSeatAvailable(): bool
  {
    return $this->seatsUsed() < $this->seatsLimit();
  }
}
