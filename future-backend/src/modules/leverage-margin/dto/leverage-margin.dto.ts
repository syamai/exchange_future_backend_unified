import { IsNotEmpty, IsOptional } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";

export class LeverageMarginDto {
  @IsNotEmpty()
  @ApiProperty({
    required: true,
    example: 1,
  })
  tier: number;

  @ApiProperty({
    required: true,
    example: 1,
  })
  instrumentId: number;
  @IsOptional()
  @ApiProperty({
    example: 2,
  })
  min: number;

  @IsOptional()
  @ApiProperty({
    example: 3,
  })
  max: number;

  @IsOptional()
  @ApiProperty({
    example: 2,
  })
  maxLeverage: number;

  @IsOptional()
  @ApiProperty({
    example: 2,
  })
  maintenanceMarginRate: number;

  @IsOptional()
  @ApiProperty({
    example: 2,
  })
  maintenanceAmount: number;
}
