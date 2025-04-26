<?php

namespace Tests\Unit\Api;

use App\Models\Subscriber;
use App\Services\Api\SubscriberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_new_subscriber()
    {
        // Arrange: Mock the Subscriber model
        $data = [
            'iptAddress' => 'test@example.com',
            'selAddressType' => 'e',
        ];

        $subscriberMock = Mockery::mock(Subscriber::class);
        $subscriberMock->shouldReceive('create')
            ->once()
            ->with([
                'address' => $data['iptAddress'],
                'address_type' => $data['iptAddressType'],
            ])
            ->andReturn(new Subscriber([
                'address' => $data['iptAddress'],
                'address_type' => $data['iptAddressType'],
            ]));

        $this->app->instance(Subscriber::class, $subscriberMock);

        // Act: Call the service method
        $service = new SubscriberService();
        $result = $service->store($data);

        // Assert: Verify the result
        $this->assertInstanceOf(Subscriber::class, $result);
        $this->assertEquals($data['iptAddress'], $result->address);
        $this->assertEquals($data['iptAddressType'], $result->address_type);
    }
}
