<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodLock;
use App\Services\PeriodLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodLockController extends Controller
{
    public function __construct(private PeriodLockService $locks) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PeriodLock::with('locker:id,name')->orderByDesc('period')->get()->map(fn (PeriodLock $l) => [
                'period' => $l->period->format('Y-m'),
                'locked_by' => $l->locker?->name,
                'locked_at' => $l->created_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $this->locks->lock($data['period'].'-01', $request->user());

        return response()->json(['message' => 'تم إقفال الشهر'], 201);
    }

    public function destroy(string $period): JsonResponse
    {
        $this->locks->unlock($period.'-01');

        return response()->json(['message' => 'تم فتح الشهر']);
    }
}
