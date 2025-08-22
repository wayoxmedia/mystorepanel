<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a site in the multi-tenant architecture.
 * Each site is associated with a tenant and uses a specific template.
 */
class Site extends Model
{
  protected $fillable = ['tenant_id', 'template_id', 'domain', 'meta'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'meta' => 'array',
  ];

  /**
   * Tenant that owns this site.
   *
   * @return BelongsTo
   */
  public function tenant(): BelongsTo
  {
    return $this->belongsTo(Tenant::class);
  }

  /**
   * Template that this site uses.
   *
   * @return BelongsTo
   */
  public function template(): BelongsTo
  {
    return $this->belongsTo(Template::class);
  }
}
