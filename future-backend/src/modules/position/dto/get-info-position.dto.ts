import { ApiProperty } from "@nestjs/swagger";
import { IsOptional, IsString } from "class-validator";

export class GetInforPositionDto {
  @ApiProperty({
    required: false,
    example: "BTCUSDT",
  })
  @IsString()
  @IsOptional()
  symbol: string;
}
