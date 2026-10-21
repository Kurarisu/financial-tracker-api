<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function createTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $account = $this->resolveSourceAccount($data);
            $toAccount = $this->resolveDestinationAccount($data);

            $transaction = Transaction::create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'account_id' => $account->id,
                'to_account_id' => $toAccount?->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'] ?? 'expense',
                'amount' => (float) ($data['amount'] ?? 0),
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'attachment' => $data['attachment'] ?? null,
            ]);

            $this->applyBalanceDelta($transaction);

            return $transaction->fresh();
        });
    }

    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->applyBalanceDelta($transaction, reverse: true);

            $transaction->fill([
                'user_id' => $data['user_id'] ?? $transaction->user_id,
                'account_id' => $data['account_id'] ?? $transaction->account_id,
                'to_account_id' => array_key_exists('to_account_id', $data) ? $data['to_account_id'] : $transaction->to_account_id,
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $transaction->category_id,
                'type' => $data['type'] ?? $transaction->type,
                'amount' => isset($data['amount']) ? (float) $data['amount'] : (float) $transaction->amount,
                'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $transaction->notes,
                'attachment' => array_key_exists('attachment', $data) ? $data['attachment'] : $transaction->attachment,
            ]);

            $transaction->save();
            $this->applyBalanceDelta($transaction);

            return $transaction->fresh();
        });
    }

    public function deleteTransaction(Transaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction) {
            $this->applyBalanceDelta($transaction, reverse: true);

            return $transaction->delete();
        });
    }

    protected function resolveSourceAccount(array $data): Account
    {
        $accountId = $data['account_id'] ?? null;

        if (! $accountId) {
            throw new \InvalidArgumentException('Source account_id is required for a transaction.');
        }

        return Account::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function resolveDestinationAccount(array $data): ?Account
    {
        $accountId = $data['to_account_id'] ?? null;

        if (! $accountId) {
            return null;
        }

        return Account::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function applyBalanceDelta(Transaction $transaction, bool $reverse = false): void
    {
        $multiplier = $reverse ? -1 : 1;
        $amount = (float) $transaction->amount;

        $sourceAccount = Account::query()
            ->whereKey($transaction->account_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $reverse && in_array($transaction->type, ['expense', 'transfer'], true)
            && (float) $sourceAccount->current_balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Saldo akun sumber tidak mencukupi untuk transaksi ini.',
            ]);
        }

        switch ($transaction->type) {
            case 'income':
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance + ($amount * $multiplier);
                $sourceAccount->save();
                break;

            case 'expense':
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance - ($amount * $multiplier);
                $sourceAccount->save();
                break;

            case 'transfer':
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance - ($amount * $multiplier);
                $sourceAccount->save();

                if ($transaction->to_account_id) {
                    $toAccount = Account::query()
                        ->whereKey($transaction->to_account_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $toAccount->current_balance = (float) $toAccount->current_balance + ($amount * $multiplier);
                    $toAccount->save();
                }
                break;
        }
    }
}
