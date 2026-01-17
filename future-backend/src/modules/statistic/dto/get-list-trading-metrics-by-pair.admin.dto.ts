import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsOptional } from "class-validator";

export class GetListTradingMetricsByPairAdminDto {
  @ApiPropertyOptional({ example: "BTCUSDT" })
  @IsOptional()
  pair?: string;

  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  startDate?: string;

  @ApiPropertyOptional({ example: "2025-07-19T09:30:08.113Z" })
  @IsOptional()
  endDate?: string;

  @ApiPropertyOptional({ example: "position" })
  @IsOptional()
  sort?: "position" | "trading_volume" | "fee" | "profit" | "loss" | "totalPnl";

  @ApiPropertyOptional({ name: "sort_type", example: "asc" })
  @IsOptional()
  sort_type?: "asc" | "desc";
}
