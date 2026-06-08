<?php

namespace App\Http\Controllers;

use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\History;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\CreditLedger;
use App\Services\CreditSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(CreditLedger $ledger, CreditSettings $settings): Response
    {
        $estimatedCost = $this->estimatedGoogleCost();
        $revenue = (float) CreditTransaction::query()
            ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
            ->sum('amount');

        return Inertia::render('admin/index', [
            'metrics' => [
                'users' => User::query()->count(),
                'pending_payments' => CreditPurchaseRequest::query()->where('status', CreditPurchaseRequest::STATUS_PENDING)->count(),
                'credits_sold' => $ledger->siteSoldCredits(),
                'site_available' => $ledger->siteBalance(),
                'revenue_bdt' => $revenue,
                'estimated_google_cost_usd' => $estimatedCost,
                'profit_estimate_bdt' => round($revenue - ($estimatedCost * 120), 2),
                'videos_today' => VideoGeneration::query()->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            ],
            'settings' => $settings->all(),
            'recentPayments' => $this->paymentQuery()->limit(8)->get()->map(fn (CreditPurchaseRequest $purchase) => $this->serializePurchase($purchase)),
            'recentUsers' => $this->userQuery()->limit(8)->get()->map(fn (User $user) => $this->serializeUser($user, $ledger)),
            'links' => [
                'aiStudioSpend' => 'https://aistudio.google.com/spend',
                'cloudBilling' => 'https://console.cloud.google.com/billing',
            ],
        ]);
    }

    public function payments(): Response
    {
        return Inertia::render('admin/payments', [
            'payments' => $this->paymentQuery()
                ->paginate(20)
                ->through(fn (CreditPurchaseRequest $purchase) => $this->serializePurchase($purchase)),
        ]);
    }

    public function approvePayment(Request $request, CreditPurchaseRequest $purchase, CreditLedger $ledger): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $ledger->approvePurchase($purchase, $admin)) {
            return back()->withErrors([
                'payment' => 'This payment cannot be approved. Check status and website credit pool.',
            ]);
        }

        return back()->with('success', 'Payment approved and credits added.');
    }

    public function rejectPayment(Request $request, CreditPurchaseRequest $purchase, CreditLedger $ledger): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $ledger->rejectPurchase($purchase, $admin, $validated['rejection_reason'])) {
            return back()->withErrors([
                'payment' => 'Only pending payments can be rejected.',
            ]);
        }

        return back()->with('success', 'Payment rejected.');
    }

    public function users(CreditLedger $ledger): Response
    {
        return Inertia::render('admin/users', [
            'users' => $this->userQuery()
                ->paginate(20)
                ->through(fn (User $user) => $this->serializeUser($user, $ledger)),
        ]);
    }

    public function adjustUser(Request $request, User $user, CreditLedger $ledger): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'credits' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $ledger->adjust($user, $admin, (int) $validated['credits'], $validated['reason']);

        return back()->with('success', 'Credit adjustment saved.');
    }

    public function suspendUser(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot suspend your own account.');

        $validated = $request->validate([
            'is_suspended' => ['required', 'boolean'],
        ]);

        $user->update(['is_suspended' => $validated['is_suspended']]);

        return back()->with('success', $user->is_suspended ? 'User suspended.' : 'User restored.');
    }

    public function settings(CreditSettings $settings): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => $settings->all(),
        ]);
    }

    public function updateSettings(Request $request, CreditSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'bdt_per_credit' => ['required', 'integer', 'min:1', 'max:1000'],
            'chat_message_cost' => ['required', 'integer', 'min:1', 'max:10000'],
            'video_generation_cost' => ['required', 'integer', 'min:1', 'max:100000'],
            'daily_spend_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'daily_video_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'payment_number' => ['required', 'string', 'max:30'],
            'packages' => ['required', 'string', 'max:200'],
        ]);

        $packages = collect(explode(',', $validated['packages']))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $settings->update([
            'bdt_per_credit' => (int) $validated['bdt_per_credit'],
            'chat_message_cost' => (int) $validated['chat_message_cost'],
            'video_generation_cost' => (int) $validated['video_generation_cost'],
            'daily_spend_limit' => (int) $validated['daily_spend_limit'],
            'daily_video_limit' => (int) $validated['daily_video_limit'],
            'payment_number' => $validated['payment_number'],
            'packages' => $packages ?: [100, 500, 1000],
        ]);

        return back()->with('success', 'Credit settings updated.');
    }

    public function recharge(Request $request, CreditLedger $ledger): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);

        $ledger->recharge($admin, (float) $validated['amount_usd']);

        return back()->with('success', 'Website credit pool recharged.');
    }

    private function paymentQuery()
    {
        return CreditPurchaseRequest::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->latest();
    }

    private function userQuery()
    {
        return User::query()
            ->withCount([
                'creditTransactions as total_transactions',
                'creditTransactions as total_purchases' => fn ($query) => $query->where('type', CreditTransaction::TYPE_USER_PURCHASE),
            ])
            ->latest();
    }

    private function serializePurchase(CreditPurchaseRequest $purchase): array
    {
        return [
            'id' => $purchase->id,
            'user' => $purchase->user?->only(['id', 'name', 'email']),
            'credits' => $purchase->credits,
            'amount_bdt' => (float) $purchase->amount_bdt,
            'payment_method' => $purchase->payment_method,
            'transaction_id' => $purchase->transaction_id,
            'sender_number' => $purchase->sender_number,
            'status' => $purchase->status,
            'reviewer' => $purchase->reviewer?->only(['id', 'name', 'email']),
            'reviewed_at' => $purchase->reviewed_at?->toISOString(),
            'rejection_reason' => $purchase->rejection_reason,
            'created_at' => $purchase->created_at?->toISOString(),
        ];
    }

    private function serializeUser(User $user, CreditLedger $ledger): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_suspended' => $user->is_suspended,
            'balance' => $ledger->userBalance($user),
            'total_transactions' => $user->total_transactions,
            'total_purchases' => $user->total_purchases,
            'created_at' => $user->created_at?->toISOString(),
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
