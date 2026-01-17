import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsOptional } from "class-validator";
import { MarginMode } from "src/shares/enums/order.enum";

export class UpdateMarginModeDto {
  @ApiProperty({
    required: true,
    description: "Id of instrument, get from GET: `/api/v1/instruments`",
    example: 1,
  })
  @IsOptional()
  instrumentId: number;

  @ApiProperty({
    required: true,
    description: "New margin mode",
    enum: MarginMode,
  })
  @IsOptional()
  @IsEnum(MarginMode)
  marginMode: MarginMode;

  @ApiProperty({ required: true, description: "New leverage", example: "25" })
  @IsOptional()
  leverage: string;
}
