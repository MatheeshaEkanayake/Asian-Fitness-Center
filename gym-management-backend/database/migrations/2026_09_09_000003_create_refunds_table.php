<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `refunds` table.
 *
 * NOTE: FK column renamed from `transaction_id` to `payment_id` to match the
 * `payments` table rename (was `transactions`) — see
 * 2026_09_09_000002_create_payments_table.php.
 *
 * Mirrors the refund data shape defined in:
 *   Frontend: src/data/mockData.js → initialRefunds
 *   Frontend: src/services/paymentService.js → issueRefund / listRefundsForTransaction
 *
 * Column mapping (JS camelCase → DB snake_case):
 *   transactionId → payment_id (FK → payments.id)
 *
 * When a refund amount >= payment amount, the payment status is
 * updated to 'Refunded'; otherwise 'PartiallyRefunded'.
 * This logic lives in: app/Http/Controllers/Api/RefundController.php → store()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->date('date');
            // Status: Processed (only value currently used by the frontend)
            $table->string('status')->default('Processed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
