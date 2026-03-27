<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinyUrlService
{
    public function __construct(
        private readonly string $apiToken = '',
        private readonly string $apiUrl = 'https://api.tinyurl.com',
    ) {}

    /**
     * Shorten a URL using the TinyURL API v2.
     * Returns the original URL if shortening fails (graceful degradation).
     */
    public function shorten(string $url): string
    {
        if (empty($this->apiToken)) {
            return $url;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/create", [
                    'url' => $url,
                    'domain' => 'tinyurl.com',
                ])
                ->throw();

            return $response->json('data.tiny_url', $url);
        } catch (RequestException $e) {
            Log::warning('TinyURL shortening failed', ['url' => $url, 'error' => $e->getMessage()]);

            return $url;
        }
    }
}
