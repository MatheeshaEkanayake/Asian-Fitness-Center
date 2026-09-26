<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `payment_plans` table — the membership plans the gym offers
 * (e.g. Daily, 1 month, 3 months), managed from Setup > Payment Plans.
 *
 * Several plans can be active at once. Plans can be edited and retired
 * (is_active = false) but not deleted, since members and payments
 * reference them.
 *
 * Frontend: src/pages/setup/PaymentPlansPage.jsx, PaymentPlanFormPage.jsx
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            // Daily | Monthly
            $table->string('type');
            // Number of months covered; null for Daily plans.
            $table->unsignedSmallInteger('months')->nullable();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
