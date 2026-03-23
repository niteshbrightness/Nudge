<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitlyService
{
    public function __construct(
        private readonly string $accessToken = '',
        private readonly string $apiUrl = 'https://api-ssl.bitly.com/v4',
    ) {}

    /**
     * Shorten a URL using the Bitly API v4.
     * Returns the original URL if shortening fails (graceful degradation).
     */
    public function shorten(string $url): string
    {
        if (empty($this->accessToken)) {
            return $url;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/shorten", ['long_url' => $url])
                ->throw();

            return $response->json('link', $url);
        } catch (RequestException $e) {
            Log::warning('Bitly shortening failed', ['url' => $url, 'error' => $e->getMessage()]);

            return $url;
        }
    }
}
