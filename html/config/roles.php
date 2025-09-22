<?php

/**
 * Define the mapping of role IDs (DB) to role slug.
 * Adjust the mapping according to your application's roles.
 */
return [
  'role_map' => [
    1 => 'platform_super_admin',
    2 => 'tenant_owner',
    3 => 'tenant_admin',
    4 => 'tenant_editor',
    5 => 'tenant_viewer',
  ],
];
