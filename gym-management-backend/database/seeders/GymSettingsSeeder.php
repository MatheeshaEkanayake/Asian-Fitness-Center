<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the gym's profile (Setup > Gym Settings) with the branding the app
 * shipped with, so the settings page opens pre-filled and editable.
 *
 * Only fills keys that aren't set yet — re-running it never overwrites
 * details someone has already changed in Setup. Contact fields are left
 * empty to be filled in from the settings page.
 *
 * The logo is the PNG embedded in the frontend's public/AsianFitnessLogo.svg
 * (uploads only accept raster images), copied onto the public disk where
 * GymSettingsController serves it from.
 */
class GymSettingsSeeder extends Seeder
{
    private const DEFAULTS = [
        'gym.name'    => 'Asian Fitness Center',
        'gym.tagline' => 'Gym operations',
    ];

    private const LOGO_PATH = 'gym/logo.png';

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }

        if (Setting::get('gym.logo_path') === null) {
            Storage::disk('public')->put(
                self::LOGO_PATH,
                file_get_contents(__DIR__.'/assets/gym-logo.png')
            );
            Setting::set('gym.logo_path', self::LOGO_PATH);
        }
    }
}
