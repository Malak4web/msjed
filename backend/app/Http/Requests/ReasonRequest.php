<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** أي إجراء محتاج سبب إلزامي (رفض، إرجاع، إلغاء، قيد عكسي) */
class ReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'اكتب السبب.',
        ];
    }
}
