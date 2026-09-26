<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PaymentPlan Eloquent model — a membership plan the gym offers.
 *
 * Type values: Daily | Monthly
 * `months` is set for Monthly plans only (null for Daily).
 *
 * Plans are retired (is_active = false) rather than deleted, since
 * members/payments reference them — see PaymentPlanController.
 */
class PaymentPlan extends Model
{
    protected $fillable = [
        'type',
        'months',
        'amount',
        'is_active',
    ];

    protected $casts = [
        'months'    => 'integer',
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['name'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Display name derived from type/months, e.g. "Daily", "1 month",
     * "3 months" — plans have no separate free-text name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type === 'Daily'
                ? 'Daily'
                : $this->months . ' ' . ($this->months === 1 ? 'month' : 'months'),
        );
    }
}
