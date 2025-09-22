<?php

return [
  'invitations' => [
    'expires_hours' => 168, // invitation token validity, 7 days
    'cooldown_minutes' => 5,
    'auto_verify_on_accept' => env('INVITES_AUTO_VERIFY_ON_ACCEPT', false),
  ],

  'system_actor_id' => 0,

  'mail' => [
    'from' => [
      'address' => env('MAIL_FROM_ADDRESS', 'no-reply@mystorepanel.com'),
      'name'    => env('MAIL_FROM_NAME', 'My Store Panel'),
    ],

    // Envelope sender (bounce/return-path).
    'bounce' => env('MAIL_BOUNCE_ADDRESS'),

    // Libre: por si quieres ramificar lógica por proveedor (smtp|ses|mailgun|postmark|sendgrid|etc.)
    'provider' => env('MAIL_PROVIDER', 'smtp'),

    'headers' => [
      'X-System' => 'mystorepanel',
    ],

    'dispatch' => env('MAIL_DISPATCH', 'queue'), // 'queue' | 'send' | 'auto'
    'queue'    => env('MAIL_QUEUE', 'mail'),     // queue name when queueing
  ],

  // Seats configuration, currently, suspended users count as seats.
  // If you want to change this behavior, uncomment and set 'suspended_counts' to false.
  /*
  'seats' => [
    'suspended_counts' => true, // if suspended counts as occupied seats
  ],
  */
];
