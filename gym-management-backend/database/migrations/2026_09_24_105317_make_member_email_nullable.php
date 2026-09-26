<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email became optional on the member form (StoreMemberRequest now allows
 * nullable email — phone/WhatsApp are the required contact channels), but
 * the original create_members_table migration left the column NOT NULL.
 * Raw ALTER TABLE avoids adding doctrine/dbal just for one column's
 * nullability (Blueprint::change() requires it).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE members MODIFY email VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE members MODIFY email VARCHAR(255) NOT NULL');
    }
};
