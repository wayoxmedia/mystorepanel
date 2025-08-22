<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Tenant extends Model
{
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
}
