<?php

namespace App\Jobs;

use App\Models\VideoGeneration;
use App\Services\VeoVideoClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateVeoVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 60;

    public int $timeout = 120;

    public function __construct(public int $videoGenerationId) {}

    public function handle(VeoVideoClient $client): void
    {
        $generation = VideoGeneration::query()->find($this->videoGenerationId);

        if (! $generation || $generation->status === 'completed') {
            return;
        }

        try {
            if (! $generation->operation_name) {
                $operation = $client->start(
                    $generation->model,
                    $generation->prompt,
                    $generation->aspect_ratio,
                    $generation->resolution,
                );

                $generation->update([
                    'status' => 'processing',
                    'operation_name' => data_get($operation, 'name'),
                    'error' => null,
                ]);
            }

            $status = $client->operation($generation->fresh()->operation_name);

            if (data_get($status, 'error')) {
                $generation->update([
                    'status' => 'failed',
                    'error' => data_get($status, 'error.message', 'Video generation failed.'),
                ]);

                return;
            }

            if (! data_get($status, 'done')) {
                $this->release(10);

                return;
            }

            $uri = data_get($status, 'response.generateVideoResponse.generatedSamples.0.video.uri');

            if (! $uri) {
                $generation->update([
                    'status' => 'failed',
                    'error' => 'Veo completed without a downloadable video.',
                ]);

                return;
            }

            $path = "videos/veo-{$generation->id}.mp4";

            Storage::disk('public')->put($path, $client->download($uri));

            $generation->update([
                'status' => 'completed',
                'video_path' => $path,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $generation->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
