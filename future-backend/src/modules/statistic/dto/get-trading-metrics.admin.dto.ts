import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsOptional } from "class-validator";

export class GetTradingMetricsAdminDto {
  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  startDate?: string;

  @ApiPropertyOptional({ example: "2025-07-19T09:30:08.113Z" })
  @IsOptional()
  endDate?: string;
}
