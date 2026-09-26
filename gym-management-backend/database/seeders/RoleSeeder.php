<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Seeds the Administrator role (is_admin bypasses all permission checks —
 * see User::isAdmin()) and a Front Desk role with a sane day-to-day subset.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['name' => 'Administrator'],
            [
                'description' => 'Full access to every area, including Setup.',
                'permissions' => Permissions::ALL,
                'is_admin'    => true,
                'is_system'   => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'Front Desk'],
            [
                'description' => 'Day-to-day member check-in, payments, and attendance. No Setup access.',
                'permissions' => [
                    'dashboard.view',
                    'members.view',
                    'members.edit',
                    'payments.view',
                    'payments.edit',
                    'attendance.view',
                    'attendance.edit',
                ],
                'is_admin'  => false,
                'is_system' => false,
            ]
        );
    }
}
