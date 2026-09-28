<?php

namespace App\Services\Vft;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pushes one person's door access to the device, always as the full
 * desired state (safe to repeat):
 *
 *   Staff (registration granted a role)
 *     active staff account → on the device, no expiry, door access
 *     inactive             → blocked
 *   Member
 *     Active and access_valid_until ≥ today → on the device with those dates
 *     otherwise → blocked, if they were ever put on the device
 *
 * Granting = add/rename them in the VFT cloud, copy them to the device the
 * first time, then set their dates and turn door access on (see
 * VftDeviceCommandService for why in that order).
 *
 * "Blocked" keeps them (and their fingerprints) on the device but blocks
 * door access and ends their dates yesterday, so paying again restores
 * access without re-scanning. If the PIN changed, the old VFT employee is
 * removed from the device and the cloud first.
 *
 * Result is saved on the member (device_sync_*) for their profile:
 *   synced | dry_run | failed | no_pin | no_access
 */
class MemberDeviceSync
{
    public function __construct(private readonly VftDeviceCommandService $commands) {}

    /** @return string the resulting device_sync_status */
    public function sync(Member $member): string
    {
        $member->loadMissing('staffAccount');
        $pin = $member->devicePin();
        $previousPin = $member->device_pin_synced;
        $today = CarbonImmutable::now(config('vft.timezone'))->startOfDay();

        try {
            // PIN changed or removed: the old device user must go.
            if ($previousPin && $previousPin !== $pin) {
                $this->commands->removePerson($previousPin);
                $previousPin = null;
            }

            if (! $pin) {
                return $this->record($member, 'no_pin', null, deviceMode: $this->commands->mode());
            }

            $state = $this->desiredState($member, $today);

            if ($state === 'grant') {
                [$from, $until] = $this->grantDates($member);
                $this->commands->upsertPerson($pin, $member->full_name);
                if ($previousPin !== $pin) {
                    $this->commands->transferToDevice($pin);
                }
                $this->commands->setValidity($pin, $from, $until);
                $this->commands->grantAccess($pin);
            } elseif ($previousPin) {
                $this->block($member, $pin, $today);
            } else {
                // Never on the device and no access now: nothing to send.
                return $this->record($member, 'no_access', null, deviceMode: $this->commands->mode());
            }

            return $this->record($member, null, $pin, deviceMode: $this->commands->mode());
        } catch (Throwable $e) {
            $member->updateQuietly([
                'device_sync_status' => 'failed',
                'device_sync_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }

    /** 'grant' or 'block' */
    public function desiredState(Member $member, CarbonImmutable $today): string
    {
        if ($staff = $member->staffAccount) {
            return $staff->status === 'Active' ? 'grant' : 'block';
        }

        $until = $member->access_valid_until ? CarbonImmutable::parse($member->access_valid_until) : null;

        return $member->status === 'Active' && $until && $until->greaterThanOrEqualTo($today)
            ? 'grant'
            : 'block';
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} null = no limit */
    private function grantDates(Member $member): array
    {
        if ($member->staffAccount) {
            return [null, null];
        }

        return [
            $member->access_valid_from ? CarbonImmutable::parse($member->access_valid_from) : null,
            CarbonImmutable::parse($member->access_valid_until),
        ];
    }

    private function block(Member $member, string $pin, CarbonImmutable $today): void
    {
        $yesterday = $today->subDay();
        $from = $member->access_valid_from ? CarbonImmutable::parse($member->access_valid_from) : $yesterday;

        $this->commands->setValidity($pin, $from->min($yesterday), $yesterday);
        $this->commands->blockAccess($pin);
    }

    /**
     * @param  ?string  $status  null → derived from the mode (synced / dry_run)
     * @param  ?string  $pinOnDevice  the PIN now on the device (null if none)
     */
    private function record(Member $member, ?string $status, ?string $pinOnDevice, string $deviceMode): string
    {
        $status ??= $deviceMode === 'live' ? 'synced' : 'dry_run';

        // In dry-run nothing reached the device, so don't claim a PIN is on it.
        $pinOnDevice = $deviceMode === 'live' ? $pinOnDevice : $member->device_pin_synced;
        if ($deviceMode === 'live' && $status !== 'synced') {
            $pinOnDevice = null;
        }

        $member->updateQuietly([
            'device_sync_status' => $status,
            'device_sync_error' => null,
            'device_synced_at' => now(),
            'device_pin_synced' => $pinOnDevice,
        ]);

        return $status;
    }
}
