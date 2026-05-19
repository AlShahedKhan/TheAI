<?php

namespace Tests\Feature;

use App\Ai\Agents\ChatTitleAgent;
use App\Ai\Agents\GeminiAgent;
use App\Jobs\GenerateConversationTitle;
use App\Models\AgentConversation;
use App\Models\CreditTransaction;
use App\Models\History;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_chat(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Planning');

        $response = $this
            ->actingAs($user)
            ->get(route('chat.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('chat/index')
                ->where('activeConversation.id', $conversation->id)
                ->where('activeConversation.title', 'Planning')
                ->where('activeConversation.model', 'gemini-3-flash-preview')
                ->where('defaultModel', 'gemini-3-flash-preview')
                ->has('modelOptions', 6)
                ->has('conversations', 1)
                ->has('messages', 0)
            );
    }

    public function test_new_chat_renders_blank_without_creating_a_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('chat.index', ['new' => 1]));

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('chat/index')
                ->where('activeConversation', null)
                ->where('defaultModel', 'gemini-3-flash-preview')
                ->has('modelOptions', 6)
                ->has('conversations', 0)
                ->has('messages', 0)
            );

        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function test_first_message_creates_conversation_with_auto_title(): void
    {
        Bus::fake();
        GeminiAgent::fake(['Hello from Gemini.']);

        $user = User::factory()->create();
        $this->grantCredits($user);

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'message' => '   Build a launch checklist for Friday   ',
                'model' => 'gemini-2.5-flash',
            ]);

        $conversation = AgentConversation::query()->firstOrFail();

        $response->assertRedirect(route('chat.show', $conversation));
        Bus::assertDispatchedAfterResponse(
            GenerateConversationTitle::class,
            fn (GenerateConversationTitle $job) => $job->conversationId === $conversation->id
                && $job->message === 'Build a launch checklist for Friday'
                && $job->fallbackTitle === 'Build a launch checklist for Friday',
        );

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Build a launch checklist for Friday',
            'model' => 'gemini-2.5-flash',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Build a launch checklist for Friday',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Hello from Gemini.',
        ]);

        $assistantMessage = History::where('role', 'assistant')->firstOrFail();
        $this->assertSame('gemini-2.5-flash', json_decode($assistantMessage->meta, true)['model']);
        $this->assertSame('Gemini 2.5 Flash', json_decode($assistantMessage->meta, true)['model_label']);

        GeminiAgent::assertPrompted(fn ($prompt) => $prompt->model === 'gemini-2.5-flash');
    }

    public function test_ai_title_job_updates_the_fallback_title(): void
    {
        ChatTitleAgent::fake(['"Friday Launch Plan"']);

        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Build a launch checklist for Friday');

        (new GenerateConversationTitle(
            $conversation->id,
            'Build a launch checklist for Friday',
            'Build a launch checklist for Friday',
        ))->handle();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Friday Launch Plan',
        ]);
    }

    public function test_ai_title_job_does_not_overwrite_renamed_conversation(): void
    {
        ChatTitleAgent::fake(['Friday Launch Plan']);

        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'User renamed title');

        (new GenerateConversationTitle(
            $conversation->id,
            'Build a launch checklist for Friday',
            'Build a launch checklist for Friday',
        ))->handle();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'User renamed title',
        ]);

        ChatTitleAgent::assertNeverPrompted();
    }

    public function test_message_can_be_appended_to_existing_conversation(): void
    {
        GeminiAgent::fake(['Follow up answer.']);

        $user = User::factory()->create();
        $this->grantCredits($user);
        $conversation = $this->conversationFor($user, 'Existing chat');
        $otherConversation = $this->conversationFor($user, 'Other chat');

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Continue this chat',
                'model' => 'gemini-3.1-pro-preview',
            ]);

        $response->assertRedirect(route('chat.show', $conversation));

        $this->assertDatabaseCount('agent_conversations', 2);
        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'model' => 'gemini-3.1-pro-preview',
        ]);
        $this->assertEquals(2, History::where('conversation_id', $conversation->id)->count());
        $this->assertEquals(0, History::where('conversation_id', $otherConversation->id)->count());
    }

    public function test_assistant_message_can_be_regenerated_with_selected_model(): void
    {
        GeminiAgent::fake(['Regenerated answer.']);

        $user = User::factory()->create();
        $this->grantCredits($user);
        $conversation = $this->conversationFor($user, 'Existing chat');
        $userMessage = History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'user',
            'content' => 'Explain this again',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now()->subSecond(),
            'updated_at' => now()->subSecond(),
        ]);
        $assistantMessage = History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'assistant',
            'content' => 'Original answer.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('chat.regenerate', [$conversation, $assistantMessage]), [
                'model' => 'gemini-3.1-pro-preview',
            ]);

        $response->assertRedirect(route('chat.show', $conversation));

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'model' => 'gemini-3.1-pro-preview',
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Regenerated answer.',
        ]);

        $regeneratedMessage = History::where('content', 'Regenerated answer.')->firstOrFail();
        $this->assertSame('Gemini 3.1 Pro', json_decode($regeneratedMessage->meta, true)['model_label']);
        $this->assertEquals(3, History::where('conversation_id', $conversation->id)->count());
        GeminiAgent::assertPrompted(fn ($prompt) => $prompt->prompt === $userMessage->content
            && $prompt->model === 'gemini-3.1-pro-preview');
    }

    public function test_users_cannot_regenerate_another_users_message(): void
    {
        GeminiAgent::fake()->preventStrayPrompts();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->conversationFor($owner, 'Private chat');
        $assistantMessage = History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'agent' => GeminiAgent::class,
            'role' => 'assistant',
            'content' => 'Private answer.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
        ]);

        $this
            ->actingAs($intruder)
            ->post(route('chat.regenerate', [$conversation, $assistantMessage]), [
                'model' => 'gemini-3-flash-preview',
            ])
            ->assertNotFound();

        GeminiAgent::assertNeverPrompted();
    }

    public function test_chat_model_must_be_allowed(): void
    {
        GeminiAgent::fake()->preventStrayPrompts();

        $user = User::factory()->create();
        $this->grantCredits($user);

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'message' => 'Use a made up model',
                'model' => 'gemini-not-real',
            ]);

        $response->assertSessionHasErrors('model');
        $this->assertDatabaseCount('agent_conversations', 0);
        GeminiAgent::assertNeverPrompted();
    }

    public function test_user_cannot_chat_without_credits(): void
    {
        Bus::fake();
        GeminiAgent::fake()->preventStrayPrompts();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'message' => 'Hello',
                'model' => 'gemini-3-flash-preview',
            ])
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('agent_conversations', 0);
        GeminiAgent::assertNeverPrompted();
    }

    public function test_rate_limited_ai_errors_are_saved_as_friendly_assistant_messages(): void
    {
        Bus::fake();
        GeminiAgent::fake([
            fn () => throw RateLimitedException::forProvider('gemini'),
        ]);

        $user = User::factory()->create();
        $this->grantCredits($user);

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'message' => 'What is the latest news?',
                'model' => 'gemini-3-flash-preview',
            ]);

        $conversation = AgentConversation::query()->firstOrFail();

        $response->assertRedirect(route('chat.show', $conversation));
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Gemini is temporarily rate limited. Please wait a moment, then try again. If it keeps happening, switch to a lighter model like Flash-Lite or reduce regenerate/send attempts.',
        ]);
    }

    public function test_credit_ai_errors_are_saved_as_friendly_assistant_messages(): void
    {
        GeminiAgent::fake([
            fn () => throw InsufficientCreditsException::forProvider('gemini'),
        ]);

        $user = User::factory()->create();
        $this->grantCredits($user);
        $conversation = $this->conversationFor($user, 'Existing chat');
        $userMessage = History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'user',
            'content' => 'Try again',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now()->subSecond(),
            'updated_at' => now()->subSecond(),
        ]);
        $assistantMessage = History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'assistant',
            'content' => 'Original answer.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('chat.regenerate', [$conversation, $assistantMessage]), [
                'model' => 'gemini-3-flash-preview',
            ]);

        $response->assertRedirect(route('chat.show', $conversation));
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Gemini could not respond because the API project does not have enough credits or billing quota. Please check billing/quota, then try again.',
        ]);
        GeminiAgent::assertPrompted(fn ($prompt) => $prompt->prompt === $userMessage->content);
    }

    public function test_users_cannot_open_post_to_or_rename_another_users_conversation(): void
    {
        GeminiAgent::fake()->preventStrayPrompts();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->conversationFor($owner, 'Private chat');

        $this
            ->actingAs($intruder)
            ->get(route('chat.show', $conversation))
            ->assertNotFound();

        $this
            ->actingAs($intruder)
            ->post(route('chat.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Let me in',
                'model' => 'gemini-3-flash-preview',
            ])
            ->assertNotFound();

        $this
            ->actingAs($intruder)
            ->patch(route('chat.update', $conversation), [
                'title' => 'Renamed',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Private chat',
        ]);
        GeminiAgent::assertNeverPrompted();
    }

    public function test_conversation_title_can_be_renamed_by_owner(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Original title');

        $response = $this
            ->actingAs($user)
            ->patch(route('chat.update', $conversation), [
                'title' => '  Better title  ',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Better title',
        ]);
    }

    public function test_conversation_title_rename_is_validated(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Original title');

        $response = $this
            ->actingAs($user)
            ->patch(route('chat.update', $conversation), [
                'title' => str_repeat('a', 101),
            ]);

        $response->assertSessionHasErrors('title');

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    private function conversationFor(User $user, string $title): AgentConversation
    {
        return AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $title,
            'model' => 'gemini-3-flash-preview',
        ]);
    }

    private function grantCredits(User $user, int $credits = 10): void
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
