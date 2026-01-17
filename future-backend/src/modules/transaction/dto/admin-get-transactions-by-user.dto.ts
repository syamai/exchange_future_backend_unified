import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsString } from "class-validator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";

export class AdminGetTransactionByUserDto extends PaginationDto {
  @ApiProperty({
    required: true,
    example: "1",
  })
  @IsString()
  @IsNotEmpty()
  userId: string;
}
