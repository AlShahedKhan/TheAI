<?php

namespace App\Http\Controllers;

use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\CreditSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditsController extends Controller
{
    public function index(Request $request, CreditLedger $ledger, CreditSettings $settings): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('credits/index', [
            'balance' => $ledger->userBalance($user),
            'settings' => $settings->all(),
            'paymentMethods' => ['bkash', 'nagad'],
            'requests' => CreditPurchaseRequest::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (CreditPurchaseRequest $purchase) => $this->serializePurchaseRequest($purchase)),
            'transactions' => CreditTransaction::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(15)
                ->get(['id', 'type', 'credits', 'amount', 'currency', 'meta', 'created_at'])
                ->map(fn (CreditTransaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'credits' => $transaction->credits,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'direction' => data_get($transaction->meta, 'direction', in_array($transaction->type, [CreditTransaction::TYPE_CHAT_USAGE, CreditTransaction::TYPE_VIDEO_USAGE], true) ? 'remove' : 'add'),
                    'created_at' => $transaction->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Request $request, CreditSettings $settings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->is_suspended) {
            return back()->withErrors([
                'credits' => 'Your account is suspended. Please contact support.',
            ]);
        }

        $availablePackages = $settings->packages();

        $validated = $request->validate([
            'credits' => ['required', 'integer', Rule::in($availablePackages)],
            'payment_method' => ['required', 'string', Rule::in(['bkash', 'nagad'])],
            'transaction_id' => ['required', 'string', 'max:100', 'unique:credit_purchase_requests,transaction_id'],
            'sender_number' => ['required', 'string', 'max:30'],
        ]);

        $credits = (int) $validated['credits'];
        $bdtPerCredit = $settings->integer('bdt_per_credit', CreditSettings::DEFAULT_BDT_PER_CREDIT);

        CreditPurchaseRequest::create([
            'user_id' => $user->id,
            'credits' => $credits,
            'amount_bdt' => $credits * $bdtPerCredit,
            'payment_method' => $validated['payment_method'],
            'transaction_id' => trim($validated['transaction_id']),
            'sender_number' => trim($validated['sender_number']),
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Payment request submitted. Admin approval is required before credits are added.');
    }

    private function serializePurchaseRequest(CreditPurchaseRequest $purchase): array
    {
        return [
            'id' => $purchase->id,
            'credits' => $purchase->credits,
            'amount_bdt' => (float) $purchase->amount_bdt,
            'payment_method' => $purchase->payment_method,
            'transaction_id' => $purchase->transaction_id,
            'sender_number' => $purchase->sender_number,
            'status' => $purchase->status,
            'rejection_reason' => $purchase->rejection_reason,
            'created_at' => $purchase->created_at?->toISOString(),
        ];
    }
}
