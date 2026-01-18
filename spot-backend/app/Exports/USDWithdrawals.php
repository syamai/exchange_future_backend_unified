<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;

class USDWithdrawals implements WithDefaultStyles, WithColumnWidths, WithTitle, WithCustomStartCell, FromArray
{
    use Exportable;

    protected $data;
    private $fileName;
    private $sheetName;

    public function __construct(array $data, string $fileName, string $sheetName)
    {
        $this->data = $data;
        $this->fileName = $fileName;
        $this->sheetName = $sheetName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function defaultStyles(Style $defaultStyle)
    {
        // Configure the default styles
        return $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID);

        // Or return the styles array
        return [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => Color::COLOR_BLACK],
            ],
        ];
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
        return 'A1';
    }
}
