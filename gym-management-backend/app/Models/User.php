<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Eloquent model — staff/login accounts (Setup > Users).
 *
 * Replaces the frontend's static "Signed in as Front Desk Staff" placeholder
 * in src/layout/SidePanel.jsx once wired up.
 *
 * Business logic lives in app/Http/Controllers/Api/AuthController.php
 * (login/logout/me) and UserController.php (CRUD + resetPassword).
 *
 * Status values: Active | Inactive ("delete" = status flip, same pattern as
 * Member::destroy() — preserves the login_activities audit trail).
 *
 * Most users are *linked* to a registered member (member_id): an admin
 * granted that registration a role, and the name, email and login
 * (username + password) all come from the member. Unlinked users are the
 * seeded bootstrap admin and pre-registration accounts, which sign in with
 * their own email + password.
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'member_id',
        'full_name',
        'email',
        'password',
        'role_id',
        'branch_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'member',
    ];

    protected $appends = ['username'];

    protected $casts = [
        'password' => 'hashed',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class)->orderByDesc('logged_in_at');
    }

    // -----------------------------------------------------------------------
    // Identity — a linked user's name/email/username live on the member.
    // -----------------------------------------------------------------------

    protected function fullName(): Attribute
    {
        return Attribute::get(fn ($value) => $this->member_id ? $this->member?->full_name : $value);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn ($value) => $this->member_id ? $this->member?->email : $value);
    }

    protected function username(): Attribute
    {
        return Attribute::get(fn () => $this->member?->username);
    }

    // -----------------------------------------------------------------------
    // Permission helpers — consumed by App\Http\Middleware\CheckPermission
    // and AuthController::me().
    // -----------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return (bool) ($this->role?->is_admin ?? false);
    }

    /**
     * @return string[]
     */
    public function permissions(): array
    {
        return $this->role?->permissions ?? [];
    }
}
