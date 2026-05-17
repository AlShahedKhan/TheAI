<?php

namespace Tests\Feature;

use App\Ai\Agents\GeminiAgent;
use App\Models\History;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_page_shows_estimated_chat_and_video_usage(): void
    {
        config(['ai.providers.gemini.monthly_budget_usd' => 10]);

        $user = User::factory()->create();

        History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'assistant',
            'content' => 'Answer',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => json_encode([
                'prompt_tokens' => 1_000_000,
                'completion_tokens' => 1_000_000,
            ]),
            'meta' => json_encode([
                'model' => 'gemini-2.5-flash-lite',
                'model_label' => 'Gemini 2.5 Flash-Lite',
            ]),
        ]);

        VideoGeneration::create([
            'user_id' => $user->id,
            'model' => 'veo-3.1-fast-generate-preview',
            'prompt' => 'A city',
            'aspect_ratio' => '16:9',
            'resolution' => '720p',
            'status' => 'completed',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('usage.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('usage/index')
                ->where('chat.messages', 1)
                ->where('chat.prompt_tokens', 1_000_000)
                ->where('chat.completion_tokens', 1_000_000)
                ->where('chat.estimated_cost', 0.5)
                ->where('chat.by_model.0.label', 'Gemini 2.5 Flash-Lite')
                ->where('budget.configured', true)
                ->where('budget.amount', 10)
                ->where('budget.remaining', 9.5)
                ->where('video.total', 1)
                ->where('video.completed', 1)
            );
    }

    public function test_usage_page_requires_authentication(): void
    {
        $this
            ->get(route('usage.index'))
            ->assertRedirect(route('login'));
    }
}
