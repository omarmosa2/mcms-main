<?php

namespace App\Support;

class MinimalPdfDocument
{
    /**
     * @param  array<int, string>  $lines
     */
    public static function build(array $lines): string
    {
        $normalizedLines = array_map(
            static fn (string $line): string => self::sanitizeLine($line),
            array_values($lines),
        );

        $content = "BT\n/F1 11 Tf\n50 780 Td\n14 TL\n";

        foreach ($normalizedLines as $index => $line) {
            $content .= sprintf('(%s) Tj', self::escapePdfText($line))."\n";

            if ($index < count($normalizedLines) - 1) {
                $content .= "T*\n";
            }
        }

        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $objectContent) {
            $objectNumber = $index + 1;
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $objectNumber, $objectContent);
        }

        $startXref = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= sprintf(
            "trailer << /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            count($objects) + 1,
            $startXref,
        );

        return $pdf;
    }

    private static function sanitizeLine(string $line): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $line);

        return trim((string) $value);
    }

    private static function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        );
    }
}
