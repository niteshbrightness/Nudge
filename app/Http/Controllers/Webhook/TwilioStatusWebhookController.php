<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhook\Concerns\ValidatesTwilioSignature;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioStatusWebhookController extends Controller
{
    use ValidatesTwilioSignature;

    public function __invoke(Request $request): Response
    {
        if (! $this->isValidTwilioSignature($request)) {
            abort(403, 'Invalid Twilio signature.');
        }

        $sid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');
        $error = $request->input('ErrorMessage');

        $log = NotificationLog::withoutGlobalScopes()
            ->where('twilio_sid', $sid)
            ->first();

        if ($log) {
            $log->update([
                'status' => $status,
                'error_message' => $error,
            ]);
        }

        return response('', 204);
    }
}
