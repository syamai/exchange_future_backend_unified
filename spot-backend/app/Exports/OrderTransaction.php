<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;

class OrderTransaction implements WithDefaultStyles, WithColumnWidths, WithTitle, WithCustomStartCell, WithHeadings, FromArray
{
    use Exportable;

    protected $data;
    protected $fileName;
    protected $sheetName;

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
        return 'A0';
    }

    public function headings() :array
    {
        return [
            __('Date'),  __('Pair'), __('Type'), __('Side'), __('Average'), __('Price'),
            __('Filled'), __('Amount'), __('Total'), __('Trigger Conditions'), __('Status')
        ];
    }
}
