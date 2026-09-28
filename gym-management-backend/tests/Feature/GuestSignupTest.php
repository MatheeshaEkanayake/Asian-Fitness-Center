<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class GuestSignupTest extends TestCase
{
    use CreatesGymRecords;
    use RefreshDatabase;

    private function signupPayload(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Nimal Perera',
            'phone' => '0771234567',
            'whatsapp_number' => '0771234567',
            'dob' => '1995-04-12',
            'username' => 'nimal',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    private function actingAsAdmin(): void
    {
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_admin' => true]);
        $this->actingAs(User::create(['full_name' => 'Admin', 'email' => 'admin@example.com', 'role_id' => $role->id, 'status' => 'Active']), 'sanctum');
    }

    public function test_signup_creates_a_signed_in_guest_with_basic_details_only(): void
    {
        $response = $this->postJson('/api/auth/signup', $this->signupPayload());

        $response->assertCreated()->assertJsonPath('user.status', 'Guest')->assertJsonStructure(['token']);
        $guest = Member::where('username', 'nimal')->first();
        $this->assertSame('Guest', $guest->status);
        $this->assertNull($guest->payment_plan_id);
        $this->assertSame('0771234567', $guest->whatsapp_number);
    }

    public function test_signup_validates_its_fields(): void
    {
        $this->member(['username' => 'taken']);

        $this->postJson('/api/auth/signup', $this->signupPayload([
            'full_name' => '',
            'whatsapp_number' => '',
            'dob' => '2999-01-01',
            'username' => 'taken',
            'password_confirmation' => 'different',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'whatsapp_number', 'dob', 'username', 'password']);
    }

    public function test_guest_can_sign_in(): void
    {
        $this->postJson('/api/auth/signup', $this->signupPayload());

        $this->postJson('/api/auth/login', ['username' => 'nimal', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.account_type', 'member')
            ->assertJsonPath('user.status', 'Guest');
    }

    public function test_guests_are_listed_separately_from_members(): void
    {
        $this->actingAsAdmin();
        $guest = $this->member(['full_name' => 'Guest One', 'status' => 'Guest']);
        $this->member(['full_name' => 'Real Member']);
        $staffGuest = $this->member(['full_name' => 'Staff Signup', 'status' => 'Guest']);
        $this->staff($staffGuest);

        $this->getJson('/api/members')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'Real Member');

        $this->getJson('/api/guests')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $guest->id);

        $this->getJson('/api/guests?search=guest')->assertJsonCount(1);
        $this->getJson('/api/guests?search=nobody')->assertJsonCount(0);
    }

    public function test_promote_makes_an_active_member_joining_today(): void
    {
        $this->actingAsAdmin();
        $plan = $this->plan();
        $guest = $this->member(['status' => 'Guest', 'join_date' => '2026-01-01']);

        $this->postJson("/api/guests/{$guest->id}/promote", ['payment_plan_id' => $plan->id, 'member_id_number' => '42'])
            ->assertOk()->assertJsonPath('status', 'Active');

        $guest->refresh();
        $this->assertSame($plan->id, $guest->payment_plan_id);
        $this->assertSame('42', $guest->member_id_number);
        $this->assertSame(now()->toDateString(), $guest->join_date->toDateString());
    }

    public function test_promote_requires_a_plan_and_a_valid_pin(): void
    {
        $this->actingAsAdmin();
        $guest = $this->member(['status' => 'Guest']);
        $this->member(['member_id_number' => '7']);

        $this->postJson("/api/guests/{$guest->id}/promote", ['member_id_number' => '7'])
            ->assertUnprocessable()->assertJsonValidationErrors(['payment_plan_id', 'member_id_number']);

        $this->assertSame('Guest', $guest->fresh()->status);
    }

    public function test_guest_routes_refuse_members(): void
    {
        $this->actingAsAdmin();
        $member = $this->member();

        $this->getJson("/api/guests/{$member->id}")->assertNotFound();
        $this->postJson("/api/guests/{$member->id}/promote", ['payment_plan_id' => $this->plan()->id])->assertNotFound();
        $this->deleteJson("/api/guests/{$member->id}")->assertNotFound();
        $this->assertModelExists($member);
    }

    public function test_delete_removes_the_guest(): void
    {
        $this->actingAsAdmin();
        $guest = $this->member(['status' => 'Guest']);

        $this->deleteJson("/api/guests/{$guest->id}")->assertOk();

        $this->assertModelMissing($guest);
    }

    public function test_payments_cannot_be_recorded_for_guests(): void
    {
        $this->actingAsAdmin();
        $guest = $this->member(['status' => 'Guest']);

        $this->postJson('/api/payments', ['member_id' => $guest->id, 'amount' => 4000, 'method' => 'Cash'])
            ->assertUnprocessable()->assertJsonValidationErrors(['member_id']);
    }

    public function test_members_cannot_use_guest_routes(): void
    {
        $guest = $this->member(['status' => 'Guest']);
        $this->actingAs($guest, 'sanctum');

        $this->getJson('/api/guests')->assertForbidden();
    }
}
