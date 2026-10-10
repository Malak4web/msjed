<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->all()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mosque_name' => ['sometimes', 'required', 'string', 'max:150'],
            'mosque_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mosque_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'receipt_format' => ['sometimes', 'in:a5,thermal'],
            'imam_self_request_mode' => ['sometimes', 'in:auto,accountant'],
            'require_invoice_on_execute' => ['sometimes', 'boolean'],
            'show_hijri_date' => ['sometimes', 'boolean'],
        ]);

        $this->settings->set(array_map(fn ($v) => $v ?? '', $data));

        return response()->json(['message' => 'تم حفظ الإعدادات', 'settings' => $this->settings->all()]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'max:2048']]);

        $old = $this->settings->get('logo_path');
        $path = $request->file('logo')->store('branding', 'local');
        $this->settings->set(['logo_path' => $path]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['message' => 'تم رفع الشعار']);
    }

    /** الشعار مش سري — بيتعرض في شاشة الدخول والإيصالات */
    public function logo(): Response
    {
        $path = $this->settings->get('logo_path');

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
