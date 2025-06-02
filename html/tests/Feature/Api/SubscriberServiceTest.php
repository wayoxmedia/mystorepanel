<?php

namespace Tests\Feature\Api;

use App\Models\Subscriber;
use Database\Seeders\TestingSuiteRecordsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Class SubscriberServiceTest.
 */
class SubscriberServiceTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        // $this->artisan('migrate');
        // $this->seed(TestingSuiteRecordsSeeder::class);
    }

    /**
     * Test the creation of a subscriber through the API.
     *
     * @param array       $data
     * @param boolean     $valid
     * @param string|null $expected
     * @return void
     */
    #[DataProvider('storeCreatesNewSubscriberProvider')]
    public function testSubscriberCreate(array $data, bool $valid, ?string $expected)
    {
        // Act: Make a POST request to the API endpoint
        $response = $this->postJson('/api/subscribe-form', $data);

        if ($valid) {
            // Assert: Verify the response and database
            $response->assertStatus(200)
                ->assertJson([
                    'message' => "Suscripción exitosa."
                ]);

            $this->assertDatabaseHas('subscribers', [
                'address' => $data['iptAddress'],
                'address_type' => $data['selAddressType'],
            ]);
        } else {
            // Assert: Verify the response and database
            $response->assertStatus(422);
            $this->assertEquals(
                $expected,
                $response->getOriginalContent()['message']
            );

            $this->assertDatabaseMissing('subscribers', [
                'address' => $data['iptAddress'],
                'address_type' => $data['selAddressType'],
            ]);
        }
    }

    /**
     * Test the update of a subscriber through the API.
     *
     * @param array       $data
     * @param string      $case
     * @param string|null $expected
     * @return void
     */
    #[DataProvider('storeUpdatesExistingSubscriberProvider')]
    public function testSubscriberUpdate(array $data, string $case, ?string $expected)
    {
        switch ($case) {
            case 'Existing Phone Active':
            case 'Existing Email Active':
            default:
                // Define new vars.
                $active = true;
                break;
            case 'Existing Phone Inactive':
            case 'Existing Email Inactive':
                $active = false;
                break;
        }

        // Arrange: Create a subscriber using the factory.
        $subscriber = Subscriber::factory()->create([
            'address' => $data['iptAddress'],
            'address_type' => $data['selAddressType'],
            'active' => $active,
            'id' => 1,
        ]);

        // Act: Make a GET request to verify the subscriber exists.
        $response = $this->getJson('/api/subscribers/' . $subscriber->id);

        // Assertions: Verify the response and database
        $response->assertStatus(200);
        $response->assertJson([
            'address' => $data['iptAddress'],
            'address_type' => $data['selAddressType'],
            'active' => $active,
        ]);
        $this->assertDatabaseHas('subscribers', [
            'address' => $data['iptAddress'],
            'address_type' => $data['selAddressType'],
            'active' => $active,
        ]);

        // Act: Make a POST request to the API endpoint
        $response = $this->postJson('/api/subscribe-form', $data);

        switch ($case) {
            case 'Existing Phone Active':
            case 'Existing Email Active':
            default:
                // Assert: Verify the response
                $response->assertStatus(422);
                $this->assertEquals(
                    $expected,
                    $response->getOriginalContent()['message']
                );
                break;

            case 'Existing Phone Inactive':
            case 'Existing Email Inactive':
                $response->assertStatus(200)
                    ->assertJson([
                        'message' => "Suscripción exitosa."
                    ]);
                break;
        }
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
                    'iptAddress' => 'fake@example.com',
                    'selAddressType' => 'e'
                ],
                'valid' => true,
                'expected' => null
            ],
            'Valid Phone Subscriber' => [
                'data' => [
                    'iptAddress' => '1234567890',
                    'selAddressType' => 'p'
                ],
                'valid' => true,
                'expected' => null
            ],
            'Invalid Email Subscriber' => [
                'data' => [
                    'iptAddress' => 'invalid-email',
                    'selAddressType' => 'e'
                ],
                'valid' => false,
                'expected' => 'Por favor use una dirección de email válida si selecciona la opción "Email".'
            ],
            'Invalid Phone Subscriber 10 chars' => [
                'data' => [
                    'iptAddress' => '12345678901',
                    'selAddressType' => 'p'
                ],
                'valid' => false,
                'expected' => 'La dirección no puede tener más de 10 caracteres.'
            ],
            'Invalid Phone Subscriber Non Numbers' => [
                'data' => [
                    'iptAddress' => 'invalid',
                    'selAddressType' => 'p'
                ],
                'valid' => false,
                'expected' => 'Por favor use solo números si selecciona la opción "Teléfono".'
            ],
            'Missing Phone Subscriber' => [
                'data' => [
                    'iptAddress' => '',
                    'selAddressType' => 'p'
                ],
                'valid' => false,
                'expected' => 'La dirección es requerida.'
            ],
            'Missing Email Subscriber' => [
                'data' => [
                    'iptAddress' => '',
                    'selAddressType' => 'e'
                ],
                'valid' => false,
                'expected' => 'La dirección es requerida.'
            ],
        ];
    }

    /**
     * Data provider for storeUpdatesExistingSubscriberProvider.
     * @return array[]
     */
    public static function storeUpdatesExistingSubscriberProvider(): array
    {
        return [
            'Existing Phone Subscriber Active' => [
                'data' => [
                    'iptAddress' => '1234567890',
                    'selAddressType' => 'p'
                ],
                'case' => 'Existing Phone Active',
                'expected' => 'Esta dirección ya esta registrada.'
            ],
            'Existing Phone Subscriber Inactive' => [
                'data' => [
                    'iptAddress' => '0987654321',
                    'selAddressType' => 'p'
                ],
                'case' => 'Existing Phone Inactive',
                'expected' => null
            ],
            'Existing Email Subscriber Active' => [
                'data' => [
                    'iptAddress' => 'nomail@fake.com',
                    'selAddressType' => 'e'
                ],
                'case' => 'Existing Email Active',
                'expected' => 'Esta dirección ya esta registrada.'
            ],
            'Existing Email Subscriber Inactive' => [
                'data' => [
                    'iptAddress' => 'yesmail@fake.com',
                    'selAddressType' => 'e'
                ],
                'case' => 'Existing Email Inactive',
                'expected' => null
            ],
        ];
    }
}
