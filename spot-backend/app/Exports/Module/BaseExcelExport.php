<?php

namespace App\Exports\Module;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BaseExcelExport implements FromCollection, WithHeadings, WithTitle
{
    protected $data, $headings, $sheetName, $startCell, $columns, $ext, $fileName;

    public function __construct(array $params)
    {
        $this->data = collect($params['data'] ?? []);
        $this->headings = $params['headings'] ?? [];
        $this->startCell = $params['startCell'] ?? 'A1';
        $this->columns = $params['columns'] ?? [
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
        $this->sheetName = $params['sheetName'] ?? 'Sheet1';
        $this->ext = $params['ext'] ?? 'xlsx';
        $this->fileName = $params['fileName'] ?? 'export';
    }
    
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function startCell(): string
    {
        return $this->startCell;
    }

    public function columnWidths(): array
    {
        return $this->columns;
    }

    public function ext(): string {
        return $this->ext;
    }

    public function fileName(): string {
        return $this->fileName;
    }
}
