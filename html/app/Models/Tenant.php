<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class Tenant.
 *
 * Purpose:
 * Core tenant model for MSP. Soft deletes are enabled to preserve history and
 * avoid cascading hard-deletes in early development. Provides relationships to child entities,
 *  convenience scopes, and small domain helpers (suspend/resume, seat checks).
 *
 * Assumptions:
 * - Table 'tenants' has a nullable 'deleted_at' TIMESTAMP column.
 * - Related models reference tenant_id with ON DELETE CASCADE or RESTRICT as needed.
 * - Soft deletes are enabled (deleted_at column exists).
 * - Foreign keys to tenants.id are present on related tables (users, sites, themes,
 *   subscribers, contacts, pages).
 * - templates table exists; tenants.template_id is nullable.
 *
 * Notes:
 *  - template_slug is deprecated and intentionally NOT part of $fillable.
 *  - Expose template slug via an accessor derived from the relationship when needed.
 *
 * @property mixed $id
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $template
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $primary_domain
 * @property mixed $allowed_origins
 * @property mixed $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $trial_ends_at
 * @property int|null $template_id
 * @property int|null $user_seat_limit
 * @property string|null $billing_email
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $plan
 */
class Tenant extends Model
{
  use HasFactory;
  use SoftDeletes;

  protected $fillable = [
    'name',
    'slug',
    'status',
    'template_id',
    'user_seat_limit',
    'billing_email',
    'timezone',
    'locale',
    'plan',
    'trial_ends_at',
    'primary_domain',
    'allowed_origins',
    'email',
    'phone',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'trial_ends_at' => 'datetime',
    'deleted_at'    => 'datetime',
    'allowed_origins' => 'array',
  ];

  /**
   * Sites of this tenant.
   *
   * @return HasMany<Site>
   */
  public function sites(): HasMany
  {
    return $this->hasMany(Site::class);
  }

  /**
   * Pages scoped to this tenant.
   *
   * @return HasMany<Page>
   */
  public function pages(): HasMany
  {
    return $this->hasMany(Page::class);
  }

  /**
   * Users belonging to this tenant.
   *
   * @return HasMany<User>
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

  // ------------------------------------------------------------------------------
  // Relationships
  // ------------------------------------------------------------------------------

  /**
   * Template used by this tenant (nullable).
   *
   * @return BelongsTo<Template, Tenant>
   */
  public function template(): BelongsTo
  {
    return $this->belongsTo(Template::class);
  }


  /**
   * Themes of this tenant.
   *
   * @return HasMany<Theme>
   */
  public function themes(): HasMany
  {
    return $this->hasMany(Theme::class);
  }

  /**
   * Subscribers scoped to this tenant.
   *
   * @return HasMany<Subscriber>
   */
  public function subscribers(): HasMany
  {
    return $this->hasMany(Subscriber::class);
  }

  /**
   * Contacts scoped to this tenant.
   *
   * @return HasMany<Contact>
   */
  public function contacts(): HasMany
  {
    return $this->hasMany(Contact::class);
  }

  // ------------------------------------------------------------------------------
  // Accessors (derived / convenience)
  // ------------------------------------------------------------------------------

  /**
   * Derived template slug (read-only), returns null if no template set.
   *
   * @return string|null
   */
  public function getTemplateSlugAttribute(): ?string
  {
    return $this->template?->slug;
  }

  // ------------------------------------------------------------------------------
  // Scopes
  // ------------------------------------------------------------------------------

  /**
   * Scope: only active tenants.
   *
   * @param  Builder<Tenant>  $query
   * @return Builder<Tenant>
   */
  public function scopeActive(Builder $query): Builder
  {
    return $query->where('status', 'active');
  }

  /**
   * Scope: only suspended tenants.
   *
   * @param  Builder<Tenant>  $query
   * @return Builder<Tenant>
   */
  public function scopeSuspended(Builder $query): Builder
  {
    return $query->where('status', 'suspended');
  }

  /**
   * Scope: simple search by name, slug or primary_domain.
   *
   * @param  Builder<Tenant>  $query
   * @param  string|null      $term
   * @return Builder<Tenant>
   */
  public function scopeSearch(Builder $query, ?string $term): Builder
  {
    if (! $term) {
      return $query;
    }

    $like = '%' . $term . '%';

    return $query->where(function (Builder $q) use ($like): void {
      $q->where('name', 'like', $like)
        ->orWhere('slug', 'like', $like)
        ->orWhere('primary_domain', 'like', $like);
    });
  }

  // ------------------------------------------------------------------------------
  // Domain helpers
  // ------------------------------------------------------------------------------

  /**
   * Suspend tenant (idempotent).
   *
   * @return bool
   */
  public function suspend(): bool
  {
    if ($this->status === 'suspended') {
      return true;
    }

    $this->status = 'suspended';

    return $this->save();
  }

  /**
   * Resume tenant (idempotent, resumes to 'active').
   *
   * @return bool
   */
  public function resume(): bool
  {
    if ($this->status === 'active') {
      return true;
    }

    $this->status = 'active';

    return $this->save();
  }

  /**
   * Count currently active users of this tenant.
   *
   * @return int
   */
  public function activeUsersCount(): int
  {
    return $this->users()
      ->where('status', 'active')
      ->count();
  }

  /**
   * Whether the seat limit can be set to the given value without violating
   * current active users.
   *
   * @param  int  $newLimit
   * @return bool
   */
  public function canSetSeatLimit(int $newLimit): bool
  {
    return $newLimit >= $this->activeUsersCount();
  }
}
