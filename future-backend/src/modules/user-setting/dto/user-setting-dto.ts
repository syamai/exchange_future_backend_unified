import { ApiProperty } from "@nestjs/swagger";
import { IsBoolean, Min, Max, IsNumber, IsOptional } from "class-validator";

export class UpdateNotificationSettingDto {
  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  limitOrder: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  marketOrder: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  stopLimitOrder: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  stopMarketOrder: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  traillingStopOrder: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  takeProfitTrigger: boolean;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  stopLossTrigger: boolean;

  @ApiProperty({
    required: true,
    description: "set 0 to disable",
  })
  @IsOptional()
  @Min(0.001)
  @Max(5)
  @IsNumber({ maxDecimalPlaces: 4 })
  fundingFeeTriggerValue: number;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsBoolean()
  fundingFeeTrigger: boolean;
}
