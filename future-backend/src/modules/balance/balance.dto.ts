import { ApiProperty } from "@nestjs/swagger";
import { IsOptional, IsString } from "class-validator";

export class GetInforBalanceDto {
  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  symbol: string;
}
