<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `attendance` table.
 *
 * Mirrors the attendance data shape defined in:
 *   Frontend: src/data/mockData.js → initialAttendance
 *   Frontend: src/pages/members/MemberAttendencePage.jsx
 *
 * Column mapping (JS camelCase → DB snake_case):
 *   memberId      → member_id (FK → members.id)
 *   memberName    → member_name  (denormalised for display, mirrors mockData shape)
 *   checkInTime   → check_in_time
 *   checkOutTime  → check_out_time
 *
 * Status enum (from frontend): Present | Late | Absent
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // Denormalised name kept for quick display without a join (mirrors mockData)
            $table->string('member_name');
            $table->string('email');
            $table->date('date');
            // '—' stored as null for absent records (frontend shows '—')
            $table->string('check_in_time')->nullable();
            $table->string('check_out_time')->nullable();
            // Status: Present | Late | Absent
            $table->string('status')->default('Present');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
