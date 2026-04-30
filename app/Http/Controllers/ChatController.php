<?php

namespace App\Http\Controllers;

use App\Ai\Agents\GeminiAgent;
use App\Models\History;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Responses\StructuredAgentResponse;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('chat/index', [
            'messages' => History::query()
                ->where('user_id', $user?->id)
                ->latest()
                ->limit(50)
                ->get(['id', 'role', 'content', 'created_at'])
                ->reverse()
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $conversationId = $this->conversationIdFor($user);

        History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
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

        $response = GeminiAgent::make(user: $user)->prompt($validated['message']);

        History::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
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

        return back();
    }

    private function conversationIdFor(User $user): string
    {
        $conversation = DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->where('title', 'Gemini Chat')
            ->first();

        if ($conversation) {
            return $conversation->id;
        }

        $id = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'title' => 'Gemini Chat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
