<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every door punch received from VFT (webhook, see VftWebhookController), kept raw.
 * The unique key is what stops a re-sync from importing the same punch
 * twice. Punches are then summarised into `attendance` (members) or
 * `staff_attendance` (staff). punched_at is device local time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_punches', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn');
            $table->string('pin');
            $table->dateTime('punched_at');
            $table->string('event_type')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['device_sn', 'pin', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_punches');
    }
};
