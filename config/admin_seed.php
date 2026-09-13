<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Named admin account seeding
    |--------------------------------------------------------------------------
    |
    | Credentials for database/seeders/SntaksAdminUserSeeder.php. Kept out of
    | that file (and out of .env.example) since the seeder is tracked in git
    | but .env is not — a real password has no business in source control,
    | even one only ever passed to Hash-backed model casting.
    |
    */

    'email' => env('ADMIN_SEED_EMAIL'),

    'password' => env('ADMIN_SEED_PASSWORD'),

];
