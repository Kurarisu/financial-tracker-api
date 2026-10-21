<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_accounts_with_net_balance(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'current_balance' => 1500,
        ]);

        Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'current_balance' => 250,
        ]);

        $token = $user->createToken('api-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_net_balance', 1750)
            ->assertJsonPath('data.breakdown.bank', 1500)
            ->assertJsonPath('data.breakdown.ewallet', 250);
    }

    public function test_authenticated_user_can_create_account(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/accounts', [
                'name' => 'Mandiri',
                'type' => 'bank',
                'account_number' => '130-000',
                'initial_balance' => 3000,
                'color' => '#0066CC',
                'icon' => 'landmark',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mandiri');

        $this->assertDatabaseHas('accounts', ['name' => 'Mandiri', 'user_id' => $user->id]);
    }
}
