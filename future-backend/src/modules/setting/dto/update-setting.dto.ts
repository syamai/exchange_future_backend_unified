import { IsNotEmpty } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";

export class UpdateSettingDto {
  @ApiProperty({
    required: true,
    description: "Value to be updated",
    example: "0.02",
  })
  @IsNotEmpty()
  value: string;
}
