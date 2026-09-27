<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff check-ins from the door device, kept apart from member attendance
 * (Members › Staff Attendance). One row per staff account per day: first
 * punch = check-in, last = check-out. Same time format as `attendance`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('staff_name');
            $table->date('date');
            $table->string('check_in_time')->nullable();
            $table->string('check_out_time')->nullable();
            $table->string('status')->default('Present');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');
    }
};
