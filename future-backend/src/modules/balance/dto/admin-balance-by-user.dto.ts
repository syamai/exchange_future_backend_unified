import { IsNotEmpty } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";

export class AdminGetBalanceByUserDto {
  @IsNotEmpty()
  @ApiProperty({
    required: true,
    example: 1,
  })
  userId: number;
}
