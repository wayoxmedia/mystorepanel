<?php

namespace App\Services\Api;

use App\Models\Subscriber;

class SubscriberService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

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
     * @param $data
     * @return mixed
     */
    public function updateActiveStatus($data)
    {
        return Subscriber::where('address', $data['iptAddress'])
            ->where('address_type', $data['selAddressType'])
            ->update([
                'active' => 1,
            ]);
    }

    /**
     * @param int $id
     * @return Subscriber|null
     */
    public function showById(int $id): ?Subscriber {
        $subscriber = Subscriber::find($id);

        if ($subscriber) {
            return $subscriber;
        } else {
            return null;
        }
    }
}
