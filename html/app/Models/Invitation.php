<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Invitation
 * @property mixed $id
 * @property mixed $email
 * @property mixed $tenant_id
 * @property mixed $role_id
 * @property mixed $token
 * @property mixed $expires_at
 * @property mixed $status
 * @property mixed $invited_by
 * @property mixed $last_sent_at
 * @property mixed $send_count
 */
class Invitation extends Model
{
  /** @var string */
  protected $table = 'invitations';

  /** @var string[] */
  protected $fillable = [
    'email',
    'tenant_id',
    'role_id',
    'token',
    'expires_at',
    'status',
    'invited_by',
    'last_sent_at',
    'send_count',
  ];

  /** @var string[] */
  protected $casts = [
    'expires_at'   => 'datetime',
    'last_sent_at' => 'datetime',
    'send_count'   => 'integer',
    'created_at'   => 'datetime',
    'updated_at'   => 'datetime',
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

  /** Helpers */
  public function isExpired(): bool
  {
    return $this->status === 'expired'
      || ($this->expires_at && $this->expires_at->isPast());
  }

  public function isPending(): bool
  {
    return $this->status === 'pending';
  }
}
