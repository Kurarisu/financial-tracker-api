<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_and_category_breakdown_reports_are_returned(): void
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

        $foodCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
            'color' => '#EF4444',
            'icon' => 'utensils',
        ]);

        $salaryCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
            'color' => '#10B981',
            'icon' => 'money-bill',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $salaryCategory->id,
                'type' => 'income',
                'amount' => 800,
                'transaction_date' => now()->format('Y-m-d'),
                'notes' => 'salary',
            ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $foodCategory->id,
                'type' => 'expense',
                'amount' => 200,
                'transaction_date' => now()->format('Y-m-d'),
                'notes' => 'lunch',
            ]);

        $monthlyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/monthly?month='.now()->month.'&year='.now()->year);

        $monthlyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_income', 800)
            ->assertJsonPath('data.total_expense', 200)
            ->assertJsonPath('data.net_savings', 600);

        $breakdownResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/category-breakdown?month='.now()->month.'&year='.now()->year.'&type=expense');

        $breakdownResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.breakdown.0.name', 'Food')
            ->assertJsonPath('data.breakdown.0.total_amount', 200);
    }
}
