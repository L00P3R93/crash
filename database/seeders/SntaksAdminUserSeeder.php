<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A specific, named admin account (Sntaks Solutions Ltd) — separate from the
 * generic admin@example.com dev bootstrap in AdminUserSeeder. Reads its
 * credentials from .env rather than hardcoding them here: this file is
 * tracked in git, and a real password has no business sitting in source
 * control history even hashed-on-save, since the plaintext passed to
 * `run()` would otherwise be visible to anyone with repo access forever.
 *
 * Run explicitly, not part of the default `db:seed` chain — see
 * DatabaseSeeder for the seeders that do run automatically.
 *
 *     php artisan db:seed --class=SntaksAdminUserSeeder
 */
class SntaksAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin_seed.email');
        $password = config('admin_seed.password');

        if (! $email || ! $password) {
            throw new RuntimeException(
                'ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD must both be set in .env before running this seeder.'
            );
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Sntaks Solutions',
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
