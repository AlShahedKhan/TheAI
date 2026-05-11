<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VeoVideoClient
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function start(string $model, string $prompt, string $aspectRatio, string $resolution): array
    {
        return $this->http()
            ->post("{$this->baseUrl}/models/{$model}:predictLongRunning", [
                'instances' => [
                    ['prompt' => $prompt],
                ],
                'parameters' => array_filter([
                    'aspectRatio' => $aspectRatio,
                    'resolution' => $resolution,
                ]),
            ])
            ->throw()
            ->json();
    }

    public function operation(string $operationName): array
    {
        return $this->http()
            ->get("{$this->baseUrl}/{$operationName}")
            ->throw()
            ->json();
    }

    public function download(string $uri): string
    {
        return $this->http()
            ->get($uri)
            ->throw()
            ->body();
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'x-goog-api-key' => (string) config('ai.providers.gemini.key'),
        ])->acceptJson();
    }
}
