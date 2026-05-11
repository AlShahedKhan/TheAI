<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateVeoVideo;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VideoGenerationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('videos/index', [
            'generations' => VideoGeneration::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (VideoGeneration $generation) => [
                    'id' => $generation->id,
                    'model' => $generation->model,
                    'prompt' => $generation->prompt,
                    'aspect_ratio' => $generation->aspect_ratio,
                    'resolution' => $generation->resolution,
                    'status' => $generation->status,
                    'video_url' => $generation->videoUrl(),
                    'error' => $generation->error,
                    'created_at' => $generation->created_at,
                ]),
            'modelOptions' => $this->modelOptions(),
            'aspectRatios' => ['16:9', '9:16'],
            'resolutions' => ['720p', '1080p'],
            'defaultModel' => 'veo-3.1-fast-generate-preview',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'model' => ['required', 'string', Rule::in($this->allowedModels())],
            'aspect_ratio' => ['required', 'string', Rule::in(['16:9', '9:16'])],
            'resolution' => ['required', 'string', Rule::in(['720p', '1080p'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        $generation = VideoGeneration::create([
            'user_id' => $user->id,
            'model' => $validated['model'],
            'prompt' => $validated['prompt'],
            'aspect_ratio' => $validated['aspect_ratio'],
            'resolution' => $validated['resolution'],
            'status' => 'pending',
        ]);

        GenerateVeoVideo::dispatch($generation->id);

        return back();
    }

    private function modelOptions(): array
    {
        return config('ai.providers.gemini.video_models', []);
    }

    private function allowedModels(): array
    {
        return collect($this->modelOptions())
            ->pluck('value')
            ->all();
    }
}
