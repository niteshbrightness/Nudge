<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\TwilioInboundLog;
use App\Services\SmsConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioInboundWebhookController extends Controller
{
    public function __invoke(Request $request, SmsConsentService $consentService): Response
    {
        // if (! $this->isValidTwilioSignature($request)) {
        //     abort(403, 'Invalid Twilio signature.');
        // }

        $from = $request->input('From');
        $body = trim(strtoupper($request->input('Body', '')));

        $clients = Client::withoutGlobalScopes()
            ->where('phone', $from)
            ->get();

        $action = null;

        foreach ($clients as $client) {
            if (
                str_starts_with($body, 'STOP')
                || $body === 'UNSUBSCRIBE'
                || $body === 'CANCEL'
                || $body === 'QUIT'
            ) {
                $action = 'stop';
                $consentService->handleOptOut($client, $request->input('Body'), $from);
            } elseif ($body === 'START' || $body === 'UNSTOP' || $body === 'YES') {
                $action = 'start';
                $consentService->handleOptIn($client, $request->input('Body'), $from);
            }
        }

        TwilioInboundLog::create([
            'from_number' => $from,
            'body' => $request->input('Body', ''),
            'action' => $action,
            'clients_affected' => $clients->count(),
            'raw_payload' => $request->all(),
        ]);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    private function isValidTwilioSignature(Request $request): bool
    {
        $authToken = config('notifications.twilio.auth_token', '');

        if (empty($authToken)) {
            return false;
        }

        $url = $request->fullUrl();
        $params = $request->post();
        $signature = $request->header('X-Twilio-Signature', '');

        $validationString = $url;

        if (! empty($params)) {
            ksort($params);
            foreach ($params as $key => $value) {
                $validationString .= $key.$value;
            }
        }

        $computed = base64_encode(hash_hmac('sha1', $validationString, $authToken, true));

        return hash_equals($computed, $signature);
    }
}
