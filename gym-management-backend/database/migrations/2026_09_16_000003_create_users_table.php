<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `users` table — the app's real staff/login table (this project
 * has no separate stock Laravel `users` migration; this replaces it).
 *
 * Accounts are admin-created only (Setup > Users) — there is no public
 * self-registration route and no email-token password reset flow. Admins
 * reset a user's password directly via UserController::resetPassword().
 *
 * `role_id` is a proper FK (unlike loosely-coupled role-name matching),
 * nullable so a deleted role doesn't cascade-delete the user account.
 * `branch_id` is nullable for the same reason and because not every account
 * needs to be scoped to a single branch (e.g. an admin overseeing all).
 *
 * "Delete" is implemented as a status flip to `Inactive` (destroy() never
 * hard-deletes), mirroring Member::destroy()'s history-preserving pattern —
 * here it preserves the account's `login_activities` audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            // Active | Inactive
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
