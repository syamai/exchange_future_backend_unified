import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsNotEmpty, IsNumber, IsOptional } from "class-validator";
import { IsPositiveBigNumber } from "src/shares/decorators/positive-bignumber.decorator";
import { ClosePositionType } from "src/shares/enums/position.enum";

export class ClosePositionDto {
  @ApiProperty({
    required: true,
    example: 1,
  })
  @IsNumber()
  @IsNotEmpty()
  positionId: number;

  @ApiProperty({
    required: true,
    example: 1,
  })
  // @IsNumber()
  @IsNotEmpty()
  quantity: string;

  @ApiProperty({
    required: true,
    example: ClosePositionType.MARKET,
  })
  @IsEnum(ClosePositionType)
  @IsNotEmpty()
  type: ClosePositionType;

  @ApiProperty({
    required: true,
  })
  @IsOptional()
  @IsPositiveBigNumber()
  limitPrice: string;
}
