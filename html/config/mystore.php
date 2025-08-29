<?php

return [
  'invitations' => [
    'expires_hours' => 168, // invitation token validity, 7 days
    'cooldown_minutes' => 5,
  ],

  'system_actor_id' => 0,

  // Seats configuration, currently, suspended users count as seats.
  // If you want to change this behavior, uncomment and set 'suspended_counts' to false.
  /*
  'seats' => [
    'suspended_counts' => true, // if suspended counts as occupied seats
  ],
  */
];
