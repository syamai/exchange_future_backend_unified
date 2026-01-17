import { IsNotEmpty } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";

export class AdminGetListUserBalanceDto {
  @IsNotEmpty()
  @ApiProperty({
    required: true,
    example: [1, 2, 3, 4],
  })
  userIds: number[];
}
