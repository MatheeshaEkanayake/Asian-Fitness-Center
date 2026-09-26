<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `settings` table — a single generic key-value store shared by
 * Setup > Gym Settings (keys prefixed "gym.") and Setup > Email Settings
 * (keys prefixed "mail."), rather than two bespoke singleton tables.
 * See App\Models\Setting for the group()/get()/set() helpers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
