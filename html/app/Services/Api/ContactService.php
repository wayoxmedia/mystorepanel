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
        if (is_string($data['geo_location'])) {
            $data['geo_location'] = json_decode($data['geo_location'], true);
        }

        // Store the data in the database
        return Contact::create([
            'store_id' => $data['store_id'],
            'name' => $data['iptName'],
            'email' => $data['iptEmail'],
            'message' => $data['iptMessage'],
            'user_ip' => $data['user_ip'],
            'geo_location' => $data['geo_location'] ?? null, // Optional, can be null if API Failed.
        ]);
    }
}
