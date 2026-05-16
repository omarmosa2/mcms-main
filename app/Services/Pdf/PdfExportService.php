<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExportService
{
    /**
     * @param  FromQuery&WithHeadings  $export
     */
    public function download(object $export, string $filename, string $title): StreamedResponse
    {
        $headings = $export->headings();

        $rows = [];
        $query = $export->query();

        $chunkSize = 500;
        $query->chunk($chunkSize, function ($items) use (&$rows, $export): void {
            foreach ($items as $item) {
                if ($export instanceof WithMapping) {
                    $rows[] = $export->map($item);
                } else {
                    $rows[] = (array) $item;
                }
            }
        });

        $html = view('pdf.export-table', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
