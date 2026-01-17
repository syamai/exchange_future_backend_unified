import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsOptional } from "class-validator";

export class GetPagListPositionHistoryBySessionAdminDto {
  @ApiPropertyOptional({ example: "test1@gmail.com" })
  @IsOptional()
  keyword?: string;

  @ApiPropertyOptional({ example: "BTCUSDT" })
  @IsOptional()
  symbol?: string;

  @ApiPropertyOptional({ example: "LONG" })
  @IsOptional()
  side?: string;

  @ApiPropertyOptional({ example: "CLOSED" })
  @IsOptional()
  status?: string;

  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  openTimeFrom?: string;

  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  openTimeTo?: string;

  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  closeTimeFrom?: string;

  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  closeTimeTo?: string;

  @ApiPropertyOptional({ example: 'realizedPnl', description: 'Sort by field: realizedPnl, fee, realizedPnlRate, or default(openTime)' })
  @IsOptional()
  sortBy?: 'realizedPnl' | 'fee' | 'realizedPnlRate' | string;

  @ApiPropertyOptional({ example: 'DESC', description: 'Sort direction: ASC or DESC' })
  @IsOptional()
  sortDirection?: 'ASC' | 'DESC';
}
