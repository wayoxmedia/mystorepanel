<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Role
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $scope
 */
class Role extends Model
{
  /** @var string[] */
  protected $fillable = ['name', 'slug', 'scope'];

  /**
   * @return BelongsToMany
   */
  public function users(): BelongsToMany
  {
    return $this->belongsToMany(User::class)->withTimestamps();
  }
}
