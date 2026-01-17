import { ApiProperty, ApiPropertyOptional } from "@nestjs/swagger";
import { Transform } from "class-transformer";
import { IsBooleanString, IsDateString, IsInt, IsNumber, IsNumberString, IsOptional, IsPositive, IsString, Max } from "class-validator";

export class GetTradesPartnerDto {
  @ApiPropertyOptional({ example: 1 })
  @Transform(({ value }) => Number(value))
  @IsInt()
  @IsPositive()
  @IsOptional()
  page?: number = 1;

  @ApiPropertyOptional({ example: 20 })
  @Transform(({ value }) => Number(value))
  @IsInt()
  @Max(1000)
  @IsPositive()
  @IsOptional()
  pageSize?: number = 20;

  @ApiProperty({
    required: false,
    example: 1674259200000,
  })
  @IsOptional()
  startDate: number;

  @ApiProperty({
    required: false,
    example: 1674259200000,
  })
  @IsOptional()
  endDate: number;

  @ApiProperty({
    required: true,
    example: "1",
  })
  @IsNumberString()
  userId: string;

  @ApiProperty({
    required: false,
    example: "id",
  })
  @IsString()
  @IsOptional()
  sortBy: string;

  @ApiProperty({
    required: false,
    example: "true",
  })
  @IsOptional()
  @IsBooleanString()
  isDesc: string;

  @ApiProperty({
    required: false,
    example: "1",
  })
  @IsOptional()
  @IsNumberString()
  orderId?: string;

  @ApiProperty({
    required: false,
    example: "USDT",
  })
  @IsOptional()
  @IsString()
  currency?: string;
}
