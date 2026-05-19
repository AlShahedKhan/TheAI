<?php

namespace App\Services;

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
        return (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CreditTransaction::TYPE_USER_PURCHASE)
            ->sum('credits');
    }

    public function userSpentCredits(User $user): int
    {
        return (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [
                CreditTransaction::TYPE_CHAT_USAGE,
                CreditTransaction::TYPE_VIDEO_USAGE,
            ])
            ->sum('credits');
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
