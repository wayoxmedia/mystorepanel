<?php

namespace App\Services\Api;

use App\Models\Subscriber;

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
     * @param array $data
     * @return Subscriber
     */
    public function store(array $data): Subscriber
    {
        return Subscriber::create([
            'address' => $data['iptAddress'],
            'address_type' => $data['selAddressType'],
            'user_ip' => $data['user_ip'] ?? null,
            'active' => 1,
        ]);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function updateActiveStatus(array $data): mixed
    {
        return Subscriber::where('address', $data['iptAddress'])
            ->where('address_type', $data['selAddressType'])
            ->update([
                'active' => 1,
            ]);
    }

    /**
     * @param integer $id
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
}
