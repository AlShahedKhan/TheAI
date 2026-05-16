<?php

namespace App\Ai\Agents;

use App\Models\History;
use App\Models\User;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
class GeminiAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(public User $user, public ?string $conversationId = null) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant. If the user asks for current, recent, latest, today, prices, schedules, availability, news, or other time-sensitive information, explain when the answer may need live verification instead of guessing.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return History::where('user_id', $this->user->id)
            ->when($this->conversationId, fn ($query) => $query->where('conversation_id', $this->conversationId))
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return new Message($message->role, $message->content);
            })->all();
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<int, never>
     */
    public function tools(): iterable
    {
        return [];
    }
}
