import { ApiProperty } from "@nestjs/swagger";
import {
  IsNotEmpty,
  IsNumber,
  IsNumberString,
  IsOptional,
} from "class-validator";
import { IsNotHaveSpace } from "src/modules/order/decorator/validate-decorator";

export class UpdateMarginDto {
  @ApiProperty({
    required: true,
    example: 12,
  })
  @IsNumber()
  @IsOptional()
  positionId: number;

  @ApiProperty({
    required: true,
    example: 1000,
  })
  @IsNumberString()
  @IsNotEmpty()
  @IsNotHaveSpace("assignedMarginValues")
  assignedMarginValue: string;
}
