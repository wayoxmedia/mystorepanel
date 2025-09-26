<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Theme
 *
 * Purpose:
 * Per-tenant theme entity, holding presentational configuration (JSON) and
 * a unique slug within the tenant namespace.
 *
 * Assumptions:
 * - 'themes' table exists with (tenant_id, name, slug, status, description, config).
 * - Related Tenant exists.
 */
class Theme extends Model
{
  use HasFactory;
  use SoftDeletes;

  /** @var array<int, string> */
  protected $fillable = [
    'tenant_id',
    'name',
    'slug',
    'status',
    'description',
    'config',
  ];

  /** @var array<string, string> */
  protected $casts = [
    'config'     => 'array',
    'deleted_at' => 'datetime',
  ];

  /**
   * Owning tenant.
   *
   * @return BelongsTo<Tenant, Theme>
   */
  public function tenant(): BelongsTo
  {
    return $this->belongsTo(Tenant::class);
  }
}
