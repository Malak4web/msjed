<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Attachment;
use App\Models\ExpenseRequest;
use App\Models\ExpenseRequestEvent;
use App\Models\Income;
use App\Models\LedgerEntry;
use App\Models\PeriodLock;
use App\Models\Transfer;
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

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user->load('custodian', 'roles')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = User::create($data);
        $user->syncRoles([$data['role']]);

        activity('audit')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('created')
            ->log("تم إنشاء حساب جديد: {$user->name}");

        return response()->json(['message' => 'تم إضافة المستخدم', 'user' => new UserResource($user->load('custodian'))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        // منع غير الإمام من تعديل حساب الإمام
        if ($user->isImam() && ! $request->user()->isImam()) {
            throw new BusinessRuleException('لا يمكن لغير إمام المسجد تعديل حساب الإمام.');
        }

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

        activity('audit')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('updated')
            ->log("تم تعديل بيانات المستخدم: {$user->name}");

        return response()->json(['message' => 'تم حفظ التعديلات', 'user' => new UserResource($user->load('custodian'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        // منع غير الإمام من حذف حساب الإمام
        if ($user->isImam() && ! $request->user()->isImam()) {
            throw new BusinessRuleException('لا يمكن لغير إمام المسجد حذف حساب الإمام.');
        }

        // منع المستخدم من حذف حسابه الحالي المسجل به
        if ($user->id === $request->user()->id) {
            throw new BusinessRuleException('لا يمكنك حذف الحساب الذي قمت بتسجيل الدخول به حالياً.');
        }

        // منع حذف آخر إمام في النظام
        if ($user->isImam() && User::role(User::ROLE_IMAM)->count() <= 1) {
            throw new BusinessRuleException('لا يمكن حذف إمام المسجد الوحيد في النظام.');
        }

        // التحقق من عدم وجود قيود ومعاملات مالية مسجلة باسم هذا المستخدم
        $hasTransactions = Income::where('created_by', $user->id)->orWhere('reversed_by', $user->id)->exists()
            || Transfer::where('created_by', $user->id)->orWhere('reversed_by', $user->id)->exists()
            || ExpenseRequest::where('requested_by', $user->id)
                ->orWhere('approved_by', $user->id)
                ->orWhere('executed_by', $user->id)
                ->orWhere('reversed_by', $user->id)->exists()
            || ExpenseRequestEvent::where('user_id', $user->id)->exists()
            || LedgerEntry::where('created_by', $user->id)->exists()
            || Attachment::where('uploaded_by', $user->id)->exists()
            || PeriodLock::where('locked_by', $user->id)->exists();

        if ($hasTransactions) {
            throw new BusinessRuleException('لا يمكن حذف هذا المستخدم لوجود معاملات مالية وسجلات محاسبية مرتبطة باسمه. للحفاظ على سلامة الحسابات، يمكنك إيقاف تفعيل الحساب بدلاً من الحذف.');
        }

        $userName = $user->name;
        $user->tokens()->delete();
        $user->notifications()->delete();
        $user->delete();

        activity('audit')
            ->causedBy($request->user())
            ->event('deleted')
            ->log("تم حذف حساب المستخدم: {$userName}");

        return response()->json(['message' => "تم حذف حساب ({$userName}) بنجاح"]);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        // تسجيل الدخول بالحسابات مقتصر على إمام المسجد
        if (! $request->user()->isImam()) {
            abort(403, 'خاصية تسجيل الدخول بحسابات أخرى مخصصة لإمام المسجد فقط.');
        }

        if ($user->id === $request->user()->id) {
            throw new BusinessRuleException('أنت مسجل الدخول بهذا الحساب بالفعل.');
        }

        if (! $user->is_active) {
            throw new BusinessRuleException('لا يمكن تسجيل الدخول بحساب معطّل. قم بتفعيل الحساب أولاً.');
        }

        // إنشاء توكن للمستخدم المستهدف
        $token = $user->createToken(
            'impersonated_by_'.$request->user()->id,
            ['*'],
            now()->addDays(7)
        );

        activity('audit')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('impersonate')
            ->log("قام إمام المسجد ({$request->user()->name}) بتسجيل الدخول بحساب ({$user->name})");

        return response()->json([
            'message' => "تم تسجيل الدخول بنجاح بحساب {$user->name}",
            'token' => $token->plainTextToken,
            'user' => AuthController::payload($user),
        ]);
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
