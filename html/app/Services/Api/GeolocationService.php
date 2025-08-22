<?php

namespace App\Services\Api;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Class GeolocationService
 */
class GeolocationService
{
  /**
   * Create a new class instance.
   */
  public function __construct()
  {
    //
  }

  /**
   * Get geolocation data based on an IP.
   *
   * @param  string|null  $ip
   * @return string|null
   * @throws GuzzleException
   */
  public function getGeolocationByIp(?string $ip): ?string
  {
    if (is_null($ip)) {
      return null;
    }

    try {
      $client = new Client();
      $response = $client->get('http://ip-api.com/json/'.$ip);
      return $response->getBody()->getContents();
    } catch (Exception $e) {
      Log::error('getGeolocationByIp() API error: '.$e->getMessage());
      return null;
    }
  }
}
