<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Custodian;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Services\StatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private ExportService $export) {}

    public function index(): JsonResponse
    {
        return response()->json(['types' => ReportService::TYPES]);
    }

    /** GET /reports/{type}?from&to&format=json|pdf|xlsx */
    public function show(Request $request, string $type, ReportService $reports): Response|JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $table = $reports->build($type, $request->query('from'), $request->query('to'));

        return $this->export->respond($request, $table, "{$type}-{$table['from']}-{$table['to']}");
    }

    /** GET /custodians/{id}/statement?from&to&format=json|pdf|xlsx */
    public function statement(Request $request, Custodian $custodian, StatementService $statements): Response|JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $data = $statements->build($custodian, $request->query('from'), $request->query('to'));

        if (in_array($request->query('format'), ['pdf', 'xlsx'], true)) {
            return $this->export->respond($request, $statements->table($data), "statement-{$custodian->id}-{$data['to']}");
        }

        return response()->json($data);
    }
}
