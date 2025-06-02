<?php

namespace App\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subscriber
 *
 * @method static create(array $array)
 * @method static insert(array[] $array)
 * @method static find(mixed $id)
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
     * @param integer $id
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Set the value of the address property.
     * @param string $address
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
