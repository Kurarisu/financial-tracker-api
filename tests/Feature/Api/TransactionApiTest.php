<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_transactions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
            'icon' => 'money-bill',
            'color' => '#10B981',
        ]);

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'income',
                'amount' => 250,
                'transaction_date' => '2026-10-01',
                'notes' => 'Monthly salary',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'income');

        $account->refresh();
        $this->assertSame(1250.0, (float) $account->current_balance);

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/transactions?type=income');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_transfer_is_rejected_when_source_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $source = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        $destination = Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'account_id' => $source->id,
                'to_account_id' => $destination->id,
                'type' => 'transfer',
                'amount' => 10000,
                'transaction_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0.0, (float) $source->fresh()->current_balance);
        $this->assertSame(0.0, (float) $destination->fresh()->current_balance);
        $this->assertSame(0, $user->transactions()->count());
    }

    public function test_transaction_rejects_accounts_and_categories_outside_user_scope_or_type(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = Account::create([
            'user_id' => $otherUser->id,
            'name' => 'Other bank',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $incomeCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
        ]);
        $foreignCategory = Category::create([
            'user_id' => $otherUser->id,
            'name' => 'Other salary',
            'type' => 'income',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'account_id' => $foreignAccount->id,
                'category_id' => $incomeCategory->id,
                'type' => 'expense',
                'amount' => 100,
                'transaction_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'category_id']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'type' => 'expense',
                'amount' => 100,
                'transaction_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $foreignCategory->id,
                'type' => 'income',
                'amount' => 100,
                'transaction_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->assertSame(0, $user->transactions()->count());
    }

    public function test_partial_update_keeps_existing_account_and_category_and_checks_new_amount(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $transaction = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 100,
                'transaction_date' => '2026-10-01',
            ])
            ->assertCreated()
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/transactions/'.$transaction['id'], ['amount' => 200])
            ->assertOk()
            ->assertJsonPath('data.amount', '200.00');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/transactions/'.$transaction['id'], ['amount' => 1000.01])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(800.0, (float) $account->fresh()->current_balance);
        $this->assertSame(200.0, (float) $user->transactions()->findOrFail($transaction['id'])->amount);
    }

    public function test_expense_is_rejected_when_source_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 100,
                'transaction_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0.0, (float) $account->fresh()->current_balance);
        $this->assertSame(0, $user->transactions()->count());
    }
}
