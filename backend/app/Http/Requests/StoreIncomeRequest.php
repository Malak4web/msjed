<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('incomes.create');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'income_source_id' => ['required', 'exists:income_sources,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'custodian_id' => ['required', 'exists:custodians,id'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'مينفعش تسجّل إيراد بتاريخ لسه مجاش.',
            'amount.gt' => 'اكتب مبلغ أكبر من صفر.',
        ];
    }
}
