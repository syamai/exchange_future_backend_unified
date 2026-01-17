import { ApiProperty } from "@nestjs/swagger";
import {
  IsIn,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  ValidateIf,
} from "class-validator";
import { IsNotHaveSpace } from "src/modules/order/decorator/validate-decorator";
import { IsPositiveBigNumber } from "src/shares/decorators/positive-bignumber.decorator";
import { OrderTrigger } from "src/shares/enums/order.enum";

export class UpdatePositionDto {
  @ApiProperty({
    required: true,
    example: 1,
  })
  @IsNumber()
  @IsNotEmpty()
  positionId: number;

  @ApiProperty()
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("takeProfit")
  takeProfit: string;

  @ApiProperty()
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("stopLoss")
  stopLoss: string;

  @ApiProperty()
  @IsOptional()
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  takeProfitTrigger: OrderTrigger;

  @ApiProperty()
  @IsOptional()
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  stopLossTrigger: OrderTrigger;
}
