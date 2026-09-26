<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting Eloquent model — generic key-value store backing both
 * Setup > Gym Settings ("gym." prefix) and Setup > Email Settings
 * ("mail." prefix), instead of two bespoke singleton tables.
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * All settings whose key starts with the given prefix, as a flat
     * ['suffix' => value] map (e.g. group('gym.') → ['name' => ..., 'address' => ...]).
     */
    public static function group(string $prefix): array
    {
        return static::query()
            ->where('key', 'like', $prefix.'%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [substr($key, strlen($prefix)) => $value])
            ->all();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
