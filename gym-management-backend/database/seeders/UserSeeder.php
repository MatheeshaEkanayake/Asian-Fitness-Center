<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one Administrator login. Critical: accounts are admin-created only
 * (no public self-registration), so this is the only way in on a fresh
 * install — use it to log in and create further Setup > Users accounts.
 *
 * Change this password immediately in a real deployment.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $mainBranch = Branch::where('is_default', true)->first();

        User::firstOrCreate(
            ['email' => 'admin@asianfitnessgym.test'],
            [
                'full_name' => 'Gym Administrator',
                'password'  => Hash::make('ChangeMe123!'),
                'role_id'   => $adminRole->id,
                'branch_id' => $mainBranch?->id,
                'status'    => 'Active',
            ]
        );
    }
}
