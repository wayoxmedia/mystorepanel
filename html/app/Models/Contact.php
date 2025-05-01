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
     * @var string[] $fillable
     */
    protected $fillable = [
        'name',
        'email',
        'message',
    ];
}
