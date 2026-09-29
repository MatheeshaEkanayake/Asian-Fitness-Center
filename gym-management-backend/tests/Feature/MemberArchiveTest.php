<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\Feature\Vft\Concerns\FakesVft;
use Tests\TestCase;

/** Gate access toggle, archiving (delete) and the 6-month purge. */
class MemberArchiveTest extends TestCase
{
    use CreatesGymRecords, FakesVft, RefreshDatabase;

    private function actAs(array $permissions): void
    {
        $role = Role::create(['name' => 'Desk '.uniqid(), 'is_admin' => false, 'permissions' => $permissions]);
        $this->actingAs(User::create(['full_name' => 'Desk', 'email' => uniqid().'@example.test',
            'password' => 'x', 'role_id' => $role->id, 'status' => 'Active']), 'sanctum');
    }

    public function test_gate_access_needs_its_own_permission(): void
    {
        $member = $this->member();

        $this->actAs(['members.view', 'members.edit']);
        $this->patchJson("/api/members/{$member->id}/gate-access", ['enabled' => false])->assertForbidden();

        $this->actAs(['members.gate_access']);
        $this->patchJson("/api/members/{$member->id}/gate-access", ['enabled' => false])
            ->assertOk()->assertJsonPath('status', 'Inactive');
        $this->assertNotNull($member->refresh()->inactive_since);

        $this->patchJson("/api/members/{$member->id}/gate-access", ['enabled' => true])
            ->assertOk()->assertJsonPath('status', 'Active');
        $this->assertNull($member->refresh()->inactive_since);
    }

    public function test_delete_needs_its_own_permission_and_archives(): void
    {
        $member = $this->member();
        $payment = $this->pay($member, $this->plan(), '2026-09-01');

        $this->actAs(['members.view', 'members.edit']);
        $this->deleteJson("/api/members/{$member->id}")->assertForbidden();

        $this->actAs(['members.view', 'members.delete', 'payments.view']);
        $this->deleteJson("/api/members/{$member->id}")->assertOk();

        $archived = Member::withTrashed()->find($member->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame('Inactive', $archived->status);
        $this->assertNotContains($member->id, collect($this->getJson('/api/members')->json('data'))->pluck('id'));

        // Payment details still show who paid.
        $this->getJson("/api/payments/{$payment->id}")->assertJsonPath('member.full_name', 'Test Person');
    }

    public function test_staff_registrations_cannot_be_deleted_from_the_member_list(): void
    {
        $registration = $this->member();
        $this->staff($registration);

        $this->actAs(['members.delete']);
        $this->deleteJson("/api/members/{$registration->id}")->assertStatus(422);
    }

    public function test_archiving_removes_the_member_from_the_device(): void
    {
        config(['vft.mode' => 'live']);
        $this->fakeVft();
        $member = $this->member(['member_id_number' => '42']);
        $member->updateQuietly(['device_pin_synced' => '42']);

        $member->delete();

        $this->assertContains('DELETE /api/template/fromdev/42', $this->vftWrites());
        $this->assertContains('DELETE /api/template/42', $this->vftWrites());
        $this->assertNull(Member::withTrashed()->find($member->id)->device_pin_synced);
    }

    public function test_purge_removes_members_archived_or_inactive_for_six_months_and_keeps_history(): void
    {
        $this->travelTo('2026-01-01');
        $archived = $this->member(['full_name' => 'Old Archived', 'member_id_number' => '7']);
        $archived->update(['status' => 'Inactive']);
        $archived->delete();
        $inactive = $this->member(['full_name' => 'Old Inactive', 'status' => 'Inactive']);
        $active = $this->member(['full_name' => 'Unpaid Active']);
        $staffReg = $this->member(['full_name' => 'Staff', 'status' => 'Inactive']);
        $this->staff($staffReg);
        $payment = $this->pay($archived, $this->plan(), '2025-12-01');
        $visit = Attendance::create(['member_id' => $archived->id, 'member_name' => 'Old Archived',
            'date' => '2025-12-02', 'status' => 'Present']);

        $this->travelTo('2026-05-01');
        $recent = $this->member(['full_name' => 'Recent', 'status' => 'Inactive']);

        $this->travelTo('2026-07-02');
        $this->assertSame(0, $this->artisan('members:purge-archived', ['--dry-run' => true]));
        $this->assertNotNull(Member::withTrashed()->find($archived->id), 'dry run deletes nothing');

        $this->assertSame(0, $this->artisan('members:purge-archived'));

        $this->assertNull(Member::withTrashed()->find($archived->id));
        $this->assertNull(Member::withTrashed()->find($inactive->id));
        $this->assertNotNull(Member::find($active->id));
        $this->assertNotNull(Member::find($staffReg->id));
        $this->assertNotNull(Member::find($recent->id));

        $payment->refresh();
        $this->assertNull($payment->member_id);
        $this->assertSame('Old Archived', $payment->member_name);
        $this->assertSame('7', $payment->member_id_number);
        $this->assertNull($visit->refresh()->member_id);
    }
}
