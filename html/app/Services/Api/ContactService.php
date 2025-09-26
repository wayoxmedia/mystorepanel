<?php

namespace App\Services\Api;

use App\Models\Contact;
use Exception;
use Illuminate\Support\Facades\Log;

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
   * @param  array  $data
   * @return boolean
   * @throws Exception
   */
  public function store(array $data): bool
  {
    if (is_string($data['geo_location'])) {
      $data['geo_location'] = json_decode($data['geo_location'], true);
    }

    try {
      Contact::create([
        'store_id' => $data['store_id'],
        'name' => $data['iptName'],
        'email' => $data['iptEmail'],
        'message' => $data['iptMessage'],
        'user_ip' => $data['user_ip'],
        'geo_location' => $data['geo_location'] ?? null, // Optional, can be null if API Failed.
      ]);
      return true;
    } catch (Exception $e) {
      // Log the error and rethrow or handle it as needed.
      Log::error('Failed to store contact information: '.$e->getMessage());
      return false;
    }
  }
}
