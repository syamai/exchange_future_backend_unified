import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty } from "class-validator";

export class CreateSettingDto {
  @ApiProperty({
    required: true,
    description: "Value to be created",
    example: "0.02",
  })
  @IsNotEmpty()
  value: string;
}
