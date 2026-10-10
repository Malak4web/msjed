<?php

use Illuminate\Support\Facades\Route;

/*
 * الواجهة (Vue) بعد البناء بتتحط في public/ (npm run build:laravel في frontend).
 * أي رابط مش ملف ومش /api بيرجّع index.html عشان الـ SPA تتعامل معاه.
 */
$spa = function () {
    $index = public_path('index.html');

    abort_unless(is_file($index), 404, 'الواجهة مش متبنية. شغّل: cd frontend && npm run build:laravel');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
};

Route::get('/', $spa);
Route::fallback($spa);
