<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `members` table.
 *
 * Mirrors the member data shape defined in:
 *   Frontend: src/data/mockData.js → initialMembers
 *   Frontend: src/services/memberService.js → createMember / updateMember
 *
 * Column mapping (JS camelCase → DB snake_case):
 *   fullName                → full_name
 *   dob                     → dob
 *   emergencyContactName    → emergency_contact_name
 *   emergencyContactPhone   → emergency_contact_phone
 *   joinDate                → join_date
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->date('join_date');
            // Mirrors frontend status enum: Active | Inactive | Suspended
            $table->string('status')->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
