<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('seed.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set SEED_USER_PASSWORD before seeding.');
        }

        User::firstOrCreate(
            ['email' => config('seed.email')],
            ['name' => config('seed.name'), 'password' => $password],
        );
    }
}
