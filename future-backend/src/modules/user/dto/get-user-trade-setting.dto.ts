import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsNumber } from "class-validator";

export class GetUserTradeSettingDto {
  @IsNotEmpty()
  @IsNumber()
  @ApiProperty({
    required: true,
    example: 1,
  })
  userId: number;
}
