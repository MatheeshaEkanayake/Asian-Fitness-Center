<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links members and payments to a payment plan.
 *
 * - members.payment_plan_id  — the plan the member is signed up on (set on
 *   the member form).
 * - payments.payment_plan_id — the plan a payment was made for, recorded at
 *   payment time so history stays correct if the member later switches plan.
 *
 * Both nullable: members/payments created before plans existed have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('payment_plan_id')->nullable()->after('branch_id')->constrained('payment_plans')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_plan_id')->nullable()->after('member_id')->constrained('payment_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_plan_id');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_plan_id');
        });
    }
};
