<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'integer', 'between:1,9999'],
            'account_id' => ['sometimes', 'integer', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where(function (Builder $query) use ($request) {
                    $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
                }),
            ],
            'type' => ['sometimes', Rule::in(['income', 'expense', 'transfer'])],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = $request->user()
            ->transactions()
            ->with(['account', 'toAccount', 'category'])
            ->when(isset($filters['month']), function ($query) use ($filters) {
                $query->whereMonth('transaction_date', $filters['month']);
            })
            ->when(isset($filters['year']), function ($query) use ($filters) {
                $query->whereYear('transaction_date', $filters['year']);
            })
            ->when(isset($filters['account_id']), function ($query) use ($filters) {
                $accountId = $filters['account_id'];
                $query->where(function ($subQuery) use ($accountId) {
                    $subQuery->where('account_id', $accountId)
                        ->orWhere('to_account_id', $accountId);
                });
            })
            ->when(isset($filters['category_id']), function ($query) use ($filters) {
                $query->where('category_id', $filters['category_id']);
            })
            ->when(isset($filters['type']), function ($query) use ($filters) {
                $query->where('type', $filters['type']);
            })
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $term = $filters['search'];
                $query->where(function ($subQuery) use ($term) {
                    $subQuery->where('notes', 'like', "%{$term}%")
                        ->orWhere('type', 'like', "%{$term}%");
                });
            });

        $perPage = $filters['per_page'] ?? 15;

        return response()->json([
            'success' => true,
            'message' => 'Transactions retrieved successfully.',
            'data' => $query->latest('transaction_date')->latest('id')->paginate($perPage),
        ]);
    }

    public function store(Request $request, TransactionService $transactionService): JsonResponse
    {
        $validated = $this->validateTransaction($request);
        $validated['user_id'] = $request->user()->id;

        $transaction = $transactionService->createTransaction($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully.',
            'data' => $transaction->load(['account', 'toAccount', 'category']),
        ], 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 404);

        return response()->json([
            'success' => true,
            'data' => $transaction->load(['account', 'toAccount', 'category']),
        ]);
    }

    public function update(Request $request, Transaction $transaction, TransactionService $transactionService): JsonResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 404);

        $validated = $this->validateTransaction($request, $transaction);

        $updated = $transactionService->updateTransaction($transaction, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'data' => $updated->load(['account', 'toAccount', 'category']),
        ]);
    }

    public function destroy(Transaction $transaction, TransactionService $transactionService): JsonResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 404);

        $transactionService->deleteTransaction($transaction);

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully.',
        ]);
    }

    public function validatedForReceipt(Request $request): array
    {
        return $this->validateTransaction($request);
    }

    private function validateTransaction(Request $request, ?Transaction $transaction = null): array
    {
        $updating = $transaction !== null;
        $userId = $request->user()->id;
        $accountExists = Rule::exists('accounts', 'id')->where('user_id', $userId);
        $categoryExists = Rule::exists('categories', 'id')->where(function (Builder $query) use ($userId) {
            $query->whereNull('user_id')->orWhere('user_id', $userId);
        });

        $validator = Validator::make($request->all(), [
            'account_id' => [$updating ? 'sometimes' : 'required', 'integer', $accountExists],
            'to_account_id' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'integer', $accountExists],
            'category_id' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'integer', $categoryExists],
            'type' => [$updating ? 'sometimes' : 'required', Rule::in(['income', 'expense', 'transfer'])],
            'amount' => [$updating ? 'sometimes' : 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'transaction_date' => [$updating ? 'sometimes' : 'required', 'date'],
            'notes' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'string'],
            'attachment' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($request, $transaction) {
            $input = $validator->getData();
            $type = $input['type'] ?? $transaction?->type;
            $typeChanged = $transaction !== null
                && array_key_exists('type', $input)
                && $input['type'] !== $transaction->type;
            $accountId = $input['account_id'] ?? $transaction?->account_id;
            $toAccountId = array_key_exists('to_account_id', $input)
                ? $input['to_account_id']
                : $transaction?->to_account_id;
            $categoryId = array_key_exists('category_id', $input)
                ? $input['category_id']
                : $transaction?->category_id;

            if ($typeChanged && $type === 'transfer' && ! array_key_exists('category_id', $input)) {
                $categoryId = null;
            }

            if ($type === 'transfer') {
                if (! $accountId) {
                    $validator->errors()->add('account_id', 'A source account is required for a transfer.');
                }
                if (! $toAccountId) {
                    $validator->errors()->add('to_account_id', 'A destination account is required for a transfer.');
                } elseif ((string) $accountId === (string) $toAccountId) {
                    $validator->errors()->add('to_account_id', 'The destination account must differ from the source account.');
                }
                if ($categoryId !== null) {
                    $validator->errors()->add('category_id', 'A category cannot be assigned to a transfer.');
                }

                return;
            }

            if ($toAccountId !== null && ! ($typeChanged && ! array_key_exists('to_account_id', $input))) {
                $validator->errors()->add('to_account_id', 'A destination account can only be assigned to a transfer.');
            }
            if (! $accountId) {
                $validator->errors()->add('account_id', 'An account is required for this transaction.');
            }
            if (! $categoryId) {
                $validator->errors()->add('category_id', 'A category is required for income and expense transactions.');

                return;
            }

            $category = Category::query()
                ->where(function ($query) use ($request) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $request->user()->id);
                })
                ->find($categoryId);

            if ($category && $category->type !== $type) {
                $validator->errors()->add('category_id', 'The category type must match the transaction type.');
            }
        });

        $validated = $validator->validate();
        $type = $validated['type'] ?? $transaction?->type;

        if ($transaction !== null && $type === 'transfer' && $transaction->type !== 'transfer'
            && ! array_key_exists('category_id', $validated)) {
            $validated['category_id'] = null;
        }
        if ($transaction !== null && $type !== 'transfer' && $transaction->to_account_id !== null
            && ! array_key_exists('to_account_id', $validated)) {
            $validated['to_account_id'] = null;
        }

        return $validated;
    }
}
