<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustodianResource;
use App\Models\Custodian;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustodianController extends Controller
{
    /** "الفلوس مع مين؟" — كل شخص ورصيده واستلم/صرف كام الشهر ده */
    public function index(Request $request): AnonymousResourceCollection
    {
        $from = now()->startOfMonth()->toDateString();

        $custodians = Custodian::with('user')
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->withSum(['ledgerEntries as month_in' => fn ($q) => $q->where('date', '>=', $from)->where('amount', '>', 0)], 'amount')
            // "صرف" = مصروفات فعلية بس (مش تسليم لشخص تاني)
            ->withSum(['ledgerEntries as month_out' => fn ($q) => $q->where('date', '>=', $from)->where('type', LedgerEntry::EXPENSE)], 'amount')
            ->orderByDesc('balance_cache')
            ->get();

        $custodians->each(function (Custodian $c) {
            $c->month_in = number_format((float) $c->month_in, 2, '.', '');
            $c->month_out = number_format(abs((float) $c->month_out), 2, '.', '');
        });

        return CustodianResource::collection($custodians);
    }

    public function show(Custodian $custodian, LedgerService $ledger): JsonResponse
    {
        $totals = LedgerEntry::where('custodian_id', $custodian->id)
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in')
            ->selectRaw('SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as total_out')
            ->first();

        return response()->json([
            'data' => (new CustodianResource($custodian->load('user')))->resolve(),
            'totals' => [
                'total_in' => number_format((float) $totals->total_in, 2, '.', ''),
                'total_out' => number_format((float) $totals->total_out, 2, '.', ''),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $custodian = Custodian::create($this->validated($request));

        return response()->json(['message' => 'تم إضافة الشخص', 'data' => new CustodianResource($custodian)], 201);
    }

    public function update(Request $request, Custodian $custodian, LedgerService $ledger): JsonResponse
    {
        $data = $this->validated($request);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $custodian->is_active
            && $ledger->balanceCents($custodian->id) !== 0) {
            throw new BusinessRuleException('مينفعش توقف شخص معاه فلوس. سلّم رصيده لشخص تاني الأول.');
        }

        $custodian->update($data);

        return response()->json(['message' => 'تم حفظ التعديلات', 'data' => new CustodianResource($custodian)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);
    }
}
