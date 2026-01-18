<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AmlTransactionExport implements WithMultipleSheets
{
    use Exportable;

    protected $sheetData;

    public function __construct($sheetData)
    {
        $this->sheetData = $sheetData;
    }


    public function sheets(): array
    {
        $sheets = [];

        for ($i = 0; $i < count($this->sheetData); $i++) {
            $sheets[] = new AmlTransactionSheet($this->sheetData[$i]);
        }

        return $sheets;
    }
}


class AmlTransactionSheet implements FromArray
{

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }
}
