<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per fingerprint/face enroll command seen on the device, copied
 * from VFT's command list (which only returns the latest 100) so a
 * person's enrollment history outlives it. Keyed by PIN, not member,
 * because that is what the device knows them by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vft_command_id')->unique();
            $table->string('device_sn');
            $table->string('pin')->index();
            $table->string('kind'); // face | finger
            $table->unsignedTinyInteger('finger_id')->nullable();
            $table->string('status'); // pending | registered | failed
            $table->integer('return_code')->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_enrollments');
    }
};
