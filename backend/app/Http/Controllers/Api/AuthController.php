<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $key = 'login:'.Str::lower($data['phone']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['phone' => __('auth.inactive')]);
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $user->createToken($data['device'] ?? 'app')->plainTextToken,
            'user' => $this->payload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'تم تسجيل الخروج']);
    }

    public static function payload(User $user): array
    {
        $user->loadMissing('custodian');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->roleName(),
            'role_label' => $user->roleLabel(),
            'is_imam' => $user->isImam(),
            // يقدر يعتمد طلبات الإمام نفسه لو الإعداد كده
            'can_approve' => $user->isImam() || $user->hasRole(User::ROLE_ACCOUNTANT),
            'custodian' => $user->custodian ? ['id' => $user->custodian->id, 'name' => $user->custodian->name] : null,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'pending_requests' => DashboardService::pendingCount(),
        ];
    }
}
