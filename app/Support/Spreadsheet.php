<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Read/write spreadsheets (.xlsx / .xls / .csv) for question import and reports.
 */
class Spreadsheet
{
    /**
     * Read a spreadsheet into an array of associative rows (first row = header).
     * @return array<int,array<string,string>>
     */
    public static function read(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        $reader = match ($ext) {
            'csv' => new CsvReader(),
            'xls' => new XlsReader(),
            default => new XlsxReader(),
        };
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false); // 0-indexed columns

        if (empty($data)) {
            return [];
        }
        $header = array_map(fn ($c) => trim((string) $c), array_shift($data));

        $rows = [];
        foreach ($data as $line) {
            // skip fully empty rows
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $col) {
                if ($col === '') {
                    continue;
                }
                $row[$col] = isset($line[$i]) ? (string) $line[$i] : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Stream a polished .xlsx download built from a header row + data rows.
     *
     * @param  array<int,string>              $headers
     * @param  array<int,array<int,mixed>>    $rows
     * @param  string|null                    $title    Optional title band shown above the table.
     * @param  string|null                    $subtitle Optional line under the title (e.g. a date range).
     */
    public static function download(string $filename, array $headers, array $rows, ?string $title = null, ?string $subtitle = null)
    {
        $ss = new PhpSpreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Report');

        $ss->getProperties()->setCreator('ExamNex')->setTitle($title ?: 'ExamNex Report');

        // Brand colours.
        $BRAND = 'FF1E3A8A';       // deep blue header
        $BRAND_DARK = 'FF0B1E42';
        $ZEBRA = 'FFF3F6FB';       // very light blue stripe
        $GRID = 'FFE2E8F0';        // soft grid line
        $WHITE = 'FFFFFFFF';
        $MUTED = 'FF64748B';

        $colCount = max(count($headers), 1);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        $row = 1;
        if ($title) {
            $sheet->setCellValue("A{$row}", $title);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $st = $sheet->getStyle("A{$row}");
            $st->getFont()->setBold(true)->setSize(15)->getColor()->setARGB($WHITE);
            $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BRAND_DARK);
            $st->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;

            $meta = trim(($subtitle ? $subtitle . '   ·   ' : '') . 'Generated ' . now()->format('d M Y, H:i'));
            $sheet->setCellValue("A{$row}", $meta);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $ms = $sheet->getStyle("A{$row}");
            $ms->getFont()->setSize(9)->getColor()->setARGB($MUTED);
            $ms->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($WHITE);
            $ms->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
            $sheet->getRowDimension($row)->setRowHeight(16);
            $row++;
        }

        // ---- Header row ----
        $headerRow = $row;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $hStyle = $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}");
        $hStyle->getFont()->setBold(true)->getColor()->setARGB($WHITE);
        $hStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BRAND);
        $hStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        // ---- Data ----
        $dataStart = $headerRow + 1;
        if ($rows) {
            $sheet->fromArray($rows, null, "A{$dataStart}");
        }
        $lastRow = $rows ? $dataStart + count($rows) - 1 : $headerRow;

        // Borders across the whole table.
        $tableRange = "A{$headerRow}:{$lastCol}{$lastRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($GRID);

        // Zebra striping + row height + vertical centering on data.
        if ($rows) {
            $sheet->getStyle("A{$dataStart}:{$lastCol}{$lastRow}")
                ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            for ($r = $dataStart; $r <= $lastRow; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(18);
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($ZEBRA);
                }
            }
        }

        // Filter dropdowns on the header, and freeze the header + first column.
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
        $sheet->freezePane('B' . $dataStart);

        // Auto-size every column (capped so long emails/dates don't blow out).
        for ($i = 1; $i <= $colCount; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $dim = $sheet->getColumnDimension($letter);
            $dim->setAutoSize(true);
        }
        // Nudge the first column a touch wider for names.
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(24);

        return response()->streamDownload(function () use ($ss) {
            (new XlsxWriter($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
