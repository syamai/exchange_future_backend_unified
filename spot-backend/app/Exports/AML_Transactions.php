<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Style\Style;

class AML_Transactions implements WithDefaultStyles, WithColumnWidths, WithTitle, WithCustomStartCell, FromArray
{
    use Exportable;

    protected array $data;
    private string $fileName;
    private string $sheetName;

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

    /**
    * Optional Writer Type
    */
    private string $writerType = Excel::XLSX;

    public function title(): string
    {
        return $this->sheetName;
    }

    public function columnWidths(): array
    {
        return [];
    }

    public function startCell(): string
    {
        return "";
    }

    public function defaultStyles(Style $defaultStyle): Style
    {
        return $defaultStyle;
    }
}
