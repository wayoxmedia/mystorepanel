<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Invitation
 *
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
 * @property Role|null $role
 * @property Tenant $tenant
 * @property User $inviter
 * @method static InvitationFactory factory($count = null, $state = [])
 */
class Invitation extends Model
{

  use HasFactory;

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

  /**
   * @return bool
   */
  public function isExpired(): bool
  {
    return $this->status === 'expired'
      || ($this->expires_at && $this->expires_at->isPast());
  }

  /**
   * @return bool
   */
  public function isPending(): bool
  {
    return $this->status === 'pending';
  }
}
