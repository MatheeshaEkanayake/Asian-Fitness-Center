<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Members can be archived (soft-deleted) from the member list, and
 * members:purge-archived permanently removes anyone archived or not Active
 * for 6+ months. inactive_since records when status last left Active;
 * members already inactive start counting from today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->softDeletes();
            $table->timestamp('inactive_since')->nullable()->after('status');
        });

        DB::table('members')
            ->whereNotIn('status', ['Active', 'Guest'])
            ->update(['inactive_since' => now()]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('inactive_since');
        });
    }
};
