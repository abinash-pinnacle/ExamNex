<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;
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
     * Stream an .xlsx download built from a header row + data rows.
     * @param array<int,string> $headers
     * @param array<int,array<int,mixed>> $rows
     */
    public static function download(string $filename, array $headers, array $rows)
    {
        $ss = new PhpSpreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if ($rows) {
            $sheet->fromArray($rows, null, 'A2');
        }
        // Bold header.
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($ss) {
            (new XlsxWriter($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
