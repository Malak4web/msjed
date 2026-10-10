<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF عربي RTL بخط XB Riyaz المدمج في mpdf (Cairo فيه جداول OpenType مش مدعومة في mpdf).
 */
class PdfService
{
    public function make(string $view, array $data, array|string $format = 'A4', string $orientation = 'P'): Mpdf
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'directionality' => 'rtl',
            'tempDir' => storage_path('app/mpdf'),
            'default_font' => 'xbriyaz',
            'useSubstitutions' => true,
            'backupSubsFont' => ['dejavusans'],
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf;
    }

    public function download(string $view, array $data, string $filename, array|string $format = 'A4', string $orientation = 'P'): Response
    {
        $content = $this->make($view, $data, $format, $orientation)->Output('', Destination::STRING_RETURN);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
