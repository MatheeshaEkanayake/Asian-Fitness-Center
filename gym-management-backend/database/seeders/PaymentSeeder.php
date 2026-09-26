<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use App\Models\Payment;

/**
 * Seeds the `payments` table with the 10 mock transactions from the frontend.
 *
 * NOTE: renamed from TransactionSeeder to match the Transaction → Payment
 * model/table rename.
 *
 * Source: gym-management-frontend/src/data/mockData.js → initialTransactions
 *
 * Members are looked up by email to resolve member_id foreign keys,
 * since IDs are auto-incremented and may differ from the frontend's
 * 'mem_1001' style string IDs.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Helper: resolve member_id from email (avoids hard-coding auto-increment IDs)
        $memberId = fn(string $email) => Member::where('email', $email)->value('id');

        // Invoice counter mirrors paymentService.js nextInvoiceNumber() logic
        $transactions = [
            [
                'member_id'      => $memberId('dilani.fernando@example.com'),
                'amount'         => 45.00,
                'method'         => 'Card',
                'type'           => 'Recurring',
                'status'         => 'Paid',
                'date'           => '2026-08-11',
                'due_date'       => '2026-08-11',
                'invoice_number' => 'INV-3001',
                'notes'          => 'Monthly auto-charge.',
            ],
            [
                'member_id'      => $memberId('kasun.w@example.com'),
                'amount'         => 120.00,
                'method'         => 'Card',
                'type'           => 'Recurring',
                'status'         => 'Paid',
                'date'           => '2026-08-04',
                'due_date'       => '2026-08-04',
                'invoice_number' => 'INV-3002',
                'notes'          => 'Quarterly billing.',
            ],
            [
                'member_id'      => $memberId('ishara.perera@example.com'),
                'amount'         => 60.00,
                'method'         => 'Cash',
                'type'           => 'OneTime',
                'status'         => 'Paid',
                'date'           => '2026-08-19',
                'due_date'       => '2026-08-19',
                'invoice_number' => 'INV-3003',
                'notes'          => 'Drop-in month, front desk payment.',
            ],
            [
                'member_id'      => $memberId('tharindu.j@example.com'),
                'amount'         => 45.00,
                'method'         => 'Card',
                'type'           => 'Recurring',
                'status'         => 'Failed',
                'date'           => '2026-08-05',
                'due_date'       => '2026-08-05',
                'invoice_number' => 'INV-3004',
                'notes'          => 'Card declined — insufficient funds.',
            ],
            [
                'member_id'      => $memberId('nadeesha.r@example.com'),
                'amount'         => 480.00,
                'method'         => 'Bank Transfer',
                'type'           => 'Recurring',
                'status'         => 'Paid',
                'date'           => '2025-08-01',
                'due_date'       => '2025-08-01',
                'invoice_number' => 'INV-3005',
                'notes'          => 'Annual plan, paid in full.',
            ],
            [
                'member_id'      => $memberId('ashan.g@example.com'),
                'amount'         => 45.00,
                'method'         => 'Card',
                'type'           => 'Recurring',
                'status'         => 'Refunded',
                'date'           => '2026-07-17',
                'due_date'       => '2026-07-17',
                'invoice_number' => 'INV-3006',
                'notes'          => 'Refunded after cancellation request.',
            ],
            [
                'member_id'      => $memberId('hasini.silva@example.com'),
                'amount'         => 35.00,
                'method'         => 'Online',
                'type'           => 'OneTime',
                'status'         => 'Paid',
                'date'           => '2026-08-22',
                'due_date'       => '2026-08-22',
                'invoice_number' => 'INV-3007',
                'notes'          => 'Trial week upgrade.',
            ],
            [
                'member_id'      => $memberId('ruwan.bandara@example.com'),
                'amount'         => 120.00,
                'method'         => 'Card',
                'type'           => 'Recurring',
                'status'         => 'Pending',
                'date'           => '2026-09-09',
                'due_date'       => '2026-09-09',
                'invoice_number' => 'INV-3008',
                'notes'          => 'Upcoming quarterly charge.',
            ],
            [
                'member_id'      => $memberId('ishara.perera@example.com'),
                'amount'         => 25.00,
                'method'         => 'Cash',
                'type'           => 'OneTime',
                'status'         => 'Pending',
                'date'           => '2026-08-29',
                'due_date'       => '2026-09-05',
                'invoice_number' => 'INV-3009',
                'notes'          => 'Personal training add-on, invoiced.',
            ],
            [
                'member_id'      => $memberId('kasun.w@example.com'),
                'amount'         => 15.00,
                'method'         => 'Cash',
                'type'           => 'OneTime',
                'status'         => 'Paid',
                'date'           => '2026-07-28',
                'due_date'       => '2026-07-28',
                'invoice_number' => 'INV-3010',
                'notes'          => 'Guest pass for a friend.',
            ],
        ];

        foreach ($transactions as $data) {
            Payment::create($data);
        }
    }
}
