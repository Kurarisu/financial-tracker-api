<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_total_balance_and_current_month_cashflow(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'initial_balance' => 500,
            'current_balance' => 500,
        ]);

        $incomeCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
            'color' => '#10B981',
            'icon' => 'money-bill',
        ]);

        $expenseCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
            'color' => '#EF4444',
            'icon' => 'utensils',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => 1,
                'category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 300,
                'transaction_date' => now()->format('Y-m-d'),
                'notes' => 'March income',
            ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => 1,
                'category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 120,
                'transaction_date' => now()->format('Y-m-d'),
                'notes' => 'Lunch',
            ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_net_balance', 1680)
            ->assertJsonPath('data.cashflow.current_month_income', 300)
            ->assertJsonPath('data.cashflow.current_month_expense', 120)
            ->assertJsonPath('data.cashflow.net_cash_flow', 180);
    }
}
