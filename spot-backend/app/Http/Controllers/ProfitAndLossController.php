<?php

namespace App\Http\Controllers;

use App\Exports\ProfitAndLossExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use App\Http\Services\TransactionService;

class ProfitAndLossController extends Controller
{
    private TransactionService $profitAndLoss;

    public function __construct(TransactionService $profitAndLoss)
    {
        $this->profitAndLoss = $profitAndLoss;
    }

    public function exportToExcel(Request $request)
    {
        $rows = $this->profitAndLoss->exportExcelProfitAndLoss($request->all());
        ExcelFacade::download(new ProfitAndLossExport($rows), 'ReturnRate', Excel::XLSX);
    }
}
