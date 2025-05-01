<?php

namespace Tests\Unit\Api;

use App\Services\Api\SubscriberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Class SubscriberServiceTest.
 */
class SubscriberServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the store method of SubscriberService.
     *
     * @param array $data
     * @return void
     */
    #[DataProvider('storeCreatesNewSubscriberProvider')]
    public function testStoreCreatesNewSubscriber(array $data)
    {
        // Call the service method
        $service = new SubscriberService();
        $result = $service->store($data);

        // Assert: Verify the database and result
        $this->assertNotNull($result->id, 'Subscriber was not saved to the database.');
        $this->assertDatabaseHas('subscribers', [
            'address' => $data['iptAddress'],
            'address_type' => $data['selAddressType'],
        ]);

        // Assert: Verify the result
        // $this->assertInstanceOf(Subscriber::class, $result);
        $this->assertEquals($data['iptAddress'], $result->address);
        $this->assertEquals($data['selAddressType'], $result->address_type);
        // dd(\DB::getQueryLog());
    }

    /****************
     * Data Providers
     ***************/

    /**
     * Data provider for store_creates_a_new_subscriber_provider.
     * @return array
     */
    public static function storeCreatesNewSubscriberProvider(): array
    {
        return [
            'Valid Email Subscriber' => [
                'data' => [
                    'iptAddress' => 'test@example.com',
                    'selAddressType' => 'e'
                ]
            ],
            'Valid Phone Subscriber' => [
                'data' => [
                    'iptAddress' => '+1234567890',
                    'selAddressType' => 'p'
                ]
            ]
        ];
    }
}
