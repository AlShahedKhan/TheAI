<?php

namespace Tests\Feature;

use App\Ai\Agents\ChatTitleAgent;
use App\Ai\Agents\GeminiAgent;
use App\Jobs\GenerateConversationTitle;
use App\Models\AgentConversation;
use App\Models\History;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
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
                ->has('conversations', 0)
                ->has('messages', 0)
            );

        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function test_first_message_creates_conversation_with_auto_title(): void
    {
        Bus::fake();
        GeminiAgent::fake([
            ['feedback' => 'Hello from Gemini.', 'score' => 10],
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'message' => '   Build a launch checklist for Friday   ',
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
            'content' => json_encode(['feedback' => 'Hello from Gemini.', 'score' => 10]),
        ]);
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
        GeminiAgent::fake([
            ['feedback' => 'Follow up answer.', 'score' => 8],
        ]);

        $user = User::factory()->create();
        $conversation = $this->conversationFor($user, 'Existing chat');
        $otherConversation = $this->conversationFor($user, 'Other chat');

        $response = $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Continue this chat',
            ]);

        $response->assertRedirect(route('chat.show', $conversation));

        $this->assertDatabaseCount('agent_conversations', 2);
        $this->assertEquals(2, History::where('conversation_id', $conversation->id)->count());
        $this->assertEquals(0, History::where('conversation_id', $otherConversation->id)->count());
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
        ]);
    }
}
