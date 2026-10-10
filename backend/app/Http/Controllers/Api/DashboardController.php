<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseRequestResource;
use App\Models\ExpenseRequest;
use App\Services\DashboardService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard, LedgerService $ledger): JsonResponse
    {
        $pending = ExpenseRequest::with('custodian', 'category', 'requester')
            ->withCount('attachments')
            ->where('status', ExpenseRequest::PENDING)
            ->orderBy('id')
            ->limit(6)
            ->get();

        return response()->json([
            'month' => $dashboard->monthTotals(),
            'chart' => $dashboard->monthlyChart(6),
            'pending' => ExpenseRequestResource::collection($pending)->resolve(),
            'pending_available' => $pending->mapWithKeys(fn ($r) => [$r->id => $ledger->summary($r->custodian)['available']]),
            'pending_count' => DashboardService::pendingCount(),
            'approved_count' => ExpenseRequest::where('status', ExpenseRequest::APPROVED)->count(),
            'upcoming' => $dashboard->upcomingRecurring(10),
            'recent' => $dashboard->recentActivity(6),
        ]);
    }
}
