<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Resources\IncomeResource;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Services\IncomeService;
use App\Services\PdfService;
use App\Services\SettingsService;
use App\Support\ArabicNumberWords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $incomes) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->filtered($request);

        $byMethod = (clone $query)->notReversed()
            ->selectRaw('payment_method_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method_id')
            ->get()
            ->keyBy('payment_method_id');

        $summary = PaymentMethod::orderBy('sort')->get(['id', 'name', 'icon'])->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'icon' => $m->icon,
            'total' => number_format((float) ($byMethod[$m->id]->total ?? 0), 2, '.', ''),
            'count' => (int) ($byMethod[$m->id]->count ?? 0),
        ]);

        $page = $query->with('source', 'paymentMethod', 'custodian', 'creator')
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return IncomeResource::collection($page)->additional([
            'summary' => [
                'by_method' => $summary,
                'total' => number_format((float) $byMethod->sum('total'), 2, '.', ''),
                'count' => (int) $byMethod->sum('count'),
            ],
        ])->response();
    }

    public function store(StoreIncomeRequest $request): JsonResponse
    {
        $income = $this->incomes->create($request->validated(), $request->user(), $request->file('attachment'));

        return response()->json([
            'message' => "تم تسجيل الإيراد بنجاح — إيصال رقم {$income->receipt_no}",
            'data' => new IncomeResource($income->load('source', 'paymentMethod', 'custodian', 'creator', 'attachments')),
        ], 201);
    }

    public function show(Income $income): IncomeResource
    {
        return new IncomeResource($income->load('source', 'paymentMethod', 'custodian', 'creator', 'reverser', 'attachments'));
    }

    public function reverse(ReasonRequest $request, Income $income): JsonResponse
    {
        $income = $this->incomes->reverse($income, $request->validated('reason'), $request->user());

        return response()->json([
            'message' => 'تم إلغاء الإيراد بقيد عكسي',
            'data' => new IncomeResource($income->load('source', 'paymentMethod', 'custodian', 'creator', 'reverser')),
        ]);
    }

    public function receipt(Request $request, Income $income, PdfService $pdf, SettingsService $settings): Response
    {
        $income->load('source', 'paymentMethod', 'custodian', 'creator');
        $all = $settings->all();
        $format = $request->query('format', $all['receipt_format']) === 'thermal' ? 'thermal' : 'a5';
        $logo = $all['logo_path'] && Storage::disk('local')->exists($all['logo_path'])
            ? Storage::disk('local')->path($all['logo_path'])
            : null;

        // الحراري 80mm بطول تقريبي يكفي الإيصال
        $paper = $format === 'thermal' ? [80, 200] : 'A5';

        return $pdf->download('pdf.receipt', [
            'income' => $income,
            'settings' => $all,
            'format' => $format,
            'logo' => $logo,
            'words' => ArabicNumberWords::money($income->amount),
        ], "receipt-{$income->receipt_no}.pdf", $paper);
    }

    private function filtered(Request $request): Builder
    {
        return Income::query()
            ->when($request->date('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->integer('income_source_id'), fn ($q, $v) => $q->where('income_source_id', $v))
            ->when($request->integer('payment_method_id'), fn ($q, $v) => $q->where('payment_method_id', $v))
            ->when($request->integer('custodian_id'), fn ($q, $v) => $q->where('custodian_id', $v))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', "%{$s}%")
                ->orWhere('payer_name', 'like', "%{$s}%")
                ->orWhere('notes', 'like', "%{$s}%")));
    }
}
