<?php

namespace App\Http\Controllers\Webhook\Concerns;

use Illuminate\Http\Request;

trait ValidatesTwilioSignature
{
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
