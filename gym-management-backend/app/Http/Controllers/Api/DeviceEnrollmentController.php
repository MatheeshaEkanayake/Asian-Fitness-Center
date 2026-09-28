<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\Vft\EnrollmentStatus;
use App\Services\Vft\VftApiException;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Puts the door device into fingerprint/face enrollment for one person;
 * they then scan at the device. Backend for the "Enroll fingerprint" /
 * "Enroll face" buttons on the member profile and Setup › Users › Edit.
 *
 *   POST /api/members/{member}/enroll       (members.edit)
 *   POST /api/setup/users/{user}/enroll     (setup.manage — staff)
 *   Body: { type: "finger" | "face", finger_id: 0–9 (finger only) }
 *
 * And reports what is registered so far (see EnrollmentStatus):
 *   GET  /api/members/{member}/enrollments  (members.view)
 *   GET  /api/setup/users/{user}/enrollments (setup.manage)
 */
class DeviceEnrollmentController extends Controller
{
    public function member(Request $request, Member $member, VftDeviceCommandService $commands): JsonResponse
    {
        return $this->enroll($request, $member, $commands);
    }

    public function staff(Request $request, User $user, VftDeviceCommandService $commands): JsonResponse
    {
        abort_unless($user->member, 422, 'This staff account has no registration, so it has no door PIN.');

        return $this->enroll($request, $user->member, $commands);
    }

    public function memberStatus(Member $member, EnrollmentStatus $status): JsonResponse
    {
        return $this->status($member, $status);
    }

    public function staffStatus(User $user, EnrollmentStatus $status): JsonResponse
    {
        abort_unless($user->member, 422, 'This staff account has no registration, so it has no door PIN.');

        return $this->status($user->member, $status);
    }

    private function status(Member $person, EnrollmentStatus $status): JsonResponse
    {
        $pin = $person->devicePin();
        if (! $pin) {
            return response()->json(['refreshError' => null, 'enrollments' => []]);
        }

        $error = $status->refresh();

        return response()->json([
            'refreshError' => $error ? 'Could not reach the door device service, showing the last known status.' : null,
            'enrollments' => $status->forPin($pin),
        ]);
    }

    private function enroll(Request $request, Member $person, VftDeviceCommandService $commands): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:finger,face'],
            'finger_id' => ['required_if:type,finger', 'nullable', 'integer', 'between:0,9'],
        ], [
            'finger_id.required_if' => 'Choose a finger.',
        ]);

        $mode = $commands->mode();
        $pin = $person->devicePin();

        if ($mode === 'off') {
            return $this->error('The door device connection is switched off (VFT_MODE=off).', 409);
        }
        if (! $pin) {
            return $this->error('Add a valid Member ID number (door PIN) first.', 422);
        }
        if ($mode === 'live' && $person->device_pin_synced !== $pin) {
            return $this->error('This person is not on the door device yet — they need door access (a paid membership or active staff account) first.', 422);
        }

        try {
            $result = $data['type'] === 'face'
                ? $commands->enrollFace($pin)
                : $commands->enrollFinger($pin, (int) $data['finger_id']);
        } catch (VftApiException $e) {
            return $this->error('The door device service rejected the request: '.$e->getMessage(), 502);
        }

        return response()->json([
            'sent' => $result->sent,
            'message' => $result->sent
                ? 'Sent. The device will ask them to scan when it next checks in.'
                : 'Dry run (VFT_MODE=log): logged, not sent to the device.',
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
