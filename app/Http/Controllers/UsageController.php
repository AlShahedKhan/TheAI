<?php

namespace App\Http\Controllers;

use App\Models\History;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $messages = History::query()
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['usage', 'meta']);

        $chat = $this->chatUsage($messages);
        $video = $this->videoUsage($user);
        $budget = config('ai.providers.gemini.monthly_budget_usd');

        return Inertia::render('usage/index', [
            'chat' => $chat,
            'video' => $video,
            'budget' => [
                'configured' => is_numeric($budget),
                'amount' => is_numeric($budget) ? (float) $budget : null,
                'remaining' => is_numeric($budget) ? max((float) $budget - $chat['estimated_cost'], 0) : null,
            ],
            'links' => [
                'aiStudio' => 'https://aistudio.google.com/',
                'cloudBilling' => 'https://console.cloud.google.com/billing',
                'pricing' => 'https://ai.google.dev/pricing',
            ],
        ]);
    }

    private function chatUsage($messages): array
    {
        $pricing = config('ai.providers.gemini.chat_pricing_per_1m_tokens', []);

        $summary = [
            'messages' => $messages->count(),
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'estimated_cost' => 0.0,
            'by_model' => [],
        ];

        foreach ($messages as $message) {
            $usage = json_decode($message->usage, true) ?: [];
            $meta = json_decode($message->meta, true) ?: [];
            $model = $meta['model'] ?? config('ai.providers.gemini.models.text.default');
            $modelLabel = $meta['model_label'] ?? $model;
            $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
            $rates = $pricing[$model] ?? ['input' => 0, 'output' => 0];
            $cost = ($promptTokens / 1_000_000 * $rates['input'])
                + ($completionTokens / 1_000_000 * $rates['output']);

            $summary['prompt_tokens'] += $promptTokens;
            $summary['completion_tokens'] += $completionTokens;
            $summary['estimated_cost'] += $cost;

            $summary['by_model'][$model] ??= [
                'model' => $model,
                'label' => $modelLabel,
                'messages' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'estimated_cost' => 0.0,
            ];

            $summary['by_model'][$model]['messages']++;
            $summary['by_model'][$model]['prompt_tokens'] += $promptTokens;
            $summary['by_model'][$model]['completion_tokens'] += $completionTokens;
            $summary['by_model'][$model]['estimated_cost'] += $cost;
        }

        $summary['estimated_cost'] = round($summary['estimated_cost'], 6);
        $summary['by_model'] = collect($summary['by_model'])
            ->map(fn ($model) => [
                ...$model,
                'estimated_cost' => round($model['estimated_cost'], 6),
            ])
            ->values();

        return $summary;
    }

    private function videoUsage(User $user): array
    {
        $query = VideoGeneration::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            'total' => (clone $query)->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'processing' => (clone $query)->whereIn('status', ['pending', 'processing'])->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }
}
