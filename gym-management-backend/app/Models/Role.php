<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role Eloquent model.
 *
 * `permissions` is a JSON array of string keys (see app/Support/Permissions.php
 * for the canonical list). `is_admin` roles bypass all permission checks
 * (see User::isAdmin() and App\Http\Middleware\CheckPermission). `is_system`
 * protects the seeded Administrator role from deletion via Setup > Roles.
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_admin',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_admin'    => 'boolean',
        'is_system'   => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
