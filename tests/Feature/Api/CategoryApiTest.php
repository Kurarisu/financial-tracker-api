<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_and_create_categories(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
            'icon' => 'utensils',
            'color' => '#EF4444',
        ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories?type=expense');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/categories', [
                'name' => 'Salary',
                'type' => 'income',
                'icon' => 'money-bill',
                'color' => '#10B981',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Salary');

        $this->assertDatabaseHas('categories', ['name' => 'Salary', 'user_id' => $user->id]);
    }

    public function test_category_list_includes_monthly_spending_budget_usage_and_change(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(4));

        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Housing',
            'type' => 'expense',
        ]);
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 1800,
            'transaction_date' => now()->startOfMonth()->addDay(),
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 1200,
            'transaction_date' => now()->subMonthNoOverflow()->startOfMonth()->addDay(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/categories/{$category->id}/budget", [
                'monthly_budget' => 2600,
            ])
            ->assertOk()
            ->assertJsonPath('data.monthly_budget', 2600);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/categories?type=expense')
            ->assertOk()
            ->assertJsonPath('data.0.current_month_spent', 1800)
            ->assertJsonPath('data.0.monthly_budget', 2600)
            ->assertJsonPath('data.0.budget_used_percentage', 69.2)
            ->assertJsonPath('data.0.spending_change_percentage', 50);
    }

    public function test_category_budgets_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/categories/{$category->id}/budget", [
                'monthly_budget' => 500,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('category_budgets', 0);
    }
}
