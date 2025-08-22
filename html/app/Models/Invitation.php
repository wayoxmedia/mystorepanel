<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Invitation
 * @property mixed $email
 * @property mixed $tenant_id
 * @property mixed $role_id
 * @property mixed $token
 * @property mixed $expires_at
 * @property mixed $status
 * @property mixed $invited_by
 */
class Invitation extends Model
{
  /** @var string[] */
  protected $fillable = [
    'email', 'tenant_id', 'role_id', 'token', 'expires_at', 'status', 'invited_by',
  ];

  /** @var string[] */
  protected $casts = [
    'expires_at' => 'datetime',
  ];

  /**
   * @return BelongsTo
   */
  public function tenant(): BelongsTo
  {
    return $this->belongsTo(Tenant::class);
  }

  /**
   * @return BelongsTo
   */
  public function role(): BelongsTo
  {
    return $this->belongsTo(Role::class);
  }

  /**
   * @return BelongsTo
   */
  public function inviter(): BelongsTo
  {
    return $this->belongsTo(User::class, 'invited_by');
  }
}
