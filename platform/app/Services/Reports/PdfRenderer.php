<?php

namespace App\Services\Reports;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/** توليد PDF على الخادم بمحرك mPDF: تشكيل عربي، RTL، تضمين الخط، A4، ترقيم ورؤوس جداول مكررة */
class PdfRenderer
{
    public function render(string $html, string $title): string
    {
        $tmp = storage_path('app/mpdf-tmp');
        @mkdir($tmp, 0700, true);
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 28,
            'margin_bottom' => 20,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => $tmp,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'plex' => [
                    'R' => 'IBMPlexSansArabic-Regular.ttf',
                    'B' => 'IBMPlexSansArabic-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 0,
                ],
            ],
            'default_font' => 'plex',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor(config('office.name'));
        $mpdf->SetCreator(config('office.short_name'));
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
