<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->ensureDefaultCashAccount();

        $accounts = $user->accounts()->where('is_active', true)->get();
        $totalNetBalance = (float) $accounts->sum('current_balance');

        $now = now();
        $currentMonthIncome = (float) $user->transactions()
            ->where('type', 'income')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $currentMonthExpense = (float) $user->transactions()
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $recentTransactions = $user->transactions()
            ->with(['account', 'toAccount', 'category'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(5)
            ->get();

        $accountSummary = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'current_balance' => (float) $account->current_balance,
                'color' => $account->color,
                'icon' => $account->icon,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary retrieved successfully.',
            'data' => [
                'total_net_balance' => $totalNetBalance,
                'cashflow' => [
                    'current_month_income' => $currentMonthIncome,
                    'current_month_expense' => $currentMonthExpense,
                    'net_cash_flow' => $currentMonthIncome - $currentMonthExpense,
                ],
                'accounts' => $accountSummary,
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }
}
