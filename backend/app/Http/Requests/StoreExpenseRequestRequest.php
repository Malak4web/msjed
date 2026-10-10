<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequestRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') && ! $this->route('expenseRequest') ? 'required' : 'sometimes';

        return [
            'custodian_id' => [$required, 'exists:custodians,id'],
            'expense_category_id' => [$required, 'exists:expense_categories,id'],
            'amount' => [$required, 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'description' => [$required, 'string', 'min:3', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'اكتب مبلغ أكبر من صفر.',
            'description.required' => 'اكتب هتصرف في إيه بالظبط.',
            'description.min' => 'اكتب وصف أوضح شوية.',
        ];
    }
}
