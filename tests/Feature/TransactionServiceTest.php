<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_income_and_expense_mutations_to_an_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $service = app(TransactionService::class);

        $service->createTransaction([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 250,
            'transaction_date' => '2026-10-01',
            'notes' => 'Salary',
        ]);

        $service->createTransaction([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100,
            'transaction_date' => '2026-10-02',
            'notes' => 'Groceries',
        ]);

        $account->refresh();

        $this->assertSame(1150.0, (float) $account->current_balance);
    }

    public function test_moves_funds_between_accounts_during_transfer_and_reverts_them_on_delete(): void
    {
        $user = User::factory()->create();

        $source = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $target = Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $service = app(TransactionService::class);

        $transfer = $service->createTransaction([
            'user_id' => $user->id,
            'account_id' => $source->id,
            'to_account_id' => $target->id,
            'type' => 'transfer',
            'amount' => 500,
            'transaction_date' => '2026-10-01',
            'notes' => 'Top up e-wallet',
        ]);

        $source->refresh();
        $target->refresh();

        $this->assertSame(500.0, (float) $source->current_balance);
        $this->assertSame(1500.0, (float) $target->current_balance);

        $service->deleteTransaction($transfer);

        $source->refresh();
        $target->refresh();

        $this->assertSame(1000.0, (float) $source->current_balance);
        $this->assertSame(1000.0, (float) $target->current_balance);
    }
}
