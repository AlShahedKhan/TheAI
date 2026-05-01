<?php

namespace App\Http\Controllers;

use App\Ai\Agents\GeminiAgent;
use App\Jobs\GenerateConversationTitle;
use App\Models\AgentConversation;
use App\Models\History;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Responses\StructuredAgentResponse;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $request->boolean('new')
            ? null
            : AgentConversation::query()
                ->where('user_id', $user->id)
                ->latest('updated_at')
                ->first();

        return $this->renderChat($user, $conversation);
    }

    public function show(Request $request, AgentConversation $conversation): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);

        return $this->renderChat($user, $conversation);
    }

    public function update(Request $request, AgentConversation $conversation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $conversation->update([
            'title' => trim(preg_replace('/\s+/', ' ', $validated['title'])),
        ]);

        return back();
    }

    private function renderChat(User $user, ?AgentConversation $conversation): Response
    {
        $conversations = AgentConversation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('chat/index', [
            'conversations' => $conversations,
            'activeConversation' => $conversation?->only(['id', 'title']),
            'messages' => $conversation
                ? $conversation->messages()
                    ->where('user_id', $user->id)
                    ->latest('created_at')
                    ->limit(50)
                    ->get(['id', 'role', 'content', 'created_at'])
                    ->reverse()
                    ->values()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isNewConversation = empty($validated['conversation_id']);
        $conversation = $this->conversationFor($user, $validated['message'], $validated['conversation_id'] ?? null);

        if ($isNewConversation) {
            GenerateConversationTitle::dispatchAfterResponse(
                $conversation->id,
                $validated['message'],
                $conversation->title,
            );
        }

        History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'user',
            'content' => $validated['message'],
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
        ]);

        $response = GeminiAgent::make(user: $user, conversationId: $conversation->id)->prompt($validated['message']);

        History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => GeminiAgent::class,
            'role' => 'assistant',
            'content' => $response instanceof StructuredAgentResponse
                ? $response->toJson()
                : (string) $response,
            'attachments' => '[]',
            'tool_calls' => $response->toolCalls->toJson(),
            'tool_results' => $response->toolResults->toJson(),
            'usage' => json_encode($response->usage),
            'meta' => json_encode($response->meta),
        ]);

        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }

    private function conversationFor(User $user, string $message, ?string $conversationId): AgentConversation
    {
        if ($conversationId) {
            $conversation = AgentConversation::query()
                ->where('user_id', $user->id)
                ->whereKey($conversationId)
                ->firstOrFail();

            return $conversation;
        }

        return AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $this->titleFromMessage($message),
        ]);
    }

    private function titleFromMessage(string $message): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $message));

        if ($title === '') {
            return 'New chat';
        }

        return Str::limit($title, 60);
    }

    private function authorizeConversation(AgentConversation $conversation, User $user): void
    {
        abort_unless((int) $conversation->user_id === $user->id, 404);
    }
}
