import { ApiProperty } from "@nestjs/swagger";
import {
  IsOptional,
  ValidateIf,
  IsIn,
  IsNumber,
  IsNotEmpty,
} from "class-validator";
import { IsPositiveBigNumber } from "src/shares/decorators/positive-bignumber.decorator";
import { OrderTrigger } from "src/shares/enums/order.enum";

export class UpdateTpSlOrderDto {
  @ApiProperty({
    required: true,
    example: 1,
  })
  @IsNotEmpty()
  // @IsNumber()
  orderId: string;

  @ApiProperty({
    required: false,
    example: "18000",
  })
  @IsOptional()
  @IsPositiveBigNumber()
  tpSLPrice: string;

  @ApiProperty({
    required: false,
    example: "LAST",
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  trigger: OrderTrigger;
}
