<?php
namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;

class MarginTradeApiExport implements FromArray, WithColumnWidths, WithTitle
{
    private $data;
    private $sheetName;

    public function __construct($data, $sheetName)
    {
        $this->data = $data;
        $this->sheetName = $sheetName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 20,
            'C' => 20,
            'D' => 20,
            'E' => 20,
            'F' => 20,
            'G' => 20,
            'H' => 20,
            'I' => 20,
            'J' => 20,
            'K' => 20,
            'L' => 20,
            'M' => 20,
        ];
    }

    public function title(): string
    {
        return $this->sheetName;
    }
}
