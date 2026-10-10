<?php

namespace App\Services;

use App\Exports\TableExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/** نفس بيانات التقرير ← JSON أو PDF أو Excel حسب ?format */
class ExportService
{
    public function __construct(private PdfService $pdf, private SettingsService $settings) {}

    public function respond(Request $request, array $table, string $filename, array $extra = []): Response|JsonResponse
    {
        $format = $request->query('format', 'json');
        $mosque = $this->settings->get('mosque_name');

        if ($format === 'xlsx') {
            $content = Excel::raw(new TableExport($table, $mosque), ExcelWriter::XLSX);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
            ]);
        }

        if ($format === 'pdf') {
            $landscape = count($table['columns']) > 6;

            return $this->pdf->download('pdf.table', [
                'table' => $table,
                'mosqueName' => $mosque,
                'printedBy' => $request->user()?->name,
            ], $filename.'.pdf', 'A4', $landscape ? 'L' : 'P');
        }

        return response()->json($table + $extra);
    }
}
