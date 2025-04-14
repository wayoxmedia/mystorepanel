<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contact
 */
class Contact extends
    Model
{
    use HasFactory;

    // Define the table name if it's not the default "contacts"
    // protected $table = 'contacts';

    // Specify which fields can be mass-assigned
    protected $fillable = [
        'name',
        'email',
        'message',
    ];
}
