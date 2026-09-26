<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `branches` table.
 *
 * The gym is single-location today, but staff (`users`) and members are kept
 * branch-aware from the start so expanding to multiple locations later is a
 * matter of adding rows and a Setup screen, not a schema migration. Seeded
 * with one default "Main Branch" row — see database/seeders/BranchSeeder.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
