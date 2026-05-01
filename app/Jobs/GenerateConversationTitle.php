<?php

namespace App\Jobs;

use App\Ai\Agents\ChatTitleAgent;
use App\Models\AgentConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $conversationId,
        public string $message,
        public string $fallbackTitle,
    ) {}

    public function handle(): void
    {
        $conversation = AgentConversation::query()->find($this->conversationId);

        if (! $conversation || $conversation->title !== $this->fallbackTitle) {
            return;
        }

        try {
            $response = ChatTitleAgent::make()->prompt($this->prompt());
            $title = $this->cleanTitle((string) $response);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($title === '') {
            return;
        }

        $conversation->update([
            'title' => $title,
        ]);
    }

    private function prompt(): string
    {
        return <<<PROMPT
Generate a concise chat title based on the user's first message.

Rules:
- Maximum 6 words.
- Return only the title.
- Do not wrap it in quotes.
- Do not include punctuation unless it is part of a name.

User message:
{$this->message}
PROMPT;
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        $title = trim($title, " \t\n\r\0\x0B\"'`");
        $title = rtrim($title, '.:;-');

        return Str::limit($title, 60, '');
    }
}
