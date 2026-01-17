import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsOptional } from "class-validator";

export class GetRevenueMetricsAdminDto {
  @ApiPropertyOptional({ example: "2025-07-18T09:30:08.113Z" })
  @IsOptional()
  fromDate?: string;

  @ApiPropertyOptional({ example: "2025-07-19T09:30:08.113Z" })
  @IsOptional()
  toDate?: string;
}
