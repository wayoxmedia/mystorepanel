<?php

namespace App\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class Subscriber
 *
 * @method static create(array $array)
 * @method static insert(array[] $array)
 * @method static find(mixed $id)
 * @property int $id
 * @property string $address
 * @property string $address_type
 * @property string|null $user_ip
 * @property array|null $geo_location
 * @property bool $active
 * @property int|null $store_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Builder
 */
class Subscriber extends Model
{
  use HasFactory;

  /** @var string[] $fillable */
  protected $fillable = [
    'address',
    'address_type',
    'user_ip',
    'geo_location',
    'active',
    'store_id',
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

  /** @var mixed $id */
  private mixed $id;

  /** @var mixed $address */
  private mixed $address;

  // Setters and getters.

  /**
   * Get the value of the ID property.
   * @return integer
   */
  public function getId(): int
  {
    return $this->id;
  }

  /**
   * Set the value of the ID property.
   * @param  integer  $id
   * @return void
   */
  public function setId(int $id): void
  {
    $this->id = $id;
  }

  /**
   * Set the value of the address property.
   * @param  string  $address
   * @return void
   */
  public function setAddress(string $address): void
  {
    $this->address = $address;
  }

  /**
   * Get the value of the address property.
   * @return string
   */
  public function getAddress(): string
  {
    return $this->address;
  }
}
