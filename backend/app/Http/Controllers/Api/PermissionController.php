<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * جدول الصلاحيات القابل للتعديل. الموافقة على الصرف مش جوّه الجدول ده — للإمام بس.
 */
class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $matrix = Role::with('permissions')->get()->mapWithKeys(fn (Role $role) => [
            $role->name => $role->permissions->pluck('name'),
        ]);

        return response()->json([
            'roles' => User::ROLE_LABELS,
            'permissions' => Permissions::LABELS,
            'matrix' => $matrix,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matrix' => ['required', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => [Rule::in(array_keys(Permissions::LABELS))],
        ]);

        // الإمام دايمًا يقدر يدخل الإعدادات عشان محدش يقفل النظام على نفسه
        $data['matrix'][User::ROLE_IMAM] = array_values(array_unique([...($data['matrix'][User::ROLE_IMAM] ?? []), 'settings.manage']));

        foreach (array_keys(User::ROLE_LABELS) as $roleName) {
            if (isset($data['matrix'][$roleName])) {
                Role::findByName($roleName, 'web')->syncPermissions($data['matrix'][$roleName]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('audit')->event('permissions_updated')->withProperties($data['matrix'])->log('تعديل جدول الصلاحيات');

        return response()->json(['message' => 'تم حفظ الصلاحيات']);
    }
}
