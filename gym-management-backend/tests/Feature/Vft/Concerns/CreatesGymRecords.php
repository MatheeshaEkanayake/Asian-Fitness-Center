<?php

namespace Tests\Feature\Vft\Concerns;

use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\User;

trait CreatesGymRecords
{
    private int $invoice = 5000;

    protected function member(array $attributes = []): Member
    {
        return Member::create($attributes + [
            'full_name' => 'Test Person',
            'phone' => '0770000000',
            'join_date' => '2026-01-01',
            'status' => 'Active',
        ]);
    }

    protected function plan(string $type = 'Monthly', ?int $months = 1): PaymentPlan
    {
        return PaymentPlan::create([
            'type' => $type,
            'months' => $type === 'Monthly' ? $months : null,
            'amount' => 4000,
            'is_active' => true,
        ]);
    }

    protected function pay(Member $member, ?PaymentPlan $plan, string $date, string $status = 'Paid'): Payment
    {
        return Payment::create([
            'member_id' => $member->id,
            'payment_plan_id' => $plan?->id,
            'amount' => 4000,
            'method' => 'Cash',
            'type' => 'OneTime',
            'status' => $status,
            'date' => $date,
            'due_date' => $date,
            'invoice_number' => 'INV-'.$this->invoice++,
        ]);
    }

    protected function staff(Member $registration, string $status = 'Active'): User
    {
        $role = Role::firstOrCreate(['name' => 'Front Desk'], ['permissions' => [], 'is_admin' => false]);

        return User::create(['member_id' => $registration->id, 'role_id' => $role->id, 'status' => $status]);
    }
}
