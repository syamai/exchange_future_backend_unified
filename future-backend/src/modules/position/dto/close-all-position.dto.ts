import { ApiProperty } from "@nestjs/swagger";
import { IsIn, IsString } from "class-validator";
import { ContractType } from "src/shares/enums/order.enum";

export class CloseAllPositionDto {
  @ApiProperty({
    required: false,
    example: "USD_M",
  })
  @IsString()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;
}
