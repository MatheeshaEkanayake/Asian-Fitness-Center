<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `refunds` table — the whole refund-issuing feature was removed
 * at your request (RefundController, Refund model, IssueRefundRequest,
 * RefundSeeder, the /api/payments/{id}/refunds routes, and the frontend's
 * RefundModal/"Issue refund" button all went with it, so nothing references
 * this table anymore). down() recreates the original schema from
 * 2026_09_09_000003_create_refunds_table.php for rollback, though any rows
 * that existed at drop time are gone for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('refunds');
    }

    public function down(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->date('date');
            $table->string('status')->default('Processed');
            $table->timestamps();
        });
    }
};
