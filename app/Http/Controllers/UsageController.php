<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\History;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\CreditLedger;
use Illuminate\Http\RedirectResponse;
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
            'credits' => $this->creditSummary($user),
            'budget' => [
                'configured' => is_numeric($budget),
                'amount' => is_numeric($budget) ? (float) $budget : null,
                'remaining' => is_numeric($budget) ? max((float) $budget - $chat['estimated_cost'], 0) : null,
            ],
            'links' => $user->is_admin ? [
                'aiStudio' => 'https://aistudio.google.com/',
                'cloudBilling' => 'https://console.cloud.google.com/billing',
                'pricing' => 'https://ai.google.dev/pricing',
            ] : null,
        ]);
    }

    public function purchaseCredits(Request $request, CreditLedger $credits): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $creditAmount = (int) $validated['credits'];

        if (! $credits->purchase($user, $creditAmount)) {
            return back()->withErrors([
                'credits' => 'The website does not have enough available credits. Please ask the admin to recharge first.',
            ]);
        }

        return back()->with('success', "{$creditAmount} credits added to your account.");
    }

    public function rechargeCredits(Request $request, CreditLedger $credits): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->is_admin, 403);

        $validated = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);

        $amountUsd = round((float) $validated['amount_usd'], 2);
        $transaction = $credits->recharge($user, $amountUsd);

        return back()->with('success', "{$transaction->credits} website credits added.");
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

    private function creditSummary(User $user): array
    {
        $ledger = app(CreditLedger::class);
        $adminCredits = $ledger->siteRechargedCredits();
        $soldCredits = $ledger->siteSoldCredits();
        $userPurchasedCredits = $ledger->userPurchasedCredits($user);
        $userSpentCredits = $ledger->userSpentCredits($user);

        return [
            'is_admin' => $user->is_admin,
            'rates' => [
                'credits_per_usd' => CreditTransaction::CREDITS_PER_USD,
                'bdt_per_credit' => CreditTransaction::BDT_PER_CREDIT,
                'chat_message_cost' => CreditTransaction::CHAT_MESSAGE_COST,
                'video_generation_cost' => CreditTransaction::VIDEO_GENERATION_COST,
            ],
            'user' => [
                'balance' => $userPurchasedCredits - $userSpentCredits,
                'purchased' => $userPurchasedCredits,
                'used' => $userSpentCredits,
                'spent_bdt' => (float) CreditTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
                    ->sum('amount'),
                'recent' => CreditTransaction::query()
                    ->where('user_id', $user->id)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'type', 'credits', 'amount', 'currency', 'created_at'])
                    ->map(fn (CreditTransaction $transaction) => $this->serializeCreditTransaction($transaction)),
            ],
            'site' => [
                'admin_recharged' => $user->is_admin ? $adminCredits : 0,
                'sold' => $user->is_admin ? $soldCredits : 0,
                'available' => $user->is_admin ? $adminCredits - $soldCredits : 0,
                'recharged_usd' => $user->is_admin ? (float) CreditTransaction::query()
                    ->where('type', CreditTransaction::TYPE_ADMIN_RECHARGE)
                    ->sum('amount') : 0,
                'sales_bdt' => $user->is_admin ? (float) CreditTransaction::query()
                    ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
                    ->sum('amount') : 0,
                'recent' => $user->is_admin
                    ? CreditTransaction::query()
                        ->with('user:id,name,email')
                        ->latest()
                        ->limit(8)
                        ->get(['id', 'user_id', 'type', 'credits', 'amount', 'currency', 'created_at'])
                        ->map(fn (CreditTransaction $transaction) => $this->serializeCreditTransaction($transaction, includeUser: true))
                    : [],
            ],
        ];
    }

    private function serializeCreditTransaction(CreditTransaction $transaction, bool $includeUser = false): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'credits' => $transaction->credits,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'created_at' => $transaction->created_at?->toISOString(),
            'user' => $includeUser && $transaction->user ? [
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ] : null,
        ];
    }
}
