<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subscribe
 * @property string $address
 * @property string $address_type
 * @property int $id
 * @method static where(string $string, $value)
 * @method static create(array $array)
 * @method static insert(array[] $array)
 * @method static find(mixed $id)
 */
class Subscriber extends Model
{
    use HasFactory;

    // Specify which fields can be mass-assigned
    protected $fillable = [
        'address',
        'address_type',
        'user_ip',
    ];
}
