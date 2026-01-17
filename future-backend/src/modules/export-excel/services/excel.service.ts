// excel.service.ts
import { Injectable } from '@nestjs/common';
import * as ExcelJS from 'exceljs';

@Injectable()
export class ExcelService {
  async generateExcelBuffer(
    columnNames: string[],
    columnDataKeys: string[],
    data: any[],
  ) {
    // Create a new workbook
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Sheet1');

    // Define columns
    const columns = columnNames.map((name, index) => ({
      header: name,
      key: columnDataKeys[index],
    }));

    // Add columns to the worksheet
    worksheet.columns = columns;

    // Add data to the worksheet
    data.forEach((d, index) => {
      const rowData = columnDataKeys.map((key) => d[key]);
      worksheet.addRow(rowData);
      // const row = worksheet.getRow(index + 2);
    });

    // Apply number format to cells with numbers
    worksheet.eachRow((row, rowNumber) => {
      row.eachCell((cell) => {
        if (typeof cell.value === 'number') {
          // Set number format for cells with numbers
          cell.numFmt = '#,##0';
        }
        cell.alignment = { vertical: 'middle' };
      });
    });

    // Set alignment to center for all cells in each column
    worksheet.columns.forEach((column) => {
      column.alignment = { horizontal: 'center' };
    });

    // Generate the Excel file buffer
    const buffer = await workbook.xlsx.writeBuffer();
    return buffer
  }
}
