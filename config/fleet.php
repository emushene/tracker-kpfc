<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fleet Database Write Roles
    |--------------------------------------------------------------------------
    |
    | Only authenticated users whose 'role' matches one of these permitted roles
    | are allowed to execute mutation / write operations (POST, PUT, PATCH, DELETE)
    | against fleet database resources (vehicles, deployments, shop assignments, etc.).
    |
    */
    'write_roles' => array_map(
        'trim',
        explode(',', (string) env('FLEET_WRITE_ROLES', 'admin,manager,fleet_manager'))
    ),
];
