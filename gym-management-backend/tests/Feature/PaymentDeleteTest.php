<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class PaymentDeleteTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    private function actAs(array $permissions): void
    {
        $role = Role::create(['name' => 'Desk '.uniqid(), 'is_admin' => false, 'permissions' => $permissions]);
        $this->actingAs(User::create(['full_name' => 'Desk', 'email' => uniqid().'@example.test',
            'password' => 'x', 'role_id' => $role->id, 'status' => 'Active']), 'sanctum');
    }

    public function test_deleting_a_payment_needs_its_own_permission_and_keeps_access_dates(): void
    {
        $member = $this->member();
        $payment = $this->pay($member, $this->plan(), '2026-09-01');
        $until = $member->refresh()->access_valid_until->toDateString();

        $this->actAs(['payments.view', 'payments.edit']);
        $this->deleteJson("/api/payments/{$payment->id}")->assertForbidden();

        $this->actAs(['payments.delete']);
        $this->deleteJson("/api/payments/{$payment->id}")->assertOk();

        $this->assertNull(Payment::find($payment->id));
        $this->assertSame($until, $member->refresh()->access_valid_until->toDateString());
    }
}
