import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsOptional } from "class-validator";

export class GetPagListOrdersByPositionHistoryBySessionIdDto {
  @ApiPropertyOptional({ example: "date" })
  @IsOptional()
  sortBy?: "date" | string;

  @ApiPropertyOptional({ example: "DESC" })
  @IsOptional()
  sortDirection?: "ASC" | "DESC";
}
