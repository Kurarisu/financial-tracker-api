<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_endpoint_returns_csv_for_transactions(): void
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
            'icon' => 'utensils',
            'color' => '#EF4444',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 150,
                'transaction_date' => '2026-10-01',
                'notes' => 'Lunch',
            ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/export?month=10&year=2026&format=csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->baseResponse->getContent();

        $this->assertStringContainsString('type,amount,transaction_date', $content);
        $this->assertStringContainsString('expense', $content);
    }
}
