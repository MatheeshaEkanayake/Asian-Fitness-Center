<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Main database seeder — orchestrates all seeders in dependency order.
 *
 * Run with: php artisan db:seed
 * Or combined with migration: php artisan migrate --seed
 *
 * Order matters: Branches before Roles/Users (users reference both),
 * Roles before Users (users reference a role). Members must exist before
 * Payments.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,     // 1 branch   (Main Branch)
            RoleSeeder::class,       // 2 roles    (Administrator, Front Desk)
            UserSeeder::class,       // 1 user     (seeded admin login)
            MemberSeeder::class,     // 8 members  (source: mockData.js → initialMembers)
            PaymentSeeder::class,    // 10 payments (source: mockData.js → initialTransactions)
            AttendanceSeeder::class, // 14 records  (source: mockData.js → initialAttendance)
            GymSettingsSeeder::class, // gym name, tagline, logo (Setup > Gym Settings)
        ]);
    }
}
