<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template Model
 *
 * Purpose:
 * Represents a template definition (structure/blueprint) in the system.
 *
 * Attributes:
 * - id: Primary key.
 * - slug: Unique identifier for the template.
 * - name: Human-readable name of the template.
 * - is_active: Indicates if the template is active.
 * - version: Version of the template.
 * - description: Optional description of the template.
 * - img_url: Optional URL to an image representing the template.
 *
 * Relationships:
 * - sites: One-to-many relationship with Site model.
 */
class Template extends Model
{
  use HasFactory;

  // Mass-assignable attributes
  protected $fillable = [
    'slug',
    'name',
    'is_active',
    'version',
    'description',
    'img_url',
    ];

  /** @var array<string, string> */
  protected $casts = [
    'is_active' => 'bool',
  ];

  /**
   * Get the sites associated with the template.
   *
   * @return HasMany
   */
  public function sites(): HasMany
  {
    return $this->hasMany(Site::class);
  }
}
