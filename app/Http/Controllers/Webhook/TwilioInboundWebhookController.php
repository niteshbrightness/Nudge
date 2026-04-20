<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhook\Concerns\ValidatesTwilioSignature;
use App\Models\Client;
use App\Models\TwilioInboundLog;
use App\Services\SmsConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioInboundWebhookController extends Controller
{
    use ValidatesTwilioSignature;

    protected $cancelKeywords = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'QUIT'];

    protected $optInKeywords = ['START', 'UNSTOP', 'YES'];

    public function __invoke(Request $request, SmsConsentService $consentService): Response
    {
        if (! $this->isValidTwilioSignature($request)) {
            abort(403, 'Invalid Twilio signature.');
        }

        $from = $request->input('From');
        $body = trim(strtoupper($request->input('Body', '')));

        $clients = Client::withoutGlobalScopes()
            ->where('phone', $from)
            ->get();

        $action = null;
        if ($request->input('SmsStatus') == 'received') {
            foreach ($clients as $client) {
                if (in_array($body, $this->cancelKeywords)) {
                    $action = 'stop';
                    $consentService->handleOptOut($client, $request->input('Body'), $from);
                } elseif (in_array($body, $this->optInKeywords)) {
                    $action = 'start';
                    $consentService->handleOptIn($client, $request->input('Body'), $from);
                }
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
}
