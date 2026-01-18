<?php

namespace App\Exports\Module;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use App\Exports\Module\BaseExcelExport;

class ExcelDataExport
{
    public function export(array $params)
    {
        $export = new BaseExcelExport($params);

        $fileName = $params['fileName'] ?? 'export';
        $ext = $params['ext'] ?? 'xlsx';

        // Xác định writer type
        $writerType = match (strtolower($ext)) {
            'csv' => ExcelFormat::CSV,
            'xls' => ExcelFormat::XLS,
            default => ExcelFormat::XLSX,
        };

        // Lấy headers nếu truyền, hoặc dùng mặc định
        $headers = $params['headers'] ?? [
            'Content-Type' => 'application/octet-stream',
            'X-Generated-By' => 'Laravel Export System',
        ];

        return Excel::download($export, "$fileName.$ext", $writerType, $headers);
    }

    public function exportStore(array $params)
    {
        // Create an instance of BaseExcelExport with the given parameters
        $export = new BaseExcelExport($params);

        // Determine the file name and path
        $fileName = $params['fileName'] ?? 'export';
        $ext = $params['ext'] ?? 'xlsx';
        $filePath = "exports/{$fileName}.{$ext}";

        // Save the file to local storage
        $success = Excel::store($export, $filePath, 'local');

        // Return the file path if the storage was successful
        if ($success) {
            return [
                'status' => 'success',
                'filePath' => $filePath,
                'fullPath' => storage_path("app/{$filePath}"),
            ];
        }

        // Return an error response if saving failed
        return [
            'status' => 'error',
            'message' => 'Failed to store the export file.',
        ];
    }
}
