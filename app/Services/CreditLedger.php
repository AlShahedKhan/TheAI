<?php

namespace App\Services;

use App\Models\CreditAdjustment;
use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditLedger
{
    public function userBalance(User $user): int
    {
        return $this->userPurchasedCredits($user)
            - $this->userSpentCredits($user);
    }

    public function siteBalance(): int
    {
        return $this->siteRechargedCredits()
            - $this->siteSoldCredits();
    }

    public function purchase(User $user, int $credits): ?CreditTransaction
    {
        return DB::transaction(function () use ($credits, $user) {
            if ($this->siteBalance() < $credits) {
                return null;
            }

            return CreditTransaction::create([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'type' => CreditTransaction::TYPE_USER_PURCHASE,
                'credits' => $credits,
                'amount' => $credits * CreditTransaction::BDT_PER_CREDIT,
                'currency' => 'BDT',
                'meta' => [
                    'mode' => 'dummy',
                    'rate' => CreditTransaction::BDT_PER_CREDIT,
                    'note' => 'Dummy user credit purchase.',
                ],
            ]);
        });
    }

    public function approvePurchase(CreditPurchaseRequest $request, User $admin): ?CreditTransaction
    {
        return DB::transaction(function () use ($admin, $request) {
            $request = CreditPurchaseRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status !== CreditPurchaseRequest::STATUS_PENDING) {
                return null;
            }

            if ($this->siteBalance() < $request->credits) {
                return null;
            }

            $transaction = CreditTransaction::create([
                'user_id' => $request->user_id,
                'created_by' => $admin->id,
                'type' => CreditTransaction::TYPE_USER_PURCHASE,
                'credits' => $request->credits,
                'amount' => $request->amount_bdt,
                'currency' => 'BDT',
                'meta' => [
                    'mode' => 'manual_payment',
                    'payment_request_id' => $request->id,
                    'payment_method' => $request->payment_method,
                    'transaction_id' => $request->transaction_id,
                ],
            ]);

            $request->update([
                'status' => CreditPurchaseRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $transaction;
        });
    }

    public function rejectPurchase(CreditPurchaseRequest $request, User $admin, string $reason): bool
    {
        return DB::transaction(function () use ($admin, $reason, $request) {
            $request = CreditPurchaseRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status !== CreditPurchaseRequest::STATUS_PENDING) {
                return false;
            }

            $request->update([
                'status' => CreditPurchaseRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return true;
        });
    }

    public function adjust(User $user, User $admin, int $credits, string $reason): CreditTransaction
    {
        return DB::transaction(function () use ($admin, $credits, $reason, $user) {
            if ($credits < 0 && $this->userBalance($user) < abs($credits)) {
                abort(422, 'Cannot remove more credits than the user has.');
            }

            $adjustment = CreditAdjustment::create([
                'user_id' => $user->id,
                'created_by' => $admin->id,
                'credits' => $credits,
                'reason' => $reason,
            ]);

            return CreditTransaction::create([
                'user_id' => $user->id,
                'created_by' => $admin->id,
                'type' => CreditTransaction::TYPE_ADMIN_ADJUSTMENT,
                'credits' => abs($credits),
                'amount' => 0,
                'currency' => 'BDT',
                'meta' => [
                    'adjustment_id' => $adjustment->id,
                    'direction' => $credits >= 0 ? 'add' : 'remove',
                    'reason' => $reason,
                ],
            ]);
        });
    }

    public function recharge(User $admin, float $amountUsd): CreditTransaction
    {
        return CreditTransaction::create([
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => (int) round($amountUsd * CreditTransaction::CREDITS_PER_USD),
            'amount' => round($amountUsd, 2),
            'currency' => 'USD',
            'meta' => [
                'mode' => 'dummy',
                'rate' => CreditTransaction::CREDITS_PER_USD,
                'note' => 'Dummy Google AI Studio recharge.',
            ],
        ]);
    }

    public function spend(User $user, int $credits, string $type, string $note): ?CreditTransaction
    {
        return DB::transaction(function () use ($credits, $note, $type, $user) {
            if ($this->userBalance($user) < $credits) {
                return null;
            }

            return CreditTransaction::create([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'type' => $type,
                'credits' => $credits,
                'amount' => 0,
                'currency' => 'BDT',
                'meta' => [
                    'mode' => 'dummy',
                    'note' => $note,
                ],
            ]);
        });
    }

    public function userPurchasedCredits(User $user): int
    {
        $purchased = (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
            ->sum('credits');

        $added = (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CreditTransaction::TYPE_ADMIN_ADJUSTMENT)
            ->where('meta->direction', 'add')
            ->sum('credits');

        return $purchased + $added;
    }

    public function userSpentCredits(User $user): int
    {
        $spent = (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [
                CreditTransaction::TYPE_CHAT_USAGE,
                CreditTransaction::TYPE_VIDEO_USAGE,
            ])
            ->sum('credits');

        $removed = (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CreditTransaction::TYPE_ADMIN_ADJUSTMENT)
            ->where('meta->direction', 'remove')
            ->sum('credits');

        return $spent + $removed;
    }

    public function siteRechargedCredits(): int
    {
        return (int) CreditTransaction::query()
            ->where('type', CreditTransaction::TYPE_ADMIN_RECHARGE)
            ->sum('credits');
    }

    public function siteSoldCredits(): int
    {
        return (int) CreditTransaction::query()
            ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
            ->sum('credits');
    }
}
