<?php

namespace App\Services\Api;

use App\Models\Contact;

/**
 * Class ContactService
 */
class ContactService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * @param array $data
     * @return Contact
     */
    public function store(array $data): Contact
    {
        // Store the data in the database
        return Contact::create([
            'name' => $data['iptName'],
            'email' => $data['iptEmail'],
            'message' => $data['iptMessage'],
        ]);
    }
}
