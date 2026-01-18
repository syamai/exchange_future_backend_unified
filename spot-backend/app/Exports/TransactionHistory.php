<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\Exportable;

class TransactionHistory implements FromArray, WithCustomStartCell
{
    use Exportable;

    protected $data;
    private $fileName;

    public function __construct(array $data, string $fileName)
    {
        $this->data = $data;
        $this->fileName = $fileName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function startCell(): string
    {
        return 'A0';
    }
}
