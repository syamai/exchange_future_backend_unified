import { IsNotEmpty, IsNumber } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";

export class AdminCancelOrderDto {
  @IsNotEmpty()
  @IsNumber()
  @ApiProperty({
    required: true,
    example: 1,
  })
  userId: number

  @IsNotEmpty()
  @IsNumber()
  @ApiProperty({
    required: true,
    example: 2,
  })
  orderId: number
}
