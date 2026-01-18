<?php
namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class WalletExport implements FromArray, WithCustomStartCell
{
    use Exportable;
    private $data;
    private $fileName;
    public function __construct($data, $fileName)
    {
        $this->data = $data;
        $this->fileName = $fileName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function title(): string
    {
        return 'History';
    }

    public function startCell(): string
    {
        return 'A1';
    }
}
