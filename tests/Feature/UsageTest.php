<?php

namespace Tests\Feature;

use App\Ai\Agents\GeminiAgent;
use App\Models\CreditTransaction;
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
                ->where('credits.user.balance', 0)
                ->where('credits.site.available', 0)
                ->where('credits.rates.credits_per_usd', 150)
                ->where('credits.rates.bdt_per_credit', 1)
                ->where('credits.rates.chat_message_cost', 1)
                ->where('credits.rates.video_generation_cost', 100)
                ->where('links', null)
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

    public function test_admin_can_recharge_website_credits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this
            ->actingAs($admin)
            ->post(route('usage.credits.recharge'), [
                'amount_usd' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => 300,
            'amount' => 2,
            'currency' => 'USD',
        ]);
    }

    public function test_non_admin_cannot_recharge_website_credits(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('usage.credits.recharge'), [
                'amount_usd' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_user_can_buy_credits_from_available_website_pool(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        CreditTransaction::create([
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => 150,
            'amount' => 1,
            'currency' => 'USD',
            'meta' => ['mode' => 'test'],
        ]);

        $this
            ->actingAs($user)
            ->post(route('usage.credits.purchase'), [
                'credits' => 100,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
            'credits' => 100,
            'amount' => 100,
            'currency' => 'BDT',
        ]);

        $this
            ->actingAs($user)
            ->get(route('usage.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('credits.user.balance', 100)
                ->where('credits.user.spent_bdt', 100)
                ->where('credits.site.available', 0)
                ->where('links', null)
            );
    }

    public function test_admin_can_see_website_pool_and_google_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        CreditTransaction::create([
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => 150,
            'amount' => 1,
            'currency' => 'USD',
            'meta' => ['mode' => 'test'],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('usage.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('credits.site.available', 150)
                ->where('links.aiStudio', 'https://aistudio.google.com/')
                ->where('links.cloudBilling', 'https://console.cloud.google.com/billing')
                ->where('links.pricing', 'https://ai.google.dev/pricing')
            );
    }

    public function test_user_cannot_buy_more_credits_than_website_pool_has(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('usage.index'))
            ->post(route('usage.credits.purchase'), [
                'credits' => 100,
            ])
            ->assertRedirect(route('usage.index'))
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('credit_transactions', 0);
    }
}
