<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * EmailSettingsController — Setup > Email Settings (singleton, "mail." key prefix).
 *
 * Gated behind ['auth:sanctum', 'permission:setup.manage'] (see routes/api.php).
 * Frontend: src/pages/setup/EmailSettingsPage.jsx
 * (src/context/EmailSettingsContext.jsx, src/services/emailSettingsService.js).
 */
class EmailSettingsController extends Controller
{
    private const KEYS = ['host', 'port', 'encryption', 'username', 'from_address', 'from_name'];

    /**
     * GET /api/setup/email
     *
     * Password is intentionally never returned to the client.
     */
    public function show(): JsonResponse
    {
        $settings = Setting::group('mail.');

        return response()->json([
            'host'         => $settings['host'] ?? null,
            'port'         => $settings['port'] ?? null,
            'encryption'   => $settings['encryption'] ?? null,
            'username'     => $settings['username'] ?? null,
            'from_address' => $settings['from_address'] ?? null,
            'from_name'    => $settings['from_name'] ?? null,
            'has_password' => Setting::get('mail.password') !== null,
        ]);
    }

    /**
     * PUT /api/setup/email
     */
    public function update(UpdateEmailSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        foreach (self::KEYS as $key) {
            Setting::set("mail.{$key}", $data[$key] ?? null);
        }

        // Only overwrite the stored password if a new one was actually sent.
        if (!empty($data['password'])) {
            Setting::set('mail.password', $data['password']);
        }

        return $this->show();
    }

    /**
     * POST /api/setup/email/test
     *
     * Sends a test email using the currently persisted SMTP settings.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['to' => ['required', 'email']]);

        $settings = Setting::group('mail.');

        Config::set('mail.mailers.smtp.host', $settings['host'] ?? null);
        Config::set('mail.mailers.smtp.port', $settings['port'] ?? null);
        Config::set('mail.mailers.smtp.encryption', $settings['encryption'] ?? null);
        Config::set('mail.mailers.smtp.username', $settings['username'] ?? null);
        Config::set('mail.mailers.smtp.password', Setting::get('mail.password'));
        Config::set('mail.from.address', $settings['from_address'] ?? null);
        Config::set('mail.from.name', $settings['from_name'] ?? null);
        Config::set('mail.default', 'smtp');

        Mail::raw('This is a test email from your Asian Fitness Gym Email Setup screen.', function ($message) use ($request) {
            $message->to($request->input('to'))->subject('Gym Management — Test Email');
        });

        return response()->json(['message' => 'Test email sent.']);
    }
}
