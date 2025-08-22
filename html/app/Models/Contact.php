<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contact
 * @method static create(array $array)
 */
class Contact extends Model
{
  use HasFactory;

  // Define the table name if it's not the default "contacts"
  // protected $table = 'contacts';

  /**
   * The attributes that are mass assignable.
   *
   * @var string[] $fillable
   */
  protected $fillable = [
    'name',
    'email',
    'message',
    'store_id',
    'user_ip',
    'geo_location'
  ];

  /**
   * Field 'geo_location' is JSON, adding cast
   * so it can be handled as an array automatically.
   *
   * @var array<string, string> $casts
   */
  protected $casts = [
    'geo_location' => 'array',
  ];
}
