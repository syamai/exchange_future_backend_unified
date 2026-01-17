import { ApiProperty } from "@nestjs/swagger";
import { IsIn, IsOptional, IsString } from "class-validator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ContractType } from "src/shares/enums/order.enum";
import { TransactionType } from "src/shares/enums/transaction.enum";

export class TransactionHistoryDto extends PaginationDto {
  @ApiProperty({ required: true, description: "Start time in timestamp" })
  @IsString()
  startTime: string;

  @ApiProperty({ required: true, description: "End time in timestamp" })
  @IsString()
  endTime: string;

  @ApiProperty({
    required: false,
    description: "Asset transaction",
    example: "USDT",
  })
  @IsOptional()
  @IsString()
  asset: string;

  @ApiProperty({
    required: false,
    description: "Type of transaction",
    enum: TransactionType,
    example: "DEPOSIT",
  })
  @IsOptional()
  @IsString()
  type: string;

  @ApiProperty({
    required: false,
    example: "USD_M",
    enum: ["USD_M", "COIN_M"],
  })
  @IsString()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;
}
