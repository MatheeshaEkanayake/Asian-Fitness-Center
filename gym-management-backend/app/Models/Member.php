<?php

namespace App\Models;

use App\Observers\MemberObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Member Eloquent model.
 *
 * Replaces the in-memory member array in:
 *   Frontend: src/services/db.js → db.members
 *
 * Business logic from the following frontend files now lives in
 * MemberController (app/Http/Controllers/Api/MemberController.php):
 *   - listMembers()      → GET  /api/members
 *   - getMember()        → GET  /api/members/{id}
 *   - createMember()     → POST /api/members
 *   - updateMember()     → PUT  /api/members/{id}
 *   - setMemberStatus()  → PATCH /api/members/{id}/status
 *   - deactivateMember() → DELETE /api/members/{id}
 *
 * Status values: Active | Inactive | Suspended
 *
 * Authenticatable + HasApiTokens so members can sign in with their username
 * (AuthController::login). Member tokens are rejected by every staff route —
 * see CheckPermission.
 *
 * A registration that an admin has granted a role to has a `staffAccount`
 * (users.member_id) and signs in as staff. Staff aren't gym members, so
 * they're left out of member lists (scopeNotStaff).
 *
 * Everyone signs up as a Guest (status 'Guest'); staff make them a member
 * from Members > Guests (GuestController::promote). Guests are listed only
 * there, can't be paid for, and get no door access.
 *
 * Deleting a member from the list archives them (soft delete) so payments
 * can still show who paid. members:purge-archived removes them for good
 * once they've been archived or not Active for 6 months (inactive_since).
 */
#[ObservedBy(MemberObserver::class)]
class Member extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'payment_plan_id',
        'member_id_number',
        'full_name',
        'nic',
        'email',
        'phone',
        'whatsapp_number',
        'dob',
        'gender',
        'address',
        'weight_kg',
        'height_cm',
        'occupation',
        'emergency_contact_name',
        'emergency_contact_phone',
        'username',
        'password',
        'join_date',
        'status',
        'inactive_since',
        'notes',
        // Door access (VFT device) — see AccessValidityService / MemberDeviceSync.
        'access_valid_from',
        'access_valid_until',
        'access_note',
        'device_sync_status',
        'device_sync_error',
        'device_synced_at',
        'device_pin_synced',
    ];

    protected $casts = [
        // Serialized as plain Y-m-d so the member form's <input type="date">
        // can display them (a full ISO datetime shows as blank).
        'dob'        => 'date:Y-m-d',
        'join_date'  => 'date:Y-m-d',
        'weight_kg'  => 'decimal:2',
        'height_cm'  => 'decimal:2',
        // Auto-hashes on assignment (skips re-hashing if already hashed, so
        // this is safe alongside MemberController's explicit Hash::make()).
        'password'   => 'hashed',
        'access_valid_from'  => 'date:Y-m-d',
        'access_valid_until' => 'date:Y-m-d',
        'device_synced_at'   => 'datetime',
        'inactive_since'     => 'datetime',
    ];

    protected static function booted(): void
    {
        // Start the purge clock when a member stops being Active, and stop it
        // when they're Active again. Guests aren't members yet, so no clock.
        static::saving(function (Member $member) {
            if (! $member->isDirty('status')) {
                return;
            }
            $counts = ! in_array($member->status, ['Active', 'Guest'], true);
            if ($counts && ! $member->inactive_since) {
                $member->inactive_since = now();
            } elseif (! $counts) {
                $member->inactive_since = null;
            }
        });
    }

    // membershipType/paymentStatus/todayAttendanceStatus are computed from
    // the latestPayment/todayAttendance relations below (there is no stored
    // "plan" or "paid" column — see MemberListPage.jsx's roster table). The
    // relations themselves are hidden since only those three derived fields
    // are meant to reach the frontend, not the raw payment/attendance rows.
    protected $appends = [
        'membership_type',
        'payment_status',
        'today_attendance_status',
    ];

    protected $hidden = ['latestPayment', 'todayAttendance', 'password'];

    // Always include the member's plan (sent to the frontend as `paymentPlan`).
    protected $with = ['paymentPlan'];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The branch this member is registered at (nullable — single-location
     * gyms don't need to set this).
     */
    public function staffAccount(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** The Member ID number, when it's usable as a device PIN (1–9 digits). */
    public function devicePin(): ?string
    {
        $pin = trim((string) $this->member_id_number);

        return preg_match('/^\d{1,9}$/', $pin) ? $pin : null;
    }

    /** Members who haven't been granted a staff role. */
    public function scopeNotStaff($query)
    {
        return $query->whereDoesntHave('staffAccount');
    }

    /** Registrations not yet made a member. */
    public function scopeGuests($query)
    {
        return $query->where('status', 'Guest');
    }

    public function scopeNotGuest($query)
    {
        return $query->where('status', '!=', 'Guest');
    }

    public function isGuest(): bool
    {
        return $this->status === 'Guest';
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * All payments made by this member.
     * NOTE: renamed from transactions() to payments() to match the
     * Transaction → Payment model/table rename (see app/Models/Payment.php).
     * Mirrors: src/context/PaymentsContext.jsx → getTransactionsForMember()
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('date');
    }

    /**
     * All attendance records for this member.
     * Mirrors: src/pages/members/MemberAttendencePage.jsx (attendance data)
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderByDesc('date');
    }

    /**
     * This member's single most recent payment (by date), used to derive
     * membershipType/paymentStatus below. `latestOfMany()` runs as an
     * efficient correlated subquery, so eager-loading it on the member
     * index (see MemberController::index) doesn't cause N+1 queries.
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany('date');
    }

    /**
     * This member's attendance record for today, if they've been checked
     * in. Used to derive todayAttendanceStatus below.
     */
    public function todayAttendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->whereDate('date', now()->toDateString());
    }

    // -----------------------------------------------------------------------
    // Computed attributes (see MemberListPage.jsx's roster table)
    // -----------------------------------------------------------------------

    /**
     * "Recurring" | "One-time" | null, derived from the latest payment's
     * `type` column. There is no separate membership-plan concept in this
     * schema, so the roster table uses the member's latest payment type as
     * a stand-in for "what kind of membership are they on".
     */
    protected function membershipType(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->latestPayment?->type) {
                'Recurring' => 'Recurring',
                'OneTime'   => 'One-time',
                default     => null,
            },
        );
    }

    /**
     * "Paid" | "Unpaid", derived from the latest payment's status. A member
     * with no payments at all, or whose latest payment isn't marked Paid
     * (Pending/Failed/Refunded/PartiallyRefunded), shows as "Unpaid".
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestPayment?->status === 'Paid' ? 'Paid' : 'Unpaid',
        );
    }

    /**
     * "Present" | "Late" | "Absent" for today. No attendance record for
     * today means the member hasn't checked in, i.e. "Absent".
     */
    protected function todayAttendanceStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->todayAttendance?->status ?? 'Absent',
        );
    }
}
