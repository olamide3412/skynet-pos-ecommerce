<?php

namespace App\Services\Referral;

use App\Models\Affiliate;
use App\Models\AffiliateWalletTransaction;
use App\Models\AffiliateWithdrawal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AffiliateWalletService
{
    /**
     * Credit the affiliate wallet with a new transaction ledger record.
     */
    public function credit(
        Affiliate $affiliate,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        string $description
    ): AffiliateWalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Credit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($affiliate, $amount, $sourceType, $sourceId, $description) {
            /** @var Affiliate $locked */
            $locked = Affiliate::lockForUpdate()->find($affiliate->id);

            $newBalance = round($locked->wallet_balance + $amount, 2);
            $newTotalEarned = round($locked->total_earned + $amount, 2);

            $locked->update([
                'wallet_balance' => $newBalance,
                'total_earned'   => $newTotalEarned,
            ]);

            return AffiliateWalletTransaction::create([
                'affiliate_id'  => $locked->id,
                'type'          => 'credit',
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'source_type'   => $sourceType,
                'source_id'     => $sourceId,
                'description'   => $description,
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Hold funds from the affiliate wallet when a withdrawal request is submitted.
     */
    public function holdForWithdrawal(Affiliate $affiliate, float $amount, AffiliateWithdrawal $withdrawal): AffiliateWalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Withdrawal amount must be greater than zero.");
        }

        return DB::transaction(function () use ($affiliate, $amount, $withdrawal) {
            /** @var Affiliate $locked */
            $locked = Affiliate::lockForUpdate()->find($affiliate->id);

            if ($locked->wallet_balance < $amount) {
                throw new InvalidArgumentException("Insufficient available balance for this withdrawal.");
            }

            $newBalance = round($locked->wallet_balance - $amount, 2);

            $locked->update([
                'wallet_balance' => $newBalance,
            ]);

            return AffiliateWalletTransaction::create([
                'affiliate_id'  => $locked->id,
                'type'          => 'debit',
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'source_type'   => 'withdrawal_hold',
                'source_id'     => $withdrawal->id,
                'description'   => "Withdrawal request #{$withdrawal->id} hold",
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Refund held funds back to wallet if admin rejects a withdrawal request.
     */
    public function refundWithdrawal(Affiliate $affiliate, AffiliateWithdrawal $withdrawal, string $reason): AffiliateWalletTransaction
    {
        return DB::transaction(function () use ($affiliate, $withdrawal, $reason) {
            /** @var Affiliate $locked */
            $locked = Affiliate::lockForUpdate()->find($affiliate->id);

            $newBalance = round($locked->wallet_balance + $withdrawal->amount, 2);

            $locked->update([
                'wallet_balance' => $newBalance,
            ]);

            return AffiliateWalletTransaction::create([
                'affiliate_id'  => $locked->id,
                'type'          => 'credit',
                'amount'        => $withdrawal->amount,
                'balance_after' => $newBalance,
                'source_type'   => 'withdrawal_refund',
                'source_id'     => $withdrawal->id,
                'description'   => "Refund for rejected withdrawal #{$withdrawal->id}: {$reason}",
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Mark withdrawal as finalized/paid, updating total_withdrawn.
     */
    public function finalizeWithdrawal(Affiliate $affiliate, AffiliateWithdrawal $withdrawal): void
    {
        DB::transaction(function () use ($affiliate, $withdrawal) {
            /** @var Affiliate $locked */
            $locked = Affiliate::lockForUpdate()->find($affiliate->id);
            $locked->update([
                'total_withdrawn' => round($locked->total_withdrawn + $withdrawal->amount, 2),
            ]);
        });
    }

    /**
     * Debit wallet if a previously credited commission has to be clawed back (e.g. order returned/cancelled).
     */
    public function debit(
        Affiliate $affiliate,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        string $description
    ): AffiliateWalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Debit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($affiliate, $amount, $sourceType, $sourceId, $description) {
            /** @var Affiliate $locked */
            $locked = Affiliate::lockForUpdate()->find($affiliate->id);

            $newBalance = round(max(0, $locked->wallet_balance - $amount), 2);
            $newTotalEarned = round(max(0, $locked->total_earned - $amount), 2);

            $locked->update([
                'wallet_balance' => $newBalance,
                'total_earned'   => $newTotalEarned,
            ]);

            return AffiliateWalletTransaction::create([
                'affiliate_id'  => $locked->id,
                'type'          => 'debit',
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'source_type'   => $sourceType,
                'source_id'     => $sourceId,
                'description'   => $description,
                'created_at'    => now(),
            ]);
        });
    }
}

