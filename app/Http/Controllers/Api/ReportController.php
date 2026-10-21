<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function monthly(Request $request): JsonResponse
    {
        $month = (int) ($request->query('month') ?? now()->month);
        $year = (int) ($request->query('year') ?? now()->year);

        $transactions = $request->user()
            ->transactions()
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get();

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Monthly report retrieved successfully.',
            'data' => [
                'month' => $month,
                'year' => $year,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_savings' => $totalIncome - $totalExpense,
            ],
        ]);
    }

    public function categoryBreakdown(Request $request): JsonResponse
    {
        $month = (int) ($request->query('month') ?? now()->month);
        $year = (int) ($request->query('year') ?? now()->year);
        $type = $request->query('type', 'expense');

        $breakdown = $request->user()
            ->transactions()
            ->with('category')
            ->where('type', $type)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get()
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) {
                $category = $items->first()->category;
                $totalAmount = (float) $items->sum('amount');

                return [
                    'category_id' => $categoryId,
                    'name' => $category?->name ?? 'Unknown',
                    'color' => $category?->color ?? '#6B7280',
                    'total_amount' => $totalAmount,
                    'percentage' => 0,
                ];
            })
            ->values();

        $grandTotal = (float) $breakdown->sum('total_amount');

        $breakdown = $breakdown->map(function ($item) use ($grandTotal) {
            $item['percentage'] = $grandTotal > 0 ? round(($item['total_amount'] / $grandTotal) * 100, 2) : 0;

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Category breakdown retrieved successfully.',
            'data' => [
                'period' => sprintf('%02d-%d', $month, $year),
                'total_expense' => $grandTotal,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    public function cashflowTrend(Request $request): JsonResponse
    {
        $months = (int) ($request->query('months') ?? 6);
        $endDate = now();
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        $rows = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addMonth()) {
            $month = $date->month;
            $year = $date->year;

            $transactions = $request->user()
                ->transactions()
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->get();

            $income = (float) $transactions->where('type', 'income')->sum('amount');
            $expense = (float) $transactions->where('type', 'expense')->sum('amount');

            $rows[] = [
                'period' => $date->format('Y-m'),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Cashflow trend retrieved successfully.',
            'data' => $rows,
        ]);
    }

    public function accountDistribution(Request $request): JsonResponse
    {
        $month = (int) ($request->query('month') ?? now()->month);
        $year = (int) ($request->query('year') ?? now()->year);

        $distribution = $request->user()
            ->transactions()
            ->with(['account'])
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get()
            ->groupBy('account_id')
            ->map(function ($items, $accountId) {
                $account = $items->first()->account;

                return [
                    'account_id' => $accountId,
                    'name' => $account?->name ?? 'Unknown',
                    'type' => $account?->type ?? 'other',
                    'total_amount' => (float) $items->sum('amount'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Account distribution retrieved successfully.',
            'data' => $distribution,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:1|max:9999',
            'format' => 'sometimes|in:csv',
        ]);

        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);

        $transactions = $request->user()
            ->transactions()
            ->with(['account', 'category'])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date')
            ->get();

        return $this->csvDownload($transactions, sprintf('transactions-%04d-%02d.csv', $year, $month));

        $columns = ['id', 'type', 'amount', 'transaction_date', 'notes', 'account', 'category'];
        $csv = fopen('php://temp', 'w+');

        fputcsv($csv, $columns);
        foreach ($transactions as $transaction) {
            fputcsv($csv, [
                $transaction->id,
                $transaction->type,
                $transaction->amount,
                $transaction->transaction_date->format('Y-m-d'),
                $transaction->notes ?? '',
                $transaction->account?->name ?? '',
                $transaction->category?->name ?? '',
            ]);
        }

        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        return response($output)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="transactions-'.sprintf('%04d-%02d', $year, $month).'.csv"');
    }

    public function exportMonthDirected(Request $request)
    {
        $payload = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:1|max:9999',
        ]);

        $month = (int) $payload['month'];
        $year = (int) $payload['year'];

        $transactions = $request->user()
            ->transactions()
            ->with(['account', 'category'])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date')
            ->get();

        $columns = ['id', 'type', 'amount', 'transaction_date', 'notes', 'account', 'category'];
        $csv = fopen('php://temp', 'w+');

        fputcsv($csv, $columns);
        foreach ($transactions as $transaction) {
            fputcsv($csv, [
                $transaction->id,
                $transaction->type,
                $transaction->amount,
                $transaction->transaction_date->format('Y-m-d'),
                $transaction->notes ?? '',
                $transaction->account?->name ?? '',
                $transaction->category?->name ?? '',
            ]);
        }

        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        return response($output)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="reports-'.$year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'.csv"');
    }
}
