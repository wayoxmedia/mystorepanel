<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property mixed $id
 */
class Tenant extends Model
{
  protected $fillable = ['name', 'slug', 'email', 'phone'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
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
