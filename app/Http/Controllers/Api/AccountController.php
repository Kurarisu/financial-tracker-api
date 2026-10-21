<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->ensureDefaultCashAccount();

        $accounts = $request->user()
            ->accounts()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'cash' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $breakdown = [
            'bank' => 0,
            'ewallet' => 0,
            'cash' => 0,
            'investment' => 0,
            'other' => 0,
        ];

        foreach ($accounts as $account) {
            $type = $account->type;
            if (! array_key_exists($type, $breakdown)) {
                $breakdown[$type] = 0;
            }

            $breakdown[$type] += (float) $account->current_balance;
        }

        $totalNetBalance = array_sum($breakdown);

        return response()->json([
            'success' => true,
            'message' => 'Accounts retrieved successfully.',
            'data' => [
                'total_net_balance' => (float) $totalNetBalance,
                'breakdown' => $breakdown,
                'accounts' => $accounts,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:bank,ewallet,cash,investment,other'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = $request->user()->accounts()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'account_number' => $validated['account_number'] ?? null,
            'initial_balance' => $validated['initial_balance'] ?? 0,
            'current_balance' => $validated['initial_balance'] ?? 0,
            'color' => $validated['color'] ?? '#3B82F6',
            'icon' => $validated['icon'] ?? 'wallet',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account,
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        abort_unless($account->user_id === auth()->id(), 404);

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        abort_unless($account->user_id === auth()->id(), 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:bank,ewallet,cash,investment,other'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $account->fill($validated);
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data' => $account->fresh(),
        ]);
    }

    public function destroy(Account $account): JsonResponse
    {
        abort_unless($account->user_id === auth()->id(), 404);

        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account archived successfully.',
        ]);
    }

    public function reconcile(Request $request, Account $account): JsonResponse
    {
        abort_unless($account->user_id === auth()->id(), 404);

        $validated = $request->validate([
            'current_balance' => ['required', 'numeric'],
        ]);

        $account->current_balance = $validated['current_balance'];
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Account balance reconciled successfully.',
            'data' => $account->fresh(),
        ]);
    }

    public function setOpeningBalance(Request $request, Account $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->type === 'cash', 422, 'Opening balance setup is only available for the cash account.');

        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        if ($account->transactions()->exists() || $account->incomingTransfers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The opening balance can only be set before transactions are added.',
            ], 409);
        }

        $account->initial_balance = $validated['initial_balance'];
        $account->current_balance = $validated['initial_balance'];
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Cash opening balance saved successfully.',
            'data' => $account->fresh(),
        ]);
    }
}
