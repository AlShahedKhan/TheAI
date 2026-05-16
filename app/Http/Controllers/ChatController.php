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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
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
            'activeConversation' => $conversation?->only(['id', 'title', 'model']),
            'modelOptions' => $this->modelOptions(),
            'defaultModel' => $this->defaultModel(),
            'messages' => $conversation
                ? $conversation->messages()
                    ->where('user_id', $user->id)
                    ->latest('created_at')
                    ->limit(50)
                    ->get(['id', 'role', 'content', 'meta', 'created_at'])
                    ->map(fn (History $message) => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'created_at' => $message->created_at,
                        'model_label' => data_get(json_decode($message->meta, true), 'model_label'),
                    ])
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
            'model' => ['required', 'string', Rule::in($this->allowedModels())],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isNewConversation = empty($validated['conversation_id']);
        $conversation = $this->conversationFor($user, $validated['message'], $validated['model'], $validated['conversation_id'] ?? null);

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

        $this->createAssistantMessage($user, $conversation, $validated['message']);

        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }

    public function regenerate(Request $request, AgentConversation $conversation, History $message): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);

        abort_unless(
            $message->user_id === $user->id
                && $message->conversation_id === $conversation->id
                && $message->role === 'assistant',
            404,
        );

        $validated = $request->validate([
            'model' => ['required', 'string', Rule::in($this->allowedModels())],
        ]);

        $prompt = History::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('created_at', '<=', $message->created_at)
            ->latest('created_at')
            ->firstOrFail();

        $conversation->update(['model' => $validated['model']]);

        $this->createAssistantMessage($user, $conversation, $prompt->content);

        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }

    private function conversationFor(User $user, string $message, string $model, ?string $conversationId): AgentConversation
    {
        if ($conversationId) {
            $conversation = AgentConversation::query()
                ->where('user_id', $user->id)
                ->whereKey($conversationId)
                ->firstOrFail();

            $conversation->update(['model' => $model]);

            return $conversation;
        }

        return AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $this->titleFromMessage($message),
            'model' => $model,
        ]);
    }

    private function createAssistantMessage(User $user, AgentConversation $conversation, string $prompt): History
    {
        try {
            $response = GeminiAgent::make(user: $user, conversationId: $conversation->id)->prompt($prompt, model: $conversation->model);

            return History::create([
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
                'meta' => $this->messageMeta($response->meta, $conversation->model),
            ]);
        } catch (AiException $exception) {
            report($exception);

            return History::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'agent' => GeminiAgent::class,
                'role' => 'assistant',
                'content' => $this->friendlyAiError($exception),
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '{}',
                'meta' => $this->messageMeta(['error' => true], $conversation->model),
            ]);
        }
    }

    private function friendlyAiError(AiException $exception): string
    {
        return match (true) {
            $exception instanceof RateLimitedException => 'Gemini is temporarily rate limited. Please wait a moment, then try again. If it keeps happening, switch to a lighter model like Flash-Lite or reduce regenerate/send attempts.',
            $exception instanceof InsufficientCreditsException => 'Gemini could not respond because the API project does not have enough credits or billing quota. Please check billing/quota, then try again.',
            $exception instanceof ProviderOverloadedException => 'Gemini is overloaded right now. Please try again in a moment or switch to a faster model.',
            default => 'Gemini could not complete this request right now. Please try again, or switch models if the problem continues.',
        };
    }

    private function modelOptions(): array
    {
        return config('ai.providers.gemini.chat_models', []);
    }

    private function modelLabel(string $model): string
    {
        return collect($this->modelOptions())
            ->firstWhere('value', $model)['label'] ?? $model;
    }

    private function messageMeta(mixed $meta, string $model): string
    {
        return json_encode([
            ...json_decode(json_encode($meta), true),
            'model' => $model,
            'model_label' => $this->modelLabel($model),
        ]);
    }

    private function allowedModels(): array
    {
        return collect($this->modelOptions())
            ->pluck('value')
            ->all();
    }

    private function defaultModel(): string
    {
        return config('ai.providers.gemini.models.text.default', 'gemini-3-flash-preview');
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
