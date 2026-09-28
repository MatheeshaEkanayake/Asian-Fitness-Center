<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Vft\AttendanceImporter;
use App\Services\Vft\WebhookPunchParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/vft/webhook/{secret} — VFT pushes door punches here as they
 * happen (the collection's WebhookCheck format; see WebhookPunchParser).
 *
 * There is no login: the long random VFT_WEBHOOK_SECRET in the URL is the
 * only thing that proves the caller is VFT, so a wrong or missing secret
 * gets a plain 404. Register the full URL in the VFT portal.
 */
class VftWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, WebhookPunchParser $parser, AttendanceImporter $importer): JsonResponse
    {
        $expected = (string) config('vft.webhook_secret');

        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $body = $request->json()->all();
        $punches = $parser->parse($body);

        Log::channel('vft')->info('← webhook', ['rows' => is_array($body) ? count($body) : 0, 'punches' => count($punches)]);

        $totals = ['new' => 0, 'duplicate' => 0, 'members' => 0, 'staff' => 0, 'unknown' => 0, 'ignored' => 0];

        foreach (collect($punches)->groupBy('device_sn') as $devSn => $devicePunches) {
            foreach ($importer->import((string) $devSn, $devicePunches->all()) as $key => $count) {
                $totals[$key] += $count;
            }
        }

        return response()->json(['message' => 'ok', 'received' => count($punches)] + $totals);
    }
}
