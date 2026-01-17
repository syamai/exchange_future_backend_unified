import { ApiProperty } from "@nestjs/swagger";
import { IsNumber, IsNotEmpty, IsOptional, IsString } from "class-validator";

export class RemoveTpSlDto {
  @ApiProperty({
    required: true,
    example: 1,
  })
  @IsNumber()
  @IsNotEmpty()
  positionId: number;

  @ApiProperty()
  @IsOptional()
  @IsString()
  takeProfitOrderId: string;

  @ApiProperty()
  @IsOptional()
  @IsString()
  stopLossOrderId: string;
}
