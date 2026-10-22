<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptScanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_scan_and_confirm_receipt_with_private_attachment(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(output: "WARUNG MAKMUR\nITEM Rp 240.000\nTOTAL BAYAR Rp 125.000,00\n22/10/2026")]);
        $user = User::factory()->create();
        $account = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'initial_balance' => 500000, 'current_balance' => 500000]);
        $category = Category::create(['user_id' => $user->id, 'name' => 'Food', 'type' => 'expense']);

        $scan = $this->actingAs($user, 'sanctum')->post('/api/receipts/scan', ['receipt' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'))]);
        $scan->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.extracted.amount', 125000)->assertJsonPath('data.extracted.category_id', $category->id)->assertJsonPath('data.extracted.type', 'expense');
        $receiptToken = $scan->json('data.receipt_token');

        $created = $this->postJson('/api/transactions', [
            'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense',
            'amount' => 125000, 'transaction_date' => '2026-10-22', 'notes' => 'Reviewed receipt', 'receipt_token' => $receiptToken,
        ]);
        $created->assertCreated()->assertJsonPath('data.notes', 'Reviewed receipt');
        $transactionId = $created->json('data.id');
        $path = $created->json('data.attachment');
        $this->assertStringStartsWith('receipts/'.$user->id.'/transactions/', $path);
        Storage::disk('local')->assertExists($path);
        $this->get('/api/transactions/'.$transactionId.'/attachment')->assertOk();
    }

    public function test_receipt_scan_rejects_unauthenticated_and_invalid_uploads(): void
    {
        $this->withHeader('Accept', 'application/json')->post('/api/receipts/scan')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->post('/api/receipts/scan', ['receipt' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')])->assertUnprocessable();
    }

    public function test_user_cannot_retrieve_another_users_receipt(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::create(['user_id' => $owner->id, 'name' => 'Cash', 'type' => 'cash', 'initial_balance' => 100, 'current_balance' => 100]);
        $category = Category::create(['user_id' => $owner->id, 'name' => 'Food', 'type' => 'expense']);
        $path = 'receipts/'.$owner->id.'/transactions/receipt.jpg';
        Storage::disk('local')->put($path, 'test');
        $transaction = $owner->transactions()->create(['account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense', 'amount' => 1, 'transaction_date' => '2026-10-22', 'attachment' => $path]);
        $this->actingAs($other, 'sanctum')->get('/api/transactions/'.$transaction->id.'/attachment')->assertNotFound();
    }
}
