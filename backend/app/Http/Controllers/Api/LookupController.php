<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إدارة القوائم البسيطة: مصادر الإيراد، طرق الدفع، بنود الصرف.
 * مفيش حذف عشان العمليات القديمة مربوطة بيها — بيتوقف بس.
 */
class LookupController extends Controller
{
    private const MODELS = [
        'income-sources' => IncomeSource::class,
        'payment-methods' => PaymentMethod::class,
        'expense-categories' => ExpenseCategory::class,
    ];

    public function index(string $type): JsonResponse
    {
        return response()->json(['data' => $this->model($type)::orderBy('sort')->orderBy('id')->get()]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $class = $this->model($type);
        $item = $class::create($this->validated($request, $type) + ['sort' => $class::max('sort') + 1]);

        return response()->json(['message' => 'تمت الإضافة', 'data' => $item], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $item = $this->model($type)::findOrFail($id);
        $item->update($this->validated($request, $type));

        return response()->json(['message' => 'تم الحفظ', 'data' => $item]);
    }

    /** @return class-string<Model> */
    private function model(string $type): string
    {
        abort_unless(isset(self::MODELS[$type]), 404);

        return self::MODELS[$type];
    }

    private function validated(Request $request, string $type): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'type' => [Rule::requiredIf($type === 'expense-categories'), Rule::in(['fixed', 'variable'])],
        ]);
    }
}
