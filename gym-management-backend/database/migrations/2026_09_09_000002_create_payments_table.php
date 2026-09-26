<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `payments` table.
 *
 * NOTE: this table was originally named `transactions` (Model: Transaction) —
 * renamed to `payments` (Model: Payment) so the DB/API naming matches the
 * "payment" domain terminology used elsewhere in the app. The frontend's
 * paymentService.js keeps its existing function/variable names (transaction,
 * transactions, etc.) unchanged to avoid a much larger UI-layer rename; only
 * the API paths it calls changed from /api/transactions to /api/payments.
 *
 * Mirrors the transaction data shape defined in:
 *   Frontend: src/data/mockData.js → initialTransactions
 *   Frontend: src/services/paymentService.js → recordPayment / markTransactionPaid
 *
 * Column mapping (JS camelCase → DB snake_case):
 *   memberId      → member_id (FK → members.id)
 *   dueDate       → due_date
 *   invoiceNumber → invoice_number
 *
 * Status enum (from frontend): Paid | Pending | Failed | Refunded | PartiallyRefunded
 * Type enum (from frontend):   OneTime | Recurring
 * Method enum (from frontend): Cash | Card | Bank Transfer | Online
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            // Payment method: Cash | Card | Bank Transfer | Online
            $table->string('method');
            // Payment type: OneTime | Recurring
            $table->string('type');
            // Payment status: Paid | Pending | Failed | Refunded | PartiallyRefunded
            $table->string('status')->default('Paid');
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('invoice_number')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
