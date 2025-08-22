<?php

namespace Database\Seeders;

use App\Models\Subscriber;
use Illuminate\Database\Seeder;

class TestingSuiteRecordsSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    /**
     * You can use this seeder to create test records for your testing suite.
     * For example, you can create a subscriber record for testing purposes.
     * Subscriber::create([
     *      'address' => 'nomail@gmail.com',
     *      'address_type' => 'e',
     *      'user_ip' => '127.0.0.1',
     *      'active' => true,
     * ]);
     */

    Subscriber::insert([
      [
        'address' => 'user1@example.com',
        'address_type' => 'e',
        'user_ip' => '127.0.0.1',
        'active' => true,
      ],
      [
        'address' => 'user2@example.com',
        'address_type' => 'e',
        'user_ip' => '127.0.0.2',
        'active' => false,
      ],
      [
        'address' => '1234567890',
        'address_type' => 'p',
        'user_ip' => '127.0.0.3',
        'active' => true,
      ],
      [
        'address' => '0987654321',
        'address_type' => 'p',
        'user_ip' => '127.0.0.4',
        'active' => false,
      ],
    ]);
  }
}
