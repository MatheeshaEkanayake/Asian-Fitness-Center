<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGymSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GymSettingsController — Setup > Gym Settings (singleton, "gym." key prefix).
 *
 * The gym's own profile (name, tagline, contact details, logo), so the
 * branding shown across the app — sidebar, sign-in pages, receipts, browser
 * tab — comes from here instead of being hard-coded.
 *
 * show/update are gated behind ['auth:sanctum', 'permission:setup.manage']
 * (see routes/api.php). branding/logo are public because the sign-in pages
 * need them before anyone is logged in.
 *
 * Frontend: src/pages/setup/GymSettingsPage.jsx
 * (src/context/GymSettingsContext.jsx, src/context/BrandingContext.jsx,
 * src/services/gymSettingsService.js).
 */
class GymSettingsController extends Controller
{
    /** Plain-text fields, stored as "gym.<field>". */
    private const FIELDS = ['name', 'tagline', 'email', 'phone', 'address', 'website'];

    /**
     * GET /api/setup/gym
     */
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * PUT /api/setup/gym
     */
    public function update(UpdateGymSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        foreach (self::FIELDS as $field) {
            Setting::set("gym.{$field}", $data[$field] ?? null);
        }

        $oldLogo = Setting::get('gym.logo_path');

        if ($request->hasFile('logo')) {
            Setting::set('gym.logo_path', $request->file('logo')->store('gym', 'public'));
            $this->deleteLogoFile($oldLogo);
        } elseif ($request->boolean('remove_logo')) {
            Setting::set('gym.logo_path', null);
            $this->deleteLogoFile($oldLogo);
        }

        return response()->json($this->payload());
    }

    /**
     * GET /api/gym/branding — public subset for the sign-in pages and the
     * app shell. Contact details are included because receipts show them.
     */
    public function branding(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * GET /api/gym/logo — public. Streams the logo through the API rather
     * than linking /storage/..., so it still loads when the public/storage
     * symlink is missing or stale (same approach as the ERP's company logo).
     */
    public function logo(): Response
    {
        $path = Setting::get('gym.logo_path');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Logo not configured.'], 404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type'  => Storage::disk('public')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function payload(): array
    {
        $settings = Setting::group('gym.');
        $logoPath = $settings['logo_path'] ?? null;

        $payload = [];
        foreach (self::FIELDS as $field) {
            $payload[$field] = $settings[$field] ?? null;
        }

        // The file name changes on every upload, so it doubles as a cache
        // buster for the logo route's Cache-Control.
        $payload['logo_url'] = $logoPath
            ? url('/api/gym/logo').'?v='.md5($logoPath)
            : null;

        return $payload;
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
