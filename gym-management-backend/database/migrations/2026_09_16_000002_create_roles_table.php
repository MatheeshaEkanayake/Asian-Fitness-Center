<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `roles` table.
 *
 * Permissions are stored as a JSON array of string keys (e.g. "members.view",
 * "setup.manage") rather than a delimited numeric-id string, since there's no
 * shared build-time registry (TS enum, etc.) to justify numeric ids here —
 * see app/Support/Permissions.php for the canonical key list, mirrored by
 * src/config/navigationTree.js on the frontend.
 *
 * `is_admin` roles bypass all permission checks (see App\Models\User::isAdmin()
 * and App\Http\Middleware\CheckPermission). `is_system` protects the seeded
 * Administrator role from being deleted via the Setup > Roles screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
