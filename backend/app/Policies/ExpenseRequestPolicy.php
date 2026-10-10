<?php

namespace App\Policies;

use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\ExpenseRequestService;

class ExpenseRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    /** الموافقة/الرفض/الإرجاع — للإمام بس (أو المحاسب لطلب الإمام نفسه لو الإعداد كده) */
    public function decide(User $user, ExpenseRequest $request): bool
    {
        return app(ExpenseRequestService::class)->canDecide($request, $user);
    }

    public function update(User $user, ExpenseRequest $request): bool
    {
        return $user->can('expenses.create')
            && in_array($request->status, [ExpenseRequest::PENDING, ExpenseRequest::RETURNED], true);
    }

    public function execute(User $user, ExpenseRequest $request): bool
    {
        return $user->can('expenses.execute') && $request->status === ExpenseRequest::APPROVED;
    }

    public function cancel(User $user, ExpenseRequest $request): bool
    {
        return in_array($request->status, [ExpenseRequest::PENDING, ExpenseRequest::RETURNED, ExpenseRequest::APPROVED], true)
            && ($request->requested_by === $user->id || $user->isImam() || $user->can('entries.reverse'));
    }

    public function reverse(User $user, ExpenseRequest $request): bool
    {
        return $user->can('entries.reverse') && $request->status === ExpenseRequest::EXECUTED && ! $request->isReversed();
    }
}
