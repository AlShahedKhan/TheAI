<?php

namespace App\Ai\Tools;

use App\Models\History;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RetrievePreviousTranscripts implements Tool
{
    public function __construct(private User $user) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve previous conversation messages for the current user.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query')->toString();
        $limit = min(max($request->integer('limit', 10), 1), 25);

        $messages = History::query()
            ->where('user_id', $this->user->id)
            ->when($query !== '', fn ($builder) => $builder->where('content', 'like', "%{$query}%"))
            ->latest()
            ->limit($limit)
            ->get(['role', 'content', 'created_at'])
            ->reverse()
            ->values();

        return $messages->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'limit' => $schema->integer()->min(1)->max(25)->required(),
        ];
    }
}
