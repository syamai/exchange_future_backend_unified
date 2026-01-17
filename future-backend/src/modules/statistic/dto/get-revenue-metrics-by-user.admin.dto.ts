import { ApiPropertyOptional } from "@nestjs/swagger";
import { IsArray, IsNotEmpty, IsOptional } from "class-validator";

export class GetRevenueMetricsByUserForAdminDto {
  @ApiPropertyOptional({ example: [1, 2, 3], type: [Number] })
  @IsArray()
  @IsNotEmpty()
  userIds: number[];
}
