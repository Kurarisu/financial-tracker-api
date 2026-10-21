<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoryBudget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Category::query()
            ->where(function ($query) use ($request, $userId) {
                $query->where('user_id', $userId);
                if ($request->boolean('include_defaults')) {
                    $query->orWhereNull('user_id');
                }
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->query('type'));
            })
            ->latest();

        $categories = $query->get();
        $categoryIds = $categories->pluck('id');
        $now = now();
        $previousMonth = $now->copy()->subMonthNoOverflow();

        $currentSpending = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereIn('category_id', $categoryIds)
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $previousSpending = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereIn('category_id', $categoryIds)
            ->whereYear('transaction_date', $previousMonth->year)
            ->whereMonth('transaction_date', $previousMonth->month)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $budgets = CategoryBudget::query()
            ->where('user_id', $userId)
            ->whereIn('category_id', $categoryIds)
            ->pluck('monthly_budget', 'category_id');

        $categories = $categories->map(function (Category $category) use ($currentSpending, $previousSpending, $budgets) {
            $currentAmount = $category->type === 'expense'
                ? (float) ($currentSpending[$category->id] ?? 0)
                : 0.0;
            $previousAmount = $category->type === 'expense'
                ? (float) ($previousSpending[$category->id] ?? 0)
                : 0.0;
            $budget = $category->type === 'expense' && isset($budgets[$category->id])
                ? (float) $budgets[$category->id]
                : null;

            return array_merge($category->toArray(), [
                'current_month_spent' => $currentAmount,
                'monthly_budget' => $budget,
                'budget_used_percentage' => $budget !== null && $budget > 0
                    ? round(($currentAmount / $budget) * 100, 1)
                    : null,
                'spending_change_percentage' => $previousAmount > 0
                    ? round((($currentAmount - $previousAmount) / $previousAmount) * 100, 1)
                    : null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:income,expense'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'color' => $validated['color'] ?? '#6B7280',
            'icon' => $validated['icon'] ?? 'tag',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        abort_unless($category->user_id === auth()->id() || is_null($category->user_id), 404);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        abort_unless($category->user_id === auth()->id() || is_null($category->user_id), 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:income,expense'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
        ]);

        $category->fill($validated);
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        abort_unless($category->user_id === auth()->id() || is_null($category->user_id), 404);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }

    public function setBudget(Request $request, Category $category): JsonResponse
    {
        $category = Category::forUser($request->user()->id)->findOrFail($category->id);

        abort_unless($category->type === 'expense', 422, 'Budgets can only be set for expense categories.');

        $validated = $request->validate([
            'monthly_budget' => ['required', 'numeric', 'min:0'],
        ]);

        $budget = CategoryBudget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'category_id' => $category->id,
            ],
            ['monthly_budget' => $validated['monthly_budget']],
        );

        return response()->json([
            'success' => true,
            'message' => 'Category budget saved successfully.',
            'data' => [
                'category_id' => $category->id,
                'monthly_budget' => (float) $budget->monthly_budget,
            ],
        ]);
    }
}
