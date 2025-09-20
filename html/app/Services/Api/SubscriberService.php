<?php

namespace App\Services\Api;

use App\Models\Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class SubscriberService
 */
class SubscriberService
{
  /**
   * Create a new class instance.
   */
  public function __construct()
  {
    //
  }

  /**
   * Store a newly created subscriber in our system.
   *
   * Required fields: iptAddress, selAddressType, store_id
   * @param  array  $data
   * @return Subscriber
   */
  public function store(array $data): Subscriber
  {
    if (is_string($data['geo_location'] ?? null)) {
      $data['geo_location'] = json_decode($data['geo_location'], true);
    }

    return Subscriber::query()->create([
      'tenant_id' => $data['tenant_id'],
      'address' => $data['iptAddress'],
      'address_type' => $data['selAddressType'],
      'user_ip' => $data['user_ip'] ?? null,
      'geo_location' => $data['geo_location'] ?? null,
      'active' => 1,
    ]);
  }

  /**
   * @param  array  $data
   * @return boolean
   */
  public function updateActiveStatus(array $data): bool
  {
    return Subscriber::query()
      ->where('address', $data['iptAddress'])
      ->where('address_type', $data['selAddressType'])
      ->update([
        'active' => 1,
      ]);
  }

  /**
   * @param  integer  $id
   * @return Subscriber|null
   */
  public function showById(int $id): ?Subscriber
  {
    $subscriber = Subscriber::find($id);

    if ($subscriber) {
      return $subscriber;
    } else {
      return null;
    }
  }

  /**
   * @param  string|null  $ip
   * @return mixed
   * @throws GuzzleException On HTTP request failure.
   */
  public function getGeolocationData(?string $ip): mixed
  {
    if (is_null($ip)) {
      return null;
    }

    $client = new Client();
    $response = $client->get('http://ip-api.com/json/'.$ip);

    return $response->getBody()->getContents();
  }
}
