<?php

namespace App\Http\Controllers;

use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\History;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\CreditLedger;
use App\Services\CreditSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CreditLedger $ledger, CreditSettings $settings): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'creditBalance' => $ledger->userBalance($user),
            'settings' => $settings->all(),
            'userSummary' => [
                'pending_payments' => CreditPurchaseRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', CreditPurchaseRequest::STATUS_PENDING)
                    ->count(),
                'chat_replies' => History::query()
                    ->where('user_id', $user->id)
                    ->where('role', 'assistant')
                    ->count(),
                'videos' => VideoGeneration::query()
                    ->where('user_id', $user->id)
                    ->count(),
                'credits_used' => $ledger->userSpentCredits($user),
            ],
            'adminSummary' => $user->is_admin ? $this->adminSummary($ledger) : null,
        ]);
    }

    private function adminSummary(CreditLedger $ledger): array
    {
        $estimatedCost = $this->estimatedGoogleCost();
        $revenue = (float) CreditTransaction::query()
            ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
            ->sum('amount');

        return [
            'users' => User::query()->count(),
            'pending_payments' => CreditPurchaseRequest::query()
                ->where('status', CreditPurchaseRequest::STATUS_PENDING)
                ->count(),
            'credits_sold' => $ledger->siteSoldCredits(),
            'site_available' => $ledger->siteBalance(),
            'revenue_bdt' => $revenue,
            'estimated_google_cost_usd' => $estimatedCost,
            'profit_estimate_bdt' => round($revenue - ($estimatedCost * 120), 2),
            'videos_today' => VideoGeneration::query()
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
        ];
    }

    private function estimatedGoogleCost(): float
    {
        $pricing = config('ai.providers.gemini.chat_pricing_per_1m_tokens', []);

        $chatCost = History::query()
            ->where('role', 'assistant')
            ->get(['usage', 'meta'])
            ->sum(function (History $message) use ($pricing) {
                $usage = json_decode($message->usage, true) ?: [];
                $meta = json_decode($message->meta, true) ?: [];
                $model = $meta['model'] ?? config('ai.providers.gemini.models.text.default');
                $rates = $pricing[$model] ?? ['input' => 0, 'output' => 0];

                return (((int) ($usage['prompt_tokens'] ?? 0)) / 1_000_000 * $rates['input'])
                    + (((int) ($usage['completion_tokens'] ?? 0)) / 1_000_000 * $rates['output']);
            });

        return round($chatCost, 6);
    }
}
