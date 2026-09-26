<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Branch Eloquent model.
 *
 * Single-location today (one seeded "Main Branch" row), kept as a real table
 * from the start so multi-location expansion later needs only new rows and a
 * Setup screen, not a schema change — see database/seeders/BranchSeeder.php.
 */
class Branch extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
