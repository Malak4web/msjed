<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * خطأ في قاعدة عمل (رصيد مش كفاية، شهر مقفول...) برسالة عربي واضحة للمستخدم.
 */
class BusinessRuleException extends Exception
{
    public function __construct(string $message, protected int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
