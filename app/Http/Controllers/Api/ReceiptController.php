<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receipt' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);
        $file = $validated['receipt'];
        $image = @getimagesize($file->getRealPath());
        if (!$image || $image[0] > 12000 || $image[1] > 12000) {
            return response()->json(['success' => false, 'message' => 'The image is invalid or its dimensions are too large.'], 422);
        }

        $token = Str::random(48);
        $extension = strtolower($file->extension());
        $path = $file->storeAs('receipts/'.$request->user()->id.'/pending', $token.'.'.$extension, 'local');
        if (!$path) return response()->json(['success' => false, 'message' => 'Unable to store receipt image.'], 500);

        $extracted = ['amount' => null, 'transaction_date' => null, 'notes' => null, 'type' => null, 'confidence' => []];
        try {
            $binary = config('services.tesseract.binary', 'tesseract');
            $result = Process::timeout(30)->run([$binary, Storage::disk('local')->path($path), 'stdout', '-l', 'ind+eng']);
            if (!$result->successful()) {
                Storage::disk('local')->delete($path);
                return response()->json(['success' => false, 'message' => 'Receipt OCR could not read this image. You can enter transaction details manually.'], 422);
            }
            $text = trim($result->output());
            if ($text === '') {
                Storage::disk('local')->delete($path);
                return response()->json(['success' => false, 'message' => 'No readable text was found. Try a clearer image or enter details manually.'], 422);
            }
            $lines = preg_split('/\\R/u', $text) ?: [];
            $extracted['amount'] = $this->extractAmount($lines);
            $extracted['category_id'] = $this->suggestExpenseCategory($text, $request->user()->id);
            if ($extracted['category_id'] !== null) $extracted['type'] = 'expense';
            if (preg_match('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})\b/', $text, $date)) {
                $year = (int) $date[3]; if ($year < 100) $year += 2000;
                if (checkdate((int) $date[2], (int) $date[1], $year)) $extracted['transaction_date'] = sprintf('%04d-%02d-%02d', $year, $date[2], $date[1]);
            }
            $firstLine = trim(preg_replace('/\s+/', ' ', $lines[0] ?? ''));
            if ($firstLine !== '') $extracted['notes'] = mb_substr($firstLine, 0, 200);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);
            return response()->json(['success' => false, 'message' => 'Receipt OCR is unavailable. You can enter transaction details manually.'], 503);
        }

        return response()->json(['success' => true, 'message' => 'Receipt scanned. Review the suggestions before saving.', 'data' => ['receipt_token' => $token, 'extracted' => $extracted]]);
    }

    /** Prefer explicit totals and avoid guessing from unrelated amounts. */
    private function extractAmount(array $lines): ?float
    {
        $prioritized = [2 => [], 1 => []];
        $currencyAmounts = [];
        foreach ($lines as $line) {
            preg_match_all('/(?:rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?)/iu', $line, $matches);
            $values = [];
            foreach ($matches[1] ?? [] as $candidate) {
                $amount = $this->parseReceiptAmount($candidate);
                if ($amount !== null && $amount > 0) {
                    $values[] = $amount;
                    if (preg_match('/\brp\.?\s*\d/iu', $line)) $currencyAmounts[] = $amount;
                }
            }
            if (!$values || preg_match('/\b(?:sub\s*total|subtotal|ppn|pajak|tax)\b/iu', $line)) continue;
            if (preg_match('/\b(?:grand\s*total|total\s*(?:bayar|dibayar|pembayaran|transfer|tagihan)|jumlah\s*(?:bayar|transfer|tagihan)|amount\s*(?:paid|due)|nominal\s*transfer)\b/iu', $line)) {
                $prioritized[2][] = $values[0];
            } elseif (preg_match('/\btotal\b/iu', $line)) {
                $prioritized[1][] = $values[0];
            }
        }
        if ($prioritized[2]) return end($prioritized[2]);
        if ($prioritized[1]) return end($prioritized[1]);
        $unique = array_values(array_unique($currencyAmounts));
        return count($unique) === 1 ? $unique[0] : null;
    }

    private function parseReceiptAmount(string $value): ?float
    {
        $value = preg_replace('/[^0-9.,]/', '', $value) ?? '';
        if ($value === '') return null;
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.';
            $group = $decimal === ',' ? '.' : ',';
            $normalized = str_replace([$group, $decimal], ['', '.'], $value);
            return is_numeric($normalized) ? (float) $normalized : null;
        }
        $separator = $comma !== false ? ',' : ($dot !== false ? '.' : null);
        if ($separator === null) return is_numeric($value) ? (float) $value : null;
        $parts = explode($separator, $value);
        $fraction = end($parts);
        if (strlen($fraction) === 3) {
            $integer = str_replace($separator, '', $value);
            return is_numeric($integer) ? (float) $integer : null;
        }
        $normalized = str_replace($separator, '.', $value);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /** Only suggest an existing expense category for recognizable receipt keywords. */
    private function suggestExpenseCategory(string $text, int $userId): ?int
    {
        $text = mb_strtolower($text);
        $groups = [
            'food' => ['makanan', 'minuman', 'food', 'beverage'],
            'transport' => ['transportasi', 'transport'],
            'shopping' => ['belanja', 'shopping', 'kebutuhan'],
            'bills' => ['tagihan', 'utilitas', 'bills', 'utilities'],
            'health' => ['kesehatan', 'health'],
            'entertainment' => ['hiburan', 'liburan', 'entertainment'],
            'housing' => ['tempat tinggal', 'housing'],
            'education' => ['pendidikan', 'education'],
            'donation' => ['donasi', 'sedekah', 'charity'],
        ];
        $rules = [
            'food' => '/\b(?:gofood|grabfood|restoran|restaurant|resto|warung|cafe|café|kopi|coffee|makanan|minuman|bakery|roti)\b/u',
            'transport' => '/\b(?:gojek|grab|transport|bensin|pertamina|shell|parkir|parking|tol|kereta|bus|taksi|taxi|fuel)\b/u',
            'shopping' => '/\b(?:shopee|tokopedia|lazada|blibli|alfamart|indomaret|supermarket|marketplace|belanja)\b/u',
            'bills' => '/\b(?:pln|listrik|pdam|indihome|telkom|internet|tagihan|pulsa|utility)\b/u',
            'health' => '/\b(?:apotek|farmasi|klinik|rumah sakit|hospital|pharmacy|kesehatan)\b/u',
            'entertainment' => '/\b(?:bioskop|cinema|netflix|spotify|hiburan|ticket|tiket)\b/u',
            'housing' => '/\b(?:sewa rumah|kontrakan|rent|housing)\b/u',
            'education' => '/\b(?:sekolah|kampus|universitas|pendidikan|tuition)\b/u',
            'donation' => '/\b(?:donasi|sedekah|zakat|charity)\b/u',
        ];
        $group = null;
        foreach ($rules as $key => $pattern) {
            if (preg_match($pattern, $text)) { $group = $key; break; }
        }
        if ($group === null) return null;
        $categories = Category::query()->forUser($userId)->where('type', 'expense')->get();
        foreach ($categories as $category) {
            $name = mb_strtolower($category->name);
            foreach ($groups[$group] as $term) {
                if (str_contains($name, $term)) return (int) $category->id;
            }
        }
        return null;
    }

    public function show(Transaction $transaction): StreamedResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 404);
        abort_unless($transaction->attachment && str_starts_with($transaction->attachment, 'receipts/'.$transaction->user_id.'/'), 404);
        abort_unless(Storage::disk('local')->exists($transaction->attachment), 404);
        return Storage::disk('local')->response($transaction->attachment, 'receipt', ['Content-Disposition' => 'inline']);
    }

    public function store(Request $request, TransactionService $service): JsonResponse
    {
        $validated = $this->validateTransaction($request);
        $validated['user_id'] = $request->user()->id;
        $destination = null;
        if ($request->filled('receipt_token')) {
            $request->validate(['receipt_token' => ['required', 'string', 'size:48']]);
            $disk = Storage::disk('local');
            $matches = $disk->files('receipts/'.$request->user()->id.'/pending');
            $source = collect($matches)->first(fn ($path) => pathinfo($path, PATHINFO_FILENAME) === $request->input('receipt_token'));
            abort_unless($source && $disk->exists($source), 422, 'Receipt upload expired. Please scan the image again.');
            $destination = 'receipts/'.$request->user()->id.'/transactions/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
            $validated['attachment'] = $destination;
        }
        try {
            if ($destination && !$disk->move($source, $destination)) throw new \RuntimeException('Unable to attach receipt.');
            $transaction = $service->createTransaction($validated);
        } catch (\Throwable $exception) {
            if ($destination) {
                Storage::disk('local')->delete($destination);
                if (isset($source)) Storage::disk('local')->delete($source);
            }
            throw $exception;
        }
        return response()->json(['success' => true, 'message' => 'Transaction created successfully.', 'data' => $transaction->load(['account', 'toAccount', 'category'])], 201);
    }

    private function validateTransaction(Request $request): array
    {
        // TransactionController remains the canonical validator for all transaction fields.
        return app(TransactionController::class)->validatedForReceipt($request);
    }
}
