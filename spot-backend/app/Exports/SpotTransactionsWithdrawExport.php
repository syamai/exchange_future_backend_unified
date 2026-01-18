<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SpotTransactionsWithdrawExport implements FromArray, WithColumnWidths, WithTitle, WithCustomStartCell, WithHeadings {
    use Exportable;

    protected $data;
    protected $fileName;
    protected $sheetName;
    protected $headings;

    public function __construct(array $data, string $fileName, string $sheetName, array $headings)
    {
        $this->data = $data;
        $this->fileName = $fileName;
        $this->sheetName = $sheetName;
        $this->headings = $headings;
    }

    public function array(): array {
        return $this->data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
            'K' => 15,
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->sheetName;
    }

    public function startCell(): string
    {
        return 'A1'; // Corrected from 'A0' to 'A1'
    }

    public function headings(): array {
//        \Log::info('Headings: ' . print_r($this->headings, true));
        return $this->headings;
    }
}
