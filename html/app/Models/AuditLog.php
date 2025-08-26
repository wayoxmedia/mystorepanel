<?php
// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AuditLog
 *
 * Represents an audit log entry in the application.
 *
 * @property int $id
 * @property int $actor_id The ID of the user who performed the action.
 * @property string $action The action performed (e.g., 'created', 'updated', 'deleted').
 * @property string $subject_type The type of subject affected by the action (e.g., 'User', 'Post').
 * @property int $subject_id The ID of the subject affected by the action.
 * @property array $meta Additional metadata related to the action.
 */
class AuditLog extends Model
{
  protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'meta'];
  protected $casts = ['meta' => 'array'];
}
