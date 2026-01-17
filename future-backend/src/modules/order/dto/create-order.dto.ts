import { ApiProperty } from "@nestjs/swagger";
import { Transform, TransformFnParams } from "class-transformer";
import {
  IsBoolean,
  IsIn,
  IsNotEmpty,
  IsOptional,
  IsString,
  ValidateIf,
} from "class-validator";
import { IsPositiveBigNumber } from "src/shares/decorators/positive-bignumber.decorator";
import {
  AssetOrder,
  ContractType,
  OrderNote,
  OrderSide,
  OrderStatus,
  OrderStopCondition,
  OrderTimeInForce,
  OrderTrigger,
  OrderType,
  TpSlType,
} from "src/shares/enums/order.enum";
import { IsNotHaveSpace } from "../decorator/validate-decorator";

export class CreateOrderDto {
  @ApiProperty({
    description: "Side of order",
    name: "side",
    enum: OrderSide,
    example: OrderSide.BUY,
  })
  @IsNotEmpty()
  @IsIn(Object.keys(OrderSide))
  side: OrderSide;

  @ApiProperty({
    required: false,
    example: "USD_M",
  })
  @IsString()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;

  @ApiProperty({
    required: true,
    description: "Symbol of contract, get from /api/v1/instruments",
    example: "BTCUSDT",
  })
  @IsString()
  @IsNotEmpty()
  symbol: string;

  @ApiProperty({
    required: true,
    description: "Type of order, LIMIT or MARKET",
    enum: ["LIMIT", "MARKET"],
  })
  @IsNotEmpty()
  @IsIn(Object.keys(OrderType))
  type: OrderType;

  @ApiProperty({
    required: true,
    description: "Quantity of order",
    example: "1",
  })
  @IsNotEmpty()
  // @IsPositiveBigNumber()
  @IsNotHaveSpace("quantity")
  quantity: string;

  @ApiProperty({
    required: true,
    description: "Price of order",
    example: "29200",
  })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("price")
  price: string;

  @ApiProperty({
    required: true,
    description: "Equal to quantity",
    example: "1",
  })
  @Transform((params: TransformFnParams) => {
    console.log(params);
  }, {})
  remaining: string;

  // nullable
  // @ApiProperty()
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  executedPrice: string;

  @ApiProperty({
    description: "Tp sl type of order",
    enum: [TpSlType.STOP_MARKET, TpSlType.STOP_LIMIT, TpSlType.TRAILING_STOP],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(TpSlType))
  tpSLType: TpSlType;

  @ApiProperty({ description: "Stop/Tpsl price", example: "27000" })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("tpSLPrice")
  tpSLPrice: string;

  @ApiProperty({
    description:
      "Stop condition, if price greater than last price / mark price (depend on trigger) than GT else LT",
    required: false,
    enum: ["LT", "GT"],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderStopCondition))
  stopCondition: string;

  @ApiProperty({
    description:
      "Like stopCondition, compare takeProfit with trigger price (last price or mark price)",
    enum: ["LT", "GT"],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderStopCondition))
  takeProfitCondition: string;

  @ApiProperty({
    description:
      "Like stopCondition, compare stopLoss with trigger price (last price or mark price)",
    enum: ["LT", "GT"],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderStopCondition))
  stopLossCondition: string;

  @ApiProperty({
    description: "Price take profit",
    required: false,
    example: "29500",
  })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("takeProfit")
  takeProfit: string;

  @ApiProperty({
    description: "Price stop loss",
    required: false,
    example: "29000",
  })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("stopLoss")
  stopLoss: string;

  @ApiProperty({
    description: "Trigger type",
    enum: [OrderTrigger.LAST, OrderTrigger.ORACLE],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  trigger: OrderTrigger;

  @ApiProperty({
    description: "Time in force",
    enum: [OrderTimeInForce.FOK, OrderTimeInForce.GTC, OrderTimeInForce.IOC],
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTimeInForce))
  timeInForce: OrderTimeInForce;

  @ApiProperty({
    description: "Callback rate of trailing stop order",
    required: false,
    example: "1",
  })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("callbackRate")
  callbackRate: string;

  @ApiProperty({
    description: "Activation price of trailing stop order",
    required: false,
    example: "29500",
  })
  @ValidateIf((_object, value) => !!value)
  @IsPositiveBigNumber()
  @IsNotHaveSpace("activationPrice")
  activationPrice: string;

  @ApiProperty({
    description: "Trigger of take profit order",
    required: false,
    enum: [OrderTrigger.LAST, OrderTrigger.ORACLE],
  })
  @IsOptional()
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  takeProfitTrigger: OrderTrigger;

  @ApiProperty({
    description: "Trigger of stop loss order",
    required: false,
    enum: [OrderTrigger.LAST, OrderTrigger.ORACLE],
  })
  @IsOptional()
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(OrderTrigger))
  stopLossTrigger: OrderTrigger;

  @ApiProperty({ description: "is post only", required: false })
  @IsBoolean()
  isPostOnly: boolean;

  @ApiProperty({
    description: "asset of order, map with symbol",
    enum: Object.keys(AssetOrder),
  })
  @ValidateIf((_object, value) => !!value)
  @IsIn(Object.keys(AssetOrder))
  asset: string;

  status: OrderStatus;

  isHidden: boolean;

  // nullable
  isReduceOnly: boolean;

  // nullable
  isMam: boolean;

  // nullable
  pairType: string;

  // nullable
  referenceId: number;

  // nullable
  note: OrderNote;

  lockPrice: string;

  orderValue: string;
}
