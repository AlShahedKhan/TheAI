<?php

namespace Tests\Feature;

use App\Jobs\GenerateVeoVideo;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\VeoVideoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VideoGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_video_generations(): void
    {
        $user = User::factory()->create();
        VideoGeneration::create([
            'user_id' => $user->id,
            'model' => 'veo-3.1-fast-generate-preview',
            'prompt' => 'A bright city skyline',
            'aspect_ratio' => '16:9',
            'resolution' => '720p',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('videos.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('videos/index')
                ->has('generations', 1)
                ->has('modelOptions', 6)
                ->where('defaultModel', 'veo-3.1-fast-generate-preview')
                ->where('aspectRatios', ['16:9', '9:16'])
                ->where('resolutions', ['720p', '1080p'])
            );
    }

    public function test_video_generation_can_be_created(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->grantCredits($user, 100);

        $response = $this
            ->actingAs($user)
            ->post(route('videos.store'), [
                'model' => 'veo-3.1-fast-generate-preview',
                'prompt' => 'A bright city skyline',
                'aspect_ratio' => '16:9',
                'resolution' => '720p',
            ]);

        $generation = VideoGeneration::query()->firstOrFail();

        $response->assertRedirect();
        $this->assertDatabaseHas('video_generations', [
            'id' => $generation->id,
            'user_id' => $user->id,
            'model' => 'veo-3.1-fast-generate-preview',
            'prompt' => 'A bright city skyline',
            'aspect_ratio' => '16:9',
            'resolution' => '720p',
            'status' => 'pending',
        ]);
        Bus::assertDispatched(
            GenerateVeoVideo::class,
            fn (GenerateVeoVideo $job) => $job->videoGenerationId === $generation->id,
        );
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_VIDEO_USAGE,
            'credits' => 100,
        ]);
    }

    public function test_video_generation_requires_credits(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('videos.store'), [
                'model' => 'veo-3.1-fast-generate-preview',
                'prompt' => 'A bright city skyline',
                'aspect_ratio' => '16:9',
                'resolution' => '720p',
            ])
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('video_generations', 0);
        Bus::assertNothingDispatched();
    }

    public function test_video_generation_validates_options(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('videos.store'), [
                'model' => 'veo-not-real',
                'prompt' => '',
                'aspect_ratio' => '1:1',
                'resolution' => '4k',
            ]);

        $response->assertSessionHasErrors(['model', 'prompt', 'aspect_ratio', 'resolution']);
        $this->assertDatabaseCount('video_generations', 0);
        Bus::assertNothingDispatched();
    }

    public function test_generate_veo_video_job_downloads_completed_video(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/veo-3.1-fast-generate-preview:predictLongRunning' => Http::response([
                'name' => 'operations/video-123',
            ]),
            'https://generativelanguage.googleapis.com/v1beta/operations/video-123' => Http::response([
                'done' => true,
                'response' => [
                    'generateVideoResponse' => [
                        'generatedSamples' => [
                            [
                                'video' => [
                                    'uri' => 'https://generativelanguage.googleapis.com/download/video-123',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://generativelanguage.googleapis.com/download/video-123' => Http::response('fake-video-bytes'),
        ]);

        $user = User::factory()->create();
        $generation = VideoGeneration::create([
            'user_id' => $user->id,
            'model' => 'veo-3.1-fast-generate-preview',
            'prompt' => 'A bright city skyline',
            'aspect_ratio' => '16:9',
            'resolution' => '720p',
            'status' => 'pending',
        ]);

        (new GenerateVeoVideo($generation->id))->handle(new VeoVideoClient);

        $generation->refresh();

        $this->assertSame('completed', $generation->status);
        $this->assertSame('operations/video-123', $generation->operation_name);
        $this->assertSame('videos/veo-'.$generation->id.'.mp4', $generation->video_path);
        Storage::disk('public')->assertExists($generation->video_path);
        $this->assertSame('fake-video-bytes', Storage::disk('public')->get($generation->video_path));
    }

    private function grantCredits(User $user, int $credits): void
    {
        CreditTransaction::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
            'credits' => $credits,
            'amount' => $credits,
            'currency' => 'BDT',
            'meta' => ['mode' => 'test'],
        ]);
    }
}
