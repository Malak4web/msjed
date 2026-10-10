<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::with('custodian', 'roles')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = User::create($data);
        $user->syncRoles([$data['role']]);

        return response()->json(['message' => 'تم إضافة المستخدم', 'user' => new UserResource($user->load('custodian'))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // ميتقفلش آخر إمام نشط عشان الموافقات متقفش
        if ($user->isImam() && (($data['role'] ?? 'imam') !== User::ROLE_IMAM || ! ($data['is_active'] ?? true))
            && User::role(User::ROLE_IMAM)->where('is_active', true)->count() <= 1) {
            throw new BusinessRuleException('لازم يفضل فيه إمام واحد نشط على الأقل عشان يوافق على الطلبات.');
        }

        $user->update($data);
        $user->syncRoles([$data['role']]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'تم حفظ التعديلات', 'user' => new UserResource($user->load('custodian'))]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'custodian_id' => ['nullable', 'exists:custodians,id', Rule::unique('users', 'custodian_id')->ignore($user)],
            'is_active' => ['boolean'],
        ], [
            'custodian_id.unique' => 'الشخص ده مربوط بمستخدم تاني.',
        ]);
    }
}
