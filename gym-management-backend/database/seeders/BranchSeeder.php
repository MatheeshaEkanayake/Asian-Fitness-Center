<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Seeds the one default branch. The gym is single-location today; this row
 * exists so `users`/`members` have somewhere to point their branch_id and so
 * multi-location expansion later is just adding rows, not a migration.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(
            ['name' => 'Main Branch'],
            ['is_default' => true]
        );
    }
}
