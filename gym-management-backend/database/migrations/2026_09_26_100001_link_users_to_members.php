<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts now come from registrations: everyone signs up on /signup
 * (a `members` row with username + password), and an admin grants a role
 * in Setup > Users, which creates a `users` row pointing at that member.
 *
 * A linked user has no credentials or name of its own — they're read from
 * the member (see User::fullName()/email(), AuthController::login()) — so
 * those columns become nullable. The only users without a member are the
 * seeded bootstrap admin (and any accounts created before this change),
 * which still sign in with email + password.
 *
 * Deleting the member removes their staff access with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->unique()->after('id')
                ->constrained('members')->cascadeOnDelete();
            $table->string('full_name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
    }
};
